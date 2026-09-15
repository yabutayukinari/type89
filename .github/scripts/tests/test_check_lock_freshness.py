"""check_lock_freshness.py の unittest。

ネットワークと git を一切使わない。HTTP を取りに行く関数・現在時刻・
`git show`/`git merge-base` を読む関数はすべて差し替える。
"""

from __future__ import annotations

import contextlib
import datetime as dt
import http.client
import io
import json
import subprocess
import sys
import unittest
import urllib.error
from pathlib import Path
from unittest import mock

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
sys.path.insert(0, str(Path(__file__).resolve().parent))

import check_lock_freshness as clf  # noqa: E402
import fixtures_freshness_143 as fx  # noqa: E402

PACKAGIST_URL = clf.PACKAGIST_NOTIFICATION_URL


def make_git_fakes(base_sha, base_text, head_sha, head_text, merge_base_sha="MERGEBASE"):
    """git merge-base / git show を差し替える偽の実装を作る。"""

    def git_merge_base(base, head):
        assert base == base_sha, base
        assert head == head_sha, head
        return merge_base_sha

    def git_show(ref):
        if ref == f"{merge_base_sha}:composer.lock":
            return base_text
        if ref == f"{head_sha}:composer.lock":
            return head_text
        raise AssertionError(f"想定外の git show 引数: {ref}")

    return git_show, git_merge_base


def make_counting_fetch(responses):
    """(vendor, name) -> 生テキストの辞書から、呼び出しを記録する偽の fetch 関数を作る。"""
    calls: list[tuple[str, str]] = []

    def fetch(vendor, name):
        calls.append((vendor, name))
        key = (vendor, name)
        if key not in responses:
            raise AssertionError(f"想定外の p2 問い合わせ: {vendor}/{name}")
        return responses[key]

    return fetch, calls


class Scenario143Tests(unittest.TestCase):
    """a〜c. #143（511fa0b -> 53a59b9）を使ったテスト。"""

    BASE_SHA = "511fa0b"
    HEAD_SHA = "53a59b9"

    EXPECTED_DEV = {"ramsey/uuid"}
    EXPECTED_MAJOR = {"guzzlehttp/uri-template", "hamcrest/hamcrest-php", "brick/math"}
    EXPECTED_AGE = {
        "laravel/framework",
        "monolog/monolog",
        "nesbot/carbon",
        "friendsofphp/php-cs-fixer",
        "larastan/larastan",
        "phpstan/phpstan",
    }

    def run_main(self, *, override, fetch, base_text=None, head_text=None):
        base_text = fx.BASE_LOCK_143 if base_text is None else base_text
        head_text = fx.HEAD_LOCK_143 if head_text is None else head_text
        stream = io.StringIO()
        git_show, git_merge_base = make_git_fakes(
            self.BASE_SHA, base_text, self.HEAD_SHA, head_text
        )
        argv = ["--base", self.BASE_SHA, "--head", self.HEAD_SHA]
        if override:
            argv.append("--override")
        exit_code = clf.main(
            argv,
            git_show=git_show,
            git_merge_base=git_merge_base,
            fetch_json_text=fetch,
            now_func=lambda: fx.FIXED_NOW_143,
            stream=stream,
        )
        return exit_code, stream.getvalue()

    def test_a_fails_with_expected_reasons(self):
        fetch, calls = make_counting_fetch(fx.P2_RESPONSES_143)
        exit_code, output = self.run_main(override=False, fetch=fetch)

        self.assertEqual(exit_code, 1)
        # 問い合わせを飛ばすのは安定度が dev の ramsey/uuid だけ。メジャーの上げの
        # 3 件（brick/math・guzzlehttp/uri-template・hamcrest/hamcrest-php）も問い合わせる
        self.assertEqual(len(calls), 32)

        for name in self.EXPECTED_DEV:
            self.assertIn(f"::error::{name}:", output)
            self.assertRegex(output, rf"{name}:.*dev 版のため不合格")
        for name in self.EXPECTED_MAJOR:
            self.assertIn(f"::error::{name}:", output)
            self.assertRegex(output, rf"{name}:.*メジャーバージョンが上がったため不合格")
        for name in self.EXPECTED_AGE:
            self.assertIn(f"::error::{name}:", output)
            self.assertRegex(output, rf"{name}:.*日未満のため不合格")

        self.assertEqual(output.count("::error::"), 10)
        self.assertIn("動いたパッケージ: 33件、問い合わせ: 32件、不合格: 10件", output)

    def test_b_no_change_passes_with_zero_http_calls(self):
        fetch, calls = make_counting_fetch({})
        exit_code, output = self.run_main(
            override=False, fetch=fetch, base_text=fx.HEAD_LOCK_143, head_text=fx.HEAD_LOCK_143
        )
        self.assertEqual(exit_code, 0)
        self.assertEqual(len(calls), 0)
        self.assertIn("動いたパッケージ: 0件、問い合わせ: 0件、不合格: 0件", output)

    def test_c_override_emits_warning_not_error(self):
        fetch, calls = make_counting_fetch(fx.P2_RESPONSES_143)
        exit_code, output = self.run_main(override=True, fetch=fetch)

        self.assertEqual(exit_code, 0)
        self.assertEqual(len(calls), 32)
        self.assertEqual(output.count("::warning::"), 10)
        self.assertNotIn("::error::", output)

        for name in self.EXPECTED_DEV | self.EXPECTED_MAJOR | self.EXPECTED_AGE:
            self.assertIn(f"::warning::{name}:", output)


class UndeterminableTests(unittest.TestCase):
    """d. 判定不能は不合格になるケース（--override では警告付きで合格になる）。"""

    BASE_SHA = "0000000"
    HEAD_SHA = "1111111"
    NOW = dt.datetime(2026, 9, 15, 0, 0, 0, tzinfo=dt.timezone.utc)

    def make_lock_texts(self, notification_url=PACKAGIST_URL):
        base = {
            "packages": [
                {
                    "name": "acme/widget",
                    "version": "1.0.0",
                    "notification-url": notification_url,
                    "source": {"reference": "base-ref"},
                    "dist": {"reference": "base-ref"},
                }
            ],
            "packages-dev": [],
        }
        head = {
            "packages": [
                {
                    "name": "acme/widget",
                    "version": "1.1.0",
                    "notification-url": notification_url,
                    "source": {"reference": "head-ref"},
                    "dist": {"reference": "head-ref"},
                }
            ],
            "packages-dev": [],
        }
        return json.dumps(base), json.dumps(head)

    def run_scenario(self, *, override, fetch, notification_url=PACKAGIST_URL):
        base_text, head_text = self.make_lock_texts(notification_url)
        git_show, git_merge_base = make_git_fakes(self.BASE_SHA, base_text, self.HEAD_SHA, head_text)
        argv = ["--base", self.BASE_SHA, "--head", self.HEAD_SHA]
        if override:
            argv.append("--override")
        stream = io.StringIO()
        exit_code = clf.main(
            argv,
            git_show=git_show,
            git_merge_base=git_merge_base,
            fetch_json_text=fetch,
            now_func=lambda: self.NOW,
            stream=stream,
        )
        return exit_code, stream.getvalue()

    def assert_fails_then_overrides(self, fetch_factory, notification_url=PACKAGIST_URL):
        exit_code, output = self.run_scenario(
            override=False, fetch=fetch_factory(), notification_url=notification_url
        )
        self.assertEqual(exit_code, 1)
        self.assertIn("::error::acme/widget:", output)
        self.assertIn("判定不能", output)

        exit_code, output = self.run_scenario(
            override=True, fetch=fetch_factory(), notification_url=notification_url
        )
        self.assertEqual(exit_code, 0)
        self.assertIn("::warning::acme/widget:", output)
        self.assertIn("判定不能", output)

    def test_http_error_404(self):
        def factory():
            def fetch(vendor, name):
                raise clf.PackagistFetchError("HTTP エラー 404")

            return fetch

        self.assert_fails_then_overrides(factory)

    def test_timeout(self):
        # 差し戻し9: 取り直しを使い切った失敗は retryable=True で送出される
        # （run_check の連続失敗カウンタが数える対象と同じ形にする）。
        def factory():
            def fetch(vendor, name):
                raise clf.PackagistFetchError(
                    "タイムアウトなどで取得できなかった (...)", retryable=True
                )

            return fetch

        self.assert_fails_then_overrides(factory)

    def test_broken_json(self):
        def factory():
            def fetch(vendor, name):
                return "{not valid json"

            return fetch

        self.assert_fails_then_overrides(factory)

    def test_version_not_found(self):
        def factory():
            def fetch(vendor, name):
                payload = {
                    "minified": "composer/2.0",
                    "packages": {
                        "acme/widget": [
                            {
                                "name": "acme/widget",
                                "version": "0.9.0",
                                "source": {"reference": "old-ref"},
                                "dist": {"reference": "old-ref"},
                                "time": "2020-01-01T00:00:00+00:00",
                                "published-time": "2020-01-01T00:00:00+00:00",
                            }
                        ]
                    },
                }
                return json.dumps(payload)

            return fetch

        self.assert_fails_then_overrides(factory)

    def test_published_time_missing(self):
        def factory():
            def fetch(vendor, name):
                payload = {
                    "minified": "composer/2.0",
                    "packages": {
                        "acme/widget": [
                            {
                                "name": "acme/widget",
                                "version": "1.1.0",
                                "source": {"reference": "head-ref"},
                                "dist": {"reference": "head-ref"},
                                "time": "2020-01-01T00:00:00+00:00",
                                # published-time が無い
                            }
                        ]
                    },
                }
                return json.dumps(payload)

            return fetch

        self.assert_fails_then_overrides(factory)

    def test_published_time_in_future(self):
        def factory():
            def fetch(vendor, name):
                future = (self.NOW + dt.timedelta(days=1)).isoformat()
                payload = {
                    "minified": "composer/2.0",
                    "packages": {
                        "acme/widget": [
                            {
                                "name": "acme/widget",
                                "version": "1.1.0",
                                "source": {"reference": "head-ref"},
                                "dist": {"reference": "head-ref"},
                                "time": future,
                                "published-time": future,
                            }
                        ]
                    },
                }
                return json.dumps(payload)

            return fetch

        self.assert_fails_then_overrides(factory)

    def test_notification_url_not_packagist(self):
        # 直すとよい7-1: 偽の fetch は呼ばれたら記録するだけにして、呼ばれていない
        # ことを直接確かめる（AssertionError を投げる形だと、判定不能に化けて
        # 通ってしまう壊れた実装でもテストが通ってしまう）。理由の文言も確かめる。
        calls: list[tuple[str, str]] = []

        def fetch(vendor, name):
            calls.append((vendor, name))
            return json.dumps({"packages": {}})

        exit_code, output = self.run_scenario(
            override=False, fetch=fetch, notification_url="https://example.com/downloads/"
        )
        self.assertEqual(exit_code, 1)
        self.assertIn("::error::acme/widget:", output)
        self.assertIn("notification-url が Packagist 以外の出どころ", output)
        self.assertEqual(calls, [])

        exit_code, output = self.run_scenario(
            override=True, fetch=fetch, notification_url="https://example.com/downloads/"
        )
        self.assertEqual(exit_code, 0)
        self.assertIn("::warning::acme/widget:", output)
        self.assertIn("notification-url が Packagist 以外の出どころ", output)
        self.assertEqual(calls, [])


class DowngradeTests(unittest.TestCase):
    """e. 下げは通る。"""

    def test_downgrade_passes(self):
        base = {
            "packages": [
                {
                    "name": "brick/math",
                    "version": "0.19.1",
                    "notification-url": PACKAGIST_URL,
                    "source": {"reference": "base-brick"},
                    "dist": {"reference": "base-brick"},
                },
                {
                    "name": "ramsey/uuid",
                    "version": "4.x-dev",
                    "notification-url": PACKAGIST_URL,
                    "source": {"reference": "base-uuid"},
                    "dist": {"reference": "base-uuid"},
                },
            ],
            "packages-dev": [],
        }
        head = {
            "packages": [
                {
                    "name": "brick/math",
                    "version": "0.18.0",
                    "notification-url": PACKAGIST_URL,
                    "source": {"reference": "head-brick"},
                    "dist": {"reference": "head-brick"},
                },
                {
                    "name": "ramsey/uuid",
                    "version": "4.9.3",
                    "notification-url": PACKAGIST_URL,
                    "source": {"reference": "head-uuid"},
                    "dist": {"reference": "head-uuid"},
                },
            ],
            "packages-dev": [],
        }
        base_text = json.dumps(base)
        head_text = json.dumps(head)

        # ramsey/uuid 4.9.3 は 2026-06-18T04:04:58+00:00（技術調査担当が実測した値と同じ）
        published_times = {
            ("brick", "math"): "2026-06-14T18:50:35+00:00",
            ("ramsey", "uuid"): "2026-06-18T04:04:58+00:00",
        }

        def fetch(vendor, name):
            key = (vendor, name)
            if key not in published_times:
                raise AssertionError(f"想定外の問い合わせ: {vendor}/{name}")
            full_name = f"{vendor}/{name}"
            target_version = "0.18.0" if name == "math" else "4.9.3"
            target_ref = "head-brick" if name == "math" else "head-uuid"
            published = published_times[key]
            payload = {
                "minified": "composer/2.0",
                "packages": {
                    full_name: [
                        {
                            "name": full_name,
                            "version": "0.0.1-fixture",
                            "source": {"reference": "unused"},
                            "dist": {"reference": "unused"},
                            "time": "2020-01-01T00:00:00+00:00",
                            "published-time": "2020-01-01T00:00:00+00:00",
                        },
                        {
                            "version": target_version,
                            "source": {"reference": target_ref},
                            "dist": {"reference": target_ref},
                            "time": published,
                            "published-time": published,
                        },
                    ]
                },
            }
            return json.dumps(payload)

        git_show, git_merge_base = make_git_fakes("2222222", base_text, "3333333", head_text)
        now = dt.datetime(2026, 9, 15, 0, 0, 0, tzinfo=dt.timezone.utc)
        stream = io.StringIO()
        exit_code = clf.main(
            ["--base", "2222222", "--head", "3333333"],
            git_show=git_show,
            git_merge_base=git_merge_base,
            fetch_json_text=fetch,
            now_func=lambda: now,
            stream=stream,
        )
        self.assertEqual(exit_code, 0, stream.getvalue())


class StabilityTableTests(unittest.TestCase):
    """f. 安定度の表。"""

    def test_stability_table(self):
        cases = [
            ("dev-main", "dev"),
            ("4.x-dev", "dev"),
            ("1.0.0-beta1", "beta"),
            ("1.0.0-RC2", "RC"),
            ("v2.0.0-alpha", "alpha"),
            ("1.0.0", "stable"),
            ("1.0.0-p1", "stable"),  # p は patch で stable
            ("1.0.0-patch", "stable"),
            ("1.0.0-pl", "stable"),
            ("1.0.0-b1", "beta"),
            ("1.0.0-a1", "alpha"),
            ("1.0.0-rc1", "RC"),  # 小文字の rc
            ("1.0.0-BETA", "beta"),  # 大文字の BETA
            ("dev-main#abc", "dev"),
            ("1.0.x-dev", "dev"),
            ("1.0.0-dev", "dev"),
            ("1.0.0+build", "stable"),
        ]
        for version, expected in cases:
            with self.subTest(version=version):
                self.assertEqual(clf.parse_stability(version), expected)


class MajorBoundaryTests(unittest.TestCase):
    """g. メジャーの境目。"""

    def test_major_boundary(self):
        cases = [
            ("0.18.0", "0.19.1", True),  # 0.x は minor が増えたら不合格
            ("0.18.0", "0.18.1", False),  # 同じ minor は合格
            ("1.9.0", "2.0.0", True),  # 1.x 以上は major が増えたら不合格
            ("v1.9.0", "v2.0.0", True),  # v 付きでも同じ
            ("V1.9.0", "V2.0.0", True),  # 直すとよい7-5: 大文字の V でも同じ（IGNORECASE）
            ("2.0.0", "1.9.0", False),  # 下げは合格
            ("0.19.1", "0.18.0", False),  # 下げは合格
            ("0.9.0", "1.0.0", True),  # 0.x -> 1.x も上げとして不合格
            ("1.2.0", "1.5.0", False),  # 同じメジャーの中は合格
            ("1.0.0", "2.0.0-dev", False),  # 片方が dev のときは判定しない
            ("not-a-version", "1.0.0", False),  # 比べられない文字列は判定しない
        ]
        for base_version, head_version, expected in cases:
            with self.subTest(base=base_version, head=head_version):
                self.assertEqual(
                    clf.major_bump_rejected(base_version, head_version), expected
                )


class CombinedReasonTests(unittest.TestCase):
    """i. 1つのパッケージに理由が複数あるとき（メジャーの上げ かつ 公開から7日未満）、
    両方の理由を1つの Reason に出す。
    """

    def test_major_bump_and_recent_publish_combine(self):
        now = dt.datetime(2026, 9, 15, 0, 0, 0, tzinfo=dt.timezone.utc)
        base_pkg = clf.PackageEntry(
            name="acme/combo",
            version="1.0.0",
            source_reference="base-ref",
            dist_reference="base-ref",
            notification_url=PACKAGIST_URL,
        )
        head_pkg = clf.PackageEntry(
            name="acme/combo",
            version="2.0.0",
            source_reference="head-ref",
            dist_reference="head-ref",
            notification_url=PACKAGIST_URL,
        )

        # フィクスチャの偽の値。公開から2日しか経っていないことにする
        published_recent = (now - dt.timedelta(days=2)).isoformat()

        def fetch(vendor, name):
            payload = {
                "minified": "composer/2.0",
                "packages": {
                    "acme/combo": [
                        {
                            "name": "acme/combo",
                            "version": "2.0.0",
                            "source": {"reference": "head-ref"},
                            "dist": {"reference": "head-ref"},
                            "time": published_recent,
                            "published-time": published_recent,
                        }
                    ]
                },
            }
            return json.dumps(payload)

        reason, queried = clf.evaluate_package(
            base_pkg,
            head_pkg,
            now=now,
            min_age_days=7,
            fetch_json_text=fetch,
        )

        self.assertTrue(queried)
        self.assertIsNotNone(reason)
        self.assertIn("メジャーバージョンが上がったため不合格", reason.message)
        self.assertIn("日未満のため不合格", reason.message)


class MinifyExpandTests(unittest.TestCase):
    """h. minify の展開。"""

    def test_expand_inherits_previous_and_applies_unset(self):
        versions = [
            {
                "version": "1.0.0",
                "source": {"reference": "aaa"},
                "dist": {"reference": "aaa"},
                "published-time": "2020-01-01T00:00:00+00:00",
                "abandoned": True,
            },
            {
                # version・source・dist だけが変わる。published-time・abandoned は省かれている
                # ＝ 1.0.0 から引き継ぐはず
                "version": "1.1.0",
                "source": {"reference": "bbb"},
                "dist": {"reference": "bbb"},
            },
            {
                # published-time は変わり、abandoned は __unset で消える
                "version": "1.2.0",
                "source": {"reference": "ccc"},
                "dist": {"reference": "ccc"},
                "published-time": "2020-03-01T00:00:00+00:00",
                "abandoned": "__unset",
            },
        ]

        # 展開前は 1.1.0 の要素に published-time が無く見える
        self.assertNotIn("published-time", versions[1])

        expanded = clf.expand_minified_versions(versions)

        self.assertEqual(len(expanded), 3)

        self.assertEqual(expanded[0]["version"], "1.0.0")
        self.assertEqual(expanded[0]["published-time"], "2020-01-01T00:00:00+00:00")
        self.assertIs(expanded[0]["abandoned"], True)

        # 展開すると 1.0.0 から published-time・abandoned を引き継いで読める
        self.assertEqual(expanded[1]["version"], "1.1.0")
        self.assertEqual(expanded[1]["source"], {"reference": "bbb"})
        self.assertEqual(expanded[1]["published-time"], "2020-01-01T00:00:00+00:00")
        self.assertIs(expanded[1]["abandoned"], True)

        self.assertEqual(expanded[2]["version"], "1.2.0")
        self.assertEqual(expanded[2]["published-time"], "2020-03-01T00:00:00+00:00")
        self.assertNotIn("abandoned", expanded[2])

        # 元の versions は変更されない（expanded_version は毎回コピーしている）
        self.assertNotIn("published-time", versions[1])


class MinifyIntegrationTests(unittest.TestCase):
    """展開のつなぎ込み: published-time を前の要素から引き継いでいる（その要素では
    省かれている）版を evaluate_package が正しく読めること
    （expand_minified_versions の呼び出しを消すと落ちる）。
    """

    def test_evaluate_package_reads_inherited_published_time(self):
        now = dt.datetime(2026, 9, 15, 0, 0, 0, tzinfo=dt.timezone.utc)
        base_pkg = clf.PackageEntry(
            name="acme/inherit",
            version="1.0.0",
            source_reference="aaa",
            dist_reference="aaa",
            notification_url=PACKAGIST_URL,
        )
        head_pkg = clf.PackageEntry(
            name="acme/inherit",
            version="1.1.0",
            source_reference="bbb",
            dist_reference="bbb",
            notification_url=PACKAGIST_URL,
        )

        def fetch(vendor, name):
            payload = {
                "minified": "composer/2.0",
                "packages": {
                    "acme/inherit": [
                        {
                            "name": "acme/inherit",
                            "version": "1.0.0",
                            "source": {"reference": "aaa"},
                            "dist": {"reference": "aaa"},
                            "published-time": "2020-01-01T00:00:00+00:00",
                        },
                        {
                            # version・source・dist だけが変わる。published-time は
                            # 省かれている＝ 1.0.0 から引き継ぐはず
                            "version": "1.1.0",
                            "source": {"reference": "bbb"},
                            "dist": {"reference": "bbb"},
                        },
                    ]
                },
            }
            return json.dumps(payload)

        reason, queried = clf.evaluate_package(
            base_pkg, head_pkg, now=now, min_age_days=7, fetch_json_text=fetch
        )
        self.assertTrue(queried)
        self.assertIsNone(reason, reason)


class DuplicateNameTests(unittest.TestCase):
    """必ず直す1. 同じ名前が2回以上出るパッケージは判定不能（不合格）。"""

    def _run(self, base_text, head_text, base_sha="4444444", head_sha="5555555"):
        git_show, git_merge_base = make_git_fakes(base_sha, base_text, head_sha, head_text)

        # 直すとよい7-1: 広い except を外したため、呼ばれてはいけないケースで
        # 実際に呼ばれてしまったときにテストが落ちるよう、記録するだけにする
        # （AssertionError を投げる形だと、その例外が main の外まで伝播して
        # 「スクリプトの不具合」扱いの別の失敗として通ってしまい、判定不能に
        # なったかどうかを確かめられない）。
        self.fetch_calls: list[tuple[str, str]] = []

        def fetch(vendor, name):
            self.fetch_calls.append((vendor, name))
            return json.dumps({"packages": {}})

        stream = io.StringIO()
        exit_code = clf.main(
            ["--base", base_sha, "--head", head_sha],
            git_show=git_show,
            git_merge_base=git_merge_base,
            fetch_json_text=fetch,
            now_func=lambda: dt.datetime(2026, 9, 15, tzinfo=dt.timezone.utc),
            stream=stream,
        )
        return exit_code, stream.getvalue()

    def test_duplicate_across_sections_in_head_is_flagged(self):
        # レビュー担当が再現したケース: head の packages に acme/w 1.1.0、
        # packages-dev に base と同じ acme/w 1.0.0。最後に読んだ方が辞書で
        # 上書きされ、version が base と同じに見えて moved から漏れないことを確かめる。
        base = {
            "packages": [
                {
                    "name": "acme/w",
                    "version": "1.0.0",
                    "notification-url": PACKAGIST_URL,
                    "source": {"reference": "base-ref"},
                    "dist": {"reference": "base-ref"},
                }
            ],
            "packages-dev": [],
        }
        head = {
            "packages": [
                {
                    "name": "acme/w",
                    "version": "1.1.0",
                    "notification-url": PACKAGIST_URL,
                    "source": {"reference": "head-ref"},
                    "dist": {"reference": "head-ref"},
                }
            ],
            "packages-dev": [
                {
                    "name": "acme/w",
                    "version": "1.0.0",
                    "notification-url": PACKAGIST_URL,
                    "source": {"reference": "base-ref"},
                    "dist": {"reference": "base-ref"},
                }
            ],
        }
        exit_code, output = self._run(json.dumps(base), json.dumps(head))
        self.assertEqual(exit_code, 1, output)
        self.assertIn("acme/w", output)
        self.assertIn("composer.lock に同じ名前が複数ある", output)
        self.assertNotIn("動いたパッケージ: 0件", output)
        self.assertEqual(self.fetch_calls, [])

    def test_duplicate_within_same_section(self):
        base_text = json.dumps({"packages": [], "packages-dev": []})
        head = {
            "packages": [
                {
                    "name": "acme/dup",
                    "version": "1.0.0",
                    "notification-url": PACKAGIST_URL,
                    "source": {"reference": "r1"},
                    "dist": {"reference": "r1"},
                },
                {
                    "name": "acme/dup",
                    "version": "1.0.1",
                    "notification-url": PACKAGIST_URL,
                    "source": {"reference": "r2"},
                    "dist": {"reference": "r2"},
                },
            ],
            "packages-dev": [],
        }
        exit_code, output = self._run(base_text, json.dumps(head), base_sha="6666666", head_sha="7777777")
        self.assertEqual(exit_code, 1, output)
        self.assertIn("composer.lock に同じ名前が複数ある", output)
        self.assertEqual(self.fetch_calls, [])


class LockParseErrorTests(unittest.TestCase):
    """必ず直す2. composer.lock が読めない（JSON 破損・形が想定と違う）ときは判定不能で、
    traceback を出さずに終わる。"""

    def _run(self, base_text, head_text, *, override=False, base_sha="8888888", head_sha="9999999"):
        git_show, git_merge_base = make_git_fakes(base_sha, base_text, head_sha, head_text)

        # 直すとよい7-1: 広い except を外したため、呼ばれてはいけないケースで
        # 実際に呼ばれてしまったときにテストが落ちるよう、記録するだけにする。
        self.fetch_calls: list[tuple[str, str]] = []

        def fetch(vendor, name):
            self.fetch_calls.append((vendor, name))
            return json.dumps({"packages": {}})

        argv = ["--base", base_sha, "--head", head_sha]
        if override:
            argv.append("--override")
        stream = io.StringIO()
        exit_code = clf.main(
            argv,
            git_show=git_show,
            git_merge_base=git_merge_base,
            fetch_json_text=fetch,
            now_func=lambda: dt.datetime(2026, 9, 15, tzinfo=dt.timezone.utc),
            stream=stream,
        )
        return exit_code, stream.getvalue()

    def test_broken_json_does_not_traceback(self):
        exit_code, output = self._run(
            "{not valid json", json.dumps({"packages": [], "packages-dev": []})
        )
        self.assertEqual(exit_code, 1)
        self.assertIn("composer.lock を読めない", output)
        self.assertIn("::error::", output)
        self.assertEqual(self.fetch_calls, [])

    def test_packages_not_a_list(self):
        head_text = json.dumps({"packages": "oops", "packages-dev": []})
        exit_code, output = self._run(json.dumps({"packages": [], "packages-dev": []}), head_text)
        self.assertEqual(exit_code, 1)
        self.assertIn("composer.lock を読めない", output)
        self.assertEqual(self.fetch_calls, [])

    def test_top_level_not_an_object(self):
        head_text = json.dumps([1, 2, 3])
        exit_code, output = self._run(json.dumps({"packages": [], "packages-dev": []}), head_text)
        self.assertEqual(exit_code, 1)
        self.assertIn("composer.lock を読めない", output)
        self.assertEqual(self.fetch_calls, [])

    def test_item_not_an_object(self):
        head_text = json.dumps({"packages": ["not-an-object"], "packages-dev": []})
        exit_code, output = self._run(json.dumps({"packages": [], "packages-dev": []}), head_text)
        self.assertEqual(exit_code, 1)
        self.assertIn("composer.lock を読めない", output)
        self.assertEqual(self.fetch_calls, [])

    def test_missing_both_sections(self):
        # 参考から直すもの11: packages も packages-dev も無い
        head_text = json.dumps({"name": "acme/app"})
        exit_code, output = self._run(json.dumps({"packages": [], "packages-dev": []}), head_text)
        self.assertEqual(exit_code, 1)
        self.assertIn("composer.lock を読めない", output)
        self.assertEqual(self.fetch_calls, [])

    def test_override_gives_warning_and_exit_zero(self):
        exit_code, output = self._run(
            "{not valid json",
            json.dumps({"packages": [], "packages-dev": []}),
            override=True,
        )
        self.assertEqual(exit_code, 0)
        self.assertIn("::warning::", output)
        self.assertNotIn("::error::", output)
        self.assertEqual(self.fetch_calls, [])


class GitCommandErrorTests(unittest.TestCase):
    """直すとよい6. RuntimeError ではなく専用の GitCommandError を使うこと。

    既定の run_git_show・run_git_merge_base は git の失敗（0 でない終了コード）を
    GitCommandError にして送出し、run_check はそれを LockParseError と同様に
    「composer.lock を読めない（判定不能）」として扱う。
    """

    def test_run_git_show_failure_raises_git_command_error(self):
        # 直すとよい3: stdout/stderr は bytes で受け取るようになったので、
        # ここも bytes で用意する（str のままだと completed.stderr.decode が
        # AttributeError になる）。
        completed = subprocess.CompletedProcess(
            args=["git", "show", "bad:composer.lock"],
            returncode=128,
            stdout=b"",
            stderr="fatal: bad revision 'bad:composer.lock'".encode("utf-8"),
        )
        with mock.patch.object(clf.subprocess, "run", return_value=completed):
            with self.assertRaises(clf.GitCommandError):
                clf.run_git_show("bad:composer.lock")

    def test_run_git_merge_base_failure_raises_git_command_error(self):
        completed = subprocess.CompletedProcess(
            args=["git", "merge-base", "aaa", "bbb"],
            returncode=1,
            stdout="",
            stderr="fatal: not a valid object name aaa",
        )
        with mock.patch.object(clf.subprocess, "run", return_value=completed):
            with self.assertRaises(clf.GitCommandError):
                clf.run_git_merge_base("aaa", "bbb")

    def test_run_git_show_success_decodes_utf8(self):
        # 直すとよい3: stdout は bytes で受け取り、UTF-8 として decode する。
        completed = subprocess.CompletedProcess(
            args=["git", "show", "HEAD:composer.lock"],
            returncode=0,
            stdout='{"packages": []}'.encode("utf-8"),
            stderr=b"",
        )
        with mock.patch.object(clf.subprocess, "run", return_value=completed):
            result = clf.run_git_show("HEAD:composer.lock")
        self.assertEqual(result, '{"packages": []}')

    def test_run_git_show_non_utf8_stdout_raises_lock_parse_error(self):
        # 直すとよい3: composer.lock が UTF-8 でないと decode に失敗する。
        # GitCommandError（git 自体の失敗）でも、素の UnicodeDecodeError
        # （スクリプトの不具合として main まで素通しされる）でもなく、
        # LockParseError（composer.lock を読めない・判定不能）にする。
        completed = subprocess.CompletedProcess(
            args=["git", "show", "HEAD:composer.lock"],
            returncode=0,
            stdout=b"\xff\xfe not valid utf-8",
            stderr=b"",
        )
        with mock.patch.object(clf.subprocess, "run", return_value=completed):
            with self.assertRaises(clf.LockParseError):
                clf.run_git_show("HEAD:composer.lock")

    def test_run_check_treats_git_command_error_as_undeterminable(self):
        def broken_git_show(ref):
            raise clf.GitCommandError(f"git show {ref} に失敗した: fatal: bad revision")

        def git_merge_base(base, head):
            return "MERGEBASE"

        self.fetch_calls: list[tuple[str, str]] = []

        def fetch(vendor, name):
            self.fetch_calls.append((vendor, name))
            return json.dumps({"packages": {}})

        stream = io.StringIO()
        exit_code = clf.main(
            ["--base", "0000000", "--head", "1111111"],
            git_show=broken_git_show,
            git_merge_base=git_merge_base,
            fetch_json_text=fetch,
            now_func=lambda: dt.datetime(2026, 9, 15, tzinfo=dt.timezone.utc),
            stream=stream,
        )
        output = stream.getvalue()
        self.assertEqual(exit_code, 1, output)
        self.assertIn("composer.lock を読めない", output)
        self.assertIn("::error::", output)
        self.assertEqual(self.fetch_calls, [])

    def test_run_check_with_override_treats_git_command_error_as_warning(self):
        def broken_git_show(ref):
            raise clf.GitCommandError(f"git show {ref} に失敗した: fatal: bad revision")

        def git_merge_base(base, head):
            return "MERGEBASE"

        stream = io.StringIO()
        exit_code = clf.main(
            ["--base", "0000000", "--head", "1111111", "--override"],
            git_show=broken_git_show,
            git_merge_base=git_merge_base,
            fetch_json_text=lambda v, n: (_ for _ in ()).throw(AssertionError("no fetch")),
            now_func=lambda: dt.datetime(2026, 9, 15, tzinfo=dt.timezone.utc),
            stream=stream,
        )
        output = stream.getvalue()
        self.assertEqual(exit_code, 0, output)
        self.assertIn("::warning::", output)
        self.assertNotIn("::error::", output)
        self.assertIn("composer.lock を読めない", output)


class ParseLockPackagesDirectTests(unittest.TestCase):
    """直すとよい7-2. parse_lock_packages を直接呼んで、形の違いごとに
    LockParseError が出ることを確かめる（main を介さず、壊れた実装が
    main の except に紛れて通ってしまわないようにする）。
    """

    def test_broken_json(self):
        with self.assertRaises(clf.LockParseError):
            clf.parse_lock_packages("{not valid json")

    def test_top_level_not_an_object(self):
        with self.assertRaises(clf.LockParseError):
            clf.parse_lock_packages(json.dumps([1, 2, 3]))

    def test_packages_not_a_list(self):
        with self.assertRaises(clf.LockParseError):
            clf.parse_lock_packages(json.dumps({"packages": "oops", "packages-dev": []}))

    def test_packages_null_is_lock_parse_error(self):
        # 直すとよい2: キーはあるが値が null（配列でない）は判定不能。
        # 「区分が無い」扱い（continue）にしてはいけない。
        with self.assertRaises(clf.LockParseError):
            clf.parse_lock_packages(json.dumps({"packages": None, "packages-dev": []}))

    def test_packages_dev_null_is_lock_parse_error(self):
        with self.assertRaises(clf.LockParseError):
            clf.parse_lock_packages(json.dumps({"packages": [], "packages-dev": None}))

    def test_packages_dev_missing_key_is_treated_as_absent_section(self):
        # packages-dev キー自体が無いときだけ「区分が無い」扱い（合格）。
        packages, duplicates = clf.parse_lock_packages(json.dumps({"packages": []}))
        self.assertEqual(packages, {})
        self.assertEqual(duplicates, set())

    def test_item_not_an_object(self):
        with self.assertRaises(clf.LockParseError):
            clf.parse_lock_packages(
                json.dumps({"packages": ["not-an-object"], "packages-dev": []})
            )

    def test_name_not_a_string(self):
        with self.assertRaises(clf.LockParseError):
            clf.parse_lock_packages(
                json.dumps(
                    {
                        "packages": [{"name": ["acme", "widget"], "version": "1.0.0"}],
                        "packages-dev": [],
                    }
                )
            )

    def test_name_missing_key_is_lock_parse_error(self):
        # 必ず直す1: name のキーが無いパッケージは黙って飛ばさず判定不能にする。
        with self.assertRaises(clf.LockParseError):
            clf.parse_lock_packages(
                json.dumps({"packages": [{"version": "1.0.0"}], "packages-dev": []})
            )

    def test_name_null_is_lock_parse_error(self):
        # 必ず直す1: name が null のパッケージも同様。
        with self.assertRaises(clf.LockParseError):
            clf.parse_lock_packages(
                json.dumps(
                    {"packages": [{"name": None, "version": "1.0.0"}], "packages-dev": []}
                )
            )

    def test_name_empty_string_is_lock_parse_error(self):
        # 必ず直す1: name が空文字のパッケージも同様。
        with self.assertRaises(clf.LockParseError):
            clf.parse_lock_packages(
                json.dumps(
                    {"packages": [{"name": "", "version": "1.0.0"}], "packages-dev": []}
                )
            )

    def test_version_missing_key_is_lock_parse_error(self):
        # 参考から直すもの6: version のキーが無いパッケージを、空文字扱いに
        # せず判定不能にする。
        with self.assertRaises(clf.LockParseError):
            clf.parse_lock_packages(
                json.dumps({"packages": [{"name": "acme/w"}], "packages-dev": []})
            )

    def test_version_null_is_lock_parse_error(self):
        # 直すとよい3: version が文字列でない（null）ときは判定不能。
        with self.assertRaises(clf.LockParseError):
            clf.parse_lock_packages(
                json.dumps(
                    {"packages": [{"name": "acme/w", "version": None}], "packages-dev": []}
                )
            )

    def test_version_number_is_lock_parse_error(self):
        # 直すとよい3: version が文字列でない（数値）ときも判定不能。
        with self.assertRaises(clf.LockParseError):
            clf.parse_lock_packages(
                json.dumps(
                    {"packages": [{"name": "acme/w", "version": 1}], "packages-dev": []}
                )
            )

    def test_source_not_a_dict_is_lock_parse_error(self):
        # 直すとよい4: source がキーはあるが辞書でないときは判定不能。
        with self.assertRaises(clf.LockParseError):
            clf.parse_lock_packages(
                json.dumps(
                    {
                        "packages": [
                            {"name": "acme/w", "version": "1.0.0", "source": "oops"}
                        ],
                        "packages-dev": [],
                    }
                )
            )

    def test_dist_not_a_dict_is_lock_parse_error(self):
        # 直すとよい4: dist も同様。
        with self.assertRaises(clf.LockParseError):
            clf.parse_lock_packages(
                json.dumps(
                    {
                        "packages": [
                            {"name": "acme/w", "version": "1.0.0", "dist": "oops"}
                        ],
                        "packages-dev": [],
                    }
                )
            )

    def test_source_reference_not_a_string_is_lock_parse_error(self):
        # 直すとよい4: source.reference が文字列でない（数値）ときは判定不能。
        with self.assertRaises(clf.LockParseError):
            clf.parse_lock_packages(
                json.dumps(
                    {
                        "packages": [
                            {
                                "name": "acme/w",
                                "version": "1.0.0",
                                "source": {"reference": 123},
                            }
                        ],
                        "packages-dev": [],
                    }
                )
            )

    def test_dist_reference_not_a_string_is_lock_parse_error(self):
        # 直すとよい4: dist.reference も同様。
        with self.assertRaises(clf.LockParseError):
            clf.parse_lock_packages(
                json.dumps(
                    {
                        "packages": [
                            {
                                "name": "acme/w",
                                "version": "1.0.0",
                                "dist": {"reference": 123},
                            }
                        ],
                        "packages-dev": [],
                    }
                )
            )

    def test_source_reference_null_is_treated_as_absent(self):
        # 直すとよい4: reference が null はキーが無いのと同じ扱い（判定不能にしない）。
        packages, _ = clf.parse_lock_packages(
            json.dumps(
                {
                    "packages": [
                        {
                            "name": "acme/w",
                            "version": "1.0.0",
                            "source": {"reference": None},
                        }
                    ],
                    "packages-dev": [],
                }
            )
        )
        self.assertIsNone(packages["acme/w"].source_reference)

    def test_missing_both_sections(self):
        with self.assertRaises(clf.LockParseError):
            clf.parse_lock_packages(json.dumps({"name": "acme/app"}))


class PackageNameValidationTests(unittest.TestCase):
    """パッケージ名の検証: Composer の命名規則に合わない名前は問い合わせずに判定不能。"""

    def test_invalid_name_is_undeterminable_without_query(self):
        base = {"packages": [], "packages-dev": []}
        head = {
            "packages": [
                {
                    "name": "Invalid_Vendor/Name",
                    "version": "1.0.0",
                    "notification-url": PACKAGIST_URL,
                    "source": {"reference": "ref"},
                    "dist": {"reference": "ref"},
                }
            ],
            "packages-dev": [],
        }
        git_show, git_merge_base = make_git_fakes(
            "cccc111", json.dumps(base), "dddd222", json.dumps(head)
        )

        # 直すとよい7-1: 広い except を外したため、呼ばれてはいけないケースで
        # 実際に呼ばれてしまったときにテストが落ちるよう、記録するだけにする。
        calls: list[tuple[str, str]] = []

        def fetch(vendor, name):
            calls.append((vendor, name))
            return json.dumps({"packages": {}})

        stream = io.StringIO()
        exit_code = clf.main(
            ["--base", "cccc111", "--head", "dddd222"],
            git_show=git_show,
            git_merge_base=git_merge_base,
            fetch_json_text=fetch,
            now_func=lambda: dt.datetime(2026, 9, 15, tzinfo=dt.timezone.utc),
            stream=stream,
        )
        self.assertEqual(exit_code, 1, stream.getvalue())
        self.assertIn("Composer の命名規則に合わない", stream.getvalue())
        self.assertEqual(calls, [])

    def test_name_with_trailing_newline_is_undeterminable_without_query(self):
        # 直すとよい7-2: 末尾に改行が付いた名前が正規表現の `$` の前でマッチして
        # しまい、誤って問い合わせに進んでしまわないことを確かめる
        # （`_COMPOSER_NAME_REGEX.fullmatch` を `search`/`match` に戻すと落ちる）。
        base = {"packages": [], "packages-dev": []}
        head = {
            "packages": [
                {
                    "name": "acme/widget\n",
                    "version": "1.0.0",
                    "notification-url": PACKAGIST_URL,
                    "source": {"reference": "ref"},
                    "dist": {"reference": "ref"},
                }
            ],
            "packages-dev": [],
        }
        git_show, git_merge_base = make_git_fakes(
            "eeee333", json.dumps(base), "ffff444", json.dumps(head)
        )

        calls: list[tuple[str, str]] = []

        def fetch(vendor, name):
            calls.append((vendor, name))
            return json.dumps({"packages": {}})

        stream = io.StringIO()
        exit_code = clf.main(
            ["--base", "eeee333", "--head", "ffff444"],
            git_show=git_show,
            git_merge_base=git_merge_base,
            fetch_json_text=fetch,
            now_func=lambda: dt.datetime(2026, 9, 15, tzinfo=dt.timezone.utc),
            stream=stream,
        )
        self.assertEqual(exit_code, 1, stream.getvalue())
        self.assertIn("Composer の命名規則に合わない", stream.getvalue())
        self.assertEqual(calls, [])


class ReferenceMismatchTests(unittest.TestCase):
    """直すとよい5. reference だけを変えた PR は、version の文字列が同じでも検出する。"""

    def test_reference_only_change_is_undeterminable(self):
        base = {
            "packages": [
                {
                    "name": "acme/refonly",
                    "version": "1.0.0",
                    "notification-url": PACKAGIST_URL,
                    "source": {"reference": "aaa"},
                    "dist": {"reference": "aaa"},
                }
            ],
            "packages-dev": [],
        }
        head = {
            "packages": [
                {
                    "name": "acme/refonly",
                    "version": "1.0.0",
                    "notification-url": PACKAGIST_URL,
                    "source": {"reference": "bbb"},
                    "dist": {"reference": "bbb"},
                }
            ],
            "packages-dev": [],
        }
        git_show, git_merge_base = make_git_fakes(
            "eeee111", json.dumps(base), "ffff222", json.dumps(head)
        )

        def fetch(vendor, name):
            payload = {
                "minified": "composer/2.0",
                "packages": {
                    "acme/refonly": [
                        {
                            "name": "acme/refonly",
                            "version": "1.0.0",
                            "source": {"reference": "aaa"},
                            "dist": {"reference": "aaa"},
                            "published-time": "2020-01-01T00:00:00+00:00",
                        }
                    ]
                },
            }
            return json.dumps(payload)

        stream = io.StringIO()
        exit_code = clf.main(
            ["--base", "eeee111", "--head", "ffff222"],
            git_show=git_show,
            git_merge_base=git_merge_base,
            fetch_json_text=fetch,
            now_func=lambda: dt.datetime(2026, 9, 15, tzinfo=dt.timezone.utc),
            stream=stream,
        )
        self.assertEqual(exit_code, 1, stream.getvalue())
        self.assertIn(
            "composer.lock の reference が Packagist の登録と合わない", stream.getvalue()
        )

    def test_missing_p2_reference_is_undeterminable(self):
        # 参考から直すもの5（開発部長の決定）: lock と p2 の双方に reference が
        # ある種類（source 同士・dist 同士）が1つも無いときは、version の
        # 文字列が一致しているだけで合格にせず判定不能にする。
        base_pkg = clf.PackageEntry(
            name="acme/norefp2",
            version="1.0.0",
            source_reference="aaa",
            dist_reference="aaa",
            notification_url=PACKAGIST_URL,
        )
        head_pkg = clf.PackageEntry(
            name="acme/norefp2",
            version="1.1.0",
            source_reference="bbb",
            dist_reference="bbb",
            notification_url=PACKAGIST_URL,
        )
        now = dt.datetime(2026, 9, 15, tzinfo=dt.timezone.utc)
        old_published = (now - dt.timedelta(days=30)).isoformat()

        def fetch(vendor, name):
            payload = {
                "minified": "composer/2.0",
                "packages": {
                    "acme/norefp2": [
                        {
                            "version": "1.1.0",
                            # source・dist が無い（p2 側に比べられる reference が無い）
                            "published-time": old_published,
                        }
                    ]
                },
            }
            return json.dumps(payload)

        reason, queried = clf.evaluate_package(
            base_pkg, head_pkg, now=now, min_age_days=7, fetch_json_text=fetch
        )
        self.assertTrue(queried)
        self.assertIsNotNone(reason)
        self.assertIn("reference を Packagist の登録と照らせない", reason.message)
        self.assertTrue(reason.versions_readable)

    def test_lock_source_only_p2_dist_only_is_undeterminable(self):
        # 前回の査読「直すとよい」1件目: lock は source しか無く、p2 は dist しか
        # 無い（種類がすれ違い、比べられる組み合わせが1つも無い）ときも判定不能に
        # なることを確かめる（既存の test_missing_p2_reference_is_undeterminable は
        # p2 側に一切無いケースなので、これとは別に確かめる）。
        base_pkg = clf.PackageEntry(
            name="acme/crossed",
            version="1.0.0",
            source_reference="base-src",
            dist_reference=None,
            notification_url=PACKAGIST_URL,
        )
        head_pkg = clf.PackageEntry(
            name="acme/crossed",
            version="1.1.0",
            source_reference="lock-src",  # lock は source だけ
            dist_reference=None,
            notification_url=PACKAGIST_URL,
        )
        now = dt.datetime(2026, 9, 15, tzinfo=dt.timezone.utc)
        old_published = (now - dt.timedelta(days=30)).isoformat()

        def fetch(vendor, name):
            payload = {
                "minified": "composer/2.0",
                "packages": {
                    "acme/crossed": [
                        {
                            "version": "1.1.0",
                            # p2 は dist だけ（source キーが無い）
                            "dist": {"reference": "p2-dist"},
                            "published-time": old_published,
                        }
                    ]
                },
            }
            return json.dumps(payload)

        reason, queried = clf.evaluate_package(
            base_pkg, head_pkg, now=now, min_age_days=7, fetch_json_text=fetch
        )
        self.assertTrue(queried)
        self.assertIsNotNone(reason)
        self.assertIn("reference を Packagist の登録と照らせない", reason.message)
        self.assertTrue(reason.versions_readable)

    def test_lock_has_no_reference_at_all_is_undeterminable(self):
        # 前回の査読「直すとよい」1件目: lock に source も dist も無い（composer.lock
        # の形としては許される書き方。parse_lock_packages は LockParseError に
        # しない）ときも、p2 側に source・dist が揃っていて version が一致していても
        # 判定不能になることを、main の全体の流れ（lock のパースから）で確かめる。
        base = {
            "packages": [
                {
                    "name": "acme/noref",
                    "version": "1.0.0",
                    "notification-url": PACKAGIST_URL,
                    # source も dist も無い
                }
            ],
            "packages-dev": [],
        }
        head = {
            "packages": [
                {
                    "name": "acme/noref",
                    "version": "1.1.0",
                    "notification-url": PACKAGIST_URL,
                    # source も dist も無い（lock の形として許される）
                }
            ],
            "packages-dev": [],
        }
        base_text = json.dumps(base)
        head_text = json.dumps(head)

        # parse_lock_packages が LockParseError を出さずに読めることを直接も確かめる。
        head_packages, head_duplicates = clf.parse_lock_packages(head_text)
        self.assertEqual(head_duplicates, set())
        self.assertIsNone(head_packages["acme/noref"].source_reference)
        self.assertIsNone(head_packages["acme/noref"].dist_reference)

        git_show, git_merge_base = make_git_fakes(
            "aaaa777", base_text, "bbbb777", head_text
        )

        def fetch(vendor, name):
            payload = {
                "minified": "composer/2.0",
                "packages": {
                    "acme/noref": [
                        {
                            "version": "1.1.0",
                            "source": {"reference": "p2-src"},
                            "dist": {"reference": "p2-dist"},
                            "published-time": "2020-01-01T00:00:00+00:00",
                        }
                    ]
                },
            }
            return json.dumps(payload)

        stream = io.StringIO()
        exit_code = clf.main(
            ["--base", "aaaa777", "--head", "bbbb777"],
            git_show=git_show,
            git_merge_base=git_merge_base,
            fetch_json_text=fetch,
            now_func=lambda: dt.datetime(2026, 9, 15, tzinfo=dt.timezone.utc),
            stream=stream,
        )
        output = stream.getvalue()
        self.assertEqual(exit_code, 1, output)
        self.assertIn(
            "reference を Packagist の登録と照らせない", output
        )

        stream = io.StringIO()
        exit_code = clf.main(
            ["--base", "aaaa777", "--head", "bbbb777", "--override"],
            git_show=git_show,
            git_merge_base=git_merge_base,
            fetch_json_text=fetch,
            now_func=lambda: dt.datetime(2026, 9, 15, tzinfo=dt.timezone.utc),
            stream=stream,
        )
        output = stream.getvalue()
        self.assertEqual(exit_code, 0, output)
        self.assertIn("::warning::", output)
        self.assertIn("reference を Packagist の登録と照らせない", output)

    def test_source_only_mismatch_is_detected(self):
        # 直すとよい7-3: dist は一致するが source だけ食い違う場合も検出する。
        base_pkg = clf.PackageEntry(
            name="acme/srconly",
            version="1.0.0",
            source_reference="aaa",
            dist_reference="same",
            notification_url=PACKAGIST_URL,
        )
        head_pkg = clf.PackageEntry(
            name="acme/srconly",
            version="1.0.0",
            source_reference="bbb",
            dist_reference="same",
            notification_url=PACKAGIST_URL,
        )

        def fetch(vendor, name):
            payload = {
                "minified": "composer/2.0",
                "packages": {
                    "acme/srconly": [
                        {
                            "version": "1.0.0",
                            "source": {"reference": "aaa"},
                            "dist": {"reference": "same"},
                            "published-time": "2020-01-01T00:00:00+00:00",
                        }
                    ]
                },
            }
            return json.dumps(payload)

        now = dt.datetime(2026, 9, 15, tzinfo=dt.timezone.utc)
        reason, queried = clf.evaluate_package(
            base_pkg, head_pkg, now=now, min_age_days=7, fetch_json_text=fetch
        )
        self.assertTrue(queried)
        self.assertIsNotNone(reason)
        self.assertIn(
            "composer.lock の reference が Packagist の登録と合わない", reason.message
        )

    def test_dist_only_mismatch_is_detected(self):
        # 直すとよい7-3: source は一致するが dist だけ食い違う場合も検出する。
        base_pkg = clf.PackageEntry(
            name="acme/distonly",
            version="1.0.0",
            source_reference="same",
            dist_reference="aaa",
            notification_url=PACKAGIST_URL,
        )
        head_pkg = clf.PackageEntry(
            name="acme/distonly",
            version="1.0.0",
            source_reference="same",
            dist_reference="bbb",
            notification_url=PACKAGIST_URL,
        )

        def fetch(vendor, name):
            payload = {
                "minified": "composer/2.0",
                "packages": {
                    "acme/distonly": [
                        {
                            "version": "1.0.0",
                            "source": {"reference": "same"},
                            "dist": {"reference": "aaa"},
                            "published-time": "2020-01-01T00:00:00+00:00",
                        }
                    ]
                },
            }
            return json.dumps(payload)

        now = dt.datetime(2026, 9, 15, tzinfo=dt.timezone.utc)
        reason, queried = clf.evaluate_package(
            base_pkg, head_pkg, now=now, min_age_days=7, fetch_json_text=fetch
        )
        self.assertTrue(queried)
        self.assertIsNotNone(reason)
        self.assertIn(
            "composer.lock の reference が Packagist の登録と合わない", reason.message
        )

    def test_one_comparable_pair_matching_passes_even_if_other_pair_missing(self):
        # 参考から直すもの5（開発部長の決定）: source は比べられないが dist は
        # 比べられて一致するときは、他が比べられなくても合格側になる。
        base_pkg = clf.PackageEntry(
            name="acme/distcomparableonly",
            version="1.0.0",
            source_reference=None,
            dist_reference="aaa",
            notification_url=PACKAGIST_URL,
        )
        head_pkg = clf.PackageEntry(
            name="acme/distcomparableonly",
            version="1.0.0",
            source_reference=None,
            dist_reference="aaa",
            notification_url=PACKAGIST_URL,
        )

        def fetch(vendor, name):
            payload = {
                "minified": "composer/2.0",
                "packages": {
                    "acme/distcomparableonly": [
                        {
                            "version": "1.0.0",
                            # p2 側にも source が無い（比べられない）が dist は一致する
                            "dist": {"reference": "aaa"},
                            "published-time": "2020-01-01T00:00:00+00:00",
                        }
                    ]
                },
            }
            return json.dumps(payload)

        now = dt.datetime(2026, 9, 15, tzinfo=dt.timezone.utc)
        reason, queried = clf.evaluate_package(
            base_pkg, head_pkg, now=now, min_age_days=7, fetch_json_text=fetch
        )
        self.assertTrue(queried)
        self.assertIsNone(reason, reason)


class P2MalformedResponseTests(unittest.TestCase):
    """直すとよい3. p2 の応答が想定の形でない（形が違う・published-time を読めない等）
    ときは判定不能にする。ただし、フェッチ層の不具合など想定外の例外
    （IncompleteRead・InvalidURL 等）はここで飲み込まず、外（main の受け口）まで
    そのまま伝える（このクラスの一部のテストはそれを確かめている）。
    """

    def _evaluate(self, fetch):
        base_pkg = clf.PackageEntry(
            name="acme/malformed",
            version="1.0.0",
            source_reference="aaa",
            dist_reference="aaa",
            notification_url=PACKAGIST_URL,
        )
        head_pkg = clf.PackageEntry(
            name="acme/malformed",
            version="1.1.0",
            source_reference="bbb",
            dist_reference="bbb",
            notification_url=PACKAGIST_URL,
        )
        now = dt.datetime(2026, 9, 15, tzinfo=dt.timezone.utc)
        return clf.evaluate_package(
            base_pkg, head_pkg, now=now, min_age_days=7, fetch_json_text=fetch
        )

    def test_null_response(self):
        reason, queried = self._evaluate(lambda v, n: "null")
        self.assertTrue(queried)
        self.assertIsNotNone(reason)
        self.assertIn("判定不能", reason.message)

    def test_empty_array_response(self):
        reason, queried = self._evaluate(lambda v, n: "[]")
        self.assertTrue(queried)
        self.assertIsNotNone(reason)
        self.assertIn("判定不能", reason.message)

    def test_packages_key_missing(self):
        reason, queried = self._evaluate(lambda v, n: json.dumps({}))
        self.assertTrue(queried)
        self.assertIsNotNone(reason)
        self.assertIn("判定不能", reason.message)

    def test_published_time_not_a_string(self):
        def fetch(vendor, name):
            payload = {
                "minified": "composer/2.0",
                "packages": {"acme/malformed": [{"version": "1.1.0", "published-time": 12345}]},
            }
            return json.dumps(payload)

        reason, queried = self._evaluate(fetch)
        self.assertTrue(queried)
        self.assertIsNotNone(reason)
        self.assertIn("判定不能", reason.message)

    def test_published_time_invalid_string(self):
        def fetch(vendor, name):
            payload = {
                "minified": "composer/2.0",
                "packages": {
                    "acme/malformed": [{"version": "1.1.0", "published-time": "not-a-date"}]
                },
            }
            return json.dumps(payload)

        reason, queried = self._evaluate(fetch)
        self.assertTrue(queried)
        self.assertIsNotNone(reason)
        self.assertIn("判定不能", reason.message)

    def test_incomplete_read_exception_propagates_as_unexpected_failure(self):
        # 必ず直す2: PackagistFetchError 以外の例外（フェッチ層のバグなど）は
        # もう evaluate_package の中で判定不能にせず、そのまま外へ伝える。
        # fetch_p2_json_text の既定実装は IncompleteRead を取り直したうえで
        # PackagistFetchError に包むので、ここに生の IncompleteRead が来るのは
        # フェッチ層自体の不具合であり、run_check・main まで素通しさせるべき。
        # （evaluate_package に広い except Exception を戻すとここで判定不能を
        # 返してしまい、この assertRaises が落ちる。）
        def fetch(vendor, name):
            raise http.client.IncompleteRead(b"partial")

        with self.assertRaises(http.client.IncompleteRead):
            self._evaluate(fetch)

    def test_unicode_decode_error(self):
        def fetch(vendor, name):
            raise UnicodeDecodeError("utf-8", b"\xff", 0, 1, "invalid start byte")

        reason, queried = self._evaluate(fetch)
        self.assertTrue(queried)
        self.assertIsNotNone(reason)
        self.assertIn("判定不能", reason.message)
        # 直すとよい7-1: UnicodeDecodeError は ValueError のサブクラスなので、
        # except ValueError より前に専用の except で受けていることを、
        # 「published-time を読めない」ではなく「p2 の応答を読めない」の文言で
        # 確かめる（except の順序を入れ替えると落ちる）。
        self.assertIn("p2 の応答を読めない", reason.message)

    def test_invalid_url_exception_propagates_as_unexpected_failure(self):
        # 必ず直す2: 上と同様、生の InvalidURL もフェッチ層の不具合として
        # 外へ伝える。
        def fetch(vendor, name):
            raise http.client.InvalidURL("bad url")

        with self.assertRaises(http.client.InvalidURL):
            self._evaluate(fetch)

    def test_minified_versions_element_not_a_dict(self):
        # 必ず直す2: expand_minified_versions に渡る前に要素が辞書かどうかを
        # 確かめる（確かめないと非辞書の要素で AttributeError になり、
        # 広い except を外した今は判定不能にならず不具合として外に伝わってしまう）。
        def fetch(vendor, name):
            payload = {
                "minified": "composer/2.0",
                "packages": {"acme/malformed": ["not-a-dict"]},
            }
            return json.dumps(payload)

        reason, queried = self._evaluate(fetch)
        self.assertTrue(queried)
        self.assertIsNotNone(reason)
        self.assertIn("判定不能: p2 の応答の形が想定と違う", reason.message)


class AnnotationEscapeTests(unittest.TestCase):
    """直すとよい4. 注記のエスケープ: 改行・% を含む値で偽の注記が混ざらないこと。"""

    def test_newline_and_percent_are_escaped_in_annotation(self):
        reason = clf.Reason(
            name="acme/evil",
            from_version="1.0.0",
            to_version="1.0.0\n::notice::pwned",
            message="100% broken\r\ninjected",
        )
        stream = io.StringIO()
        clf.emit_reason(reason, override=False, stream=stream)
        output = stream.getvalue()

        # print() が付ける末尾の改行1つだけが実際の改行として残るはず
        self.assertEqual(output.count("\n"), 1)
        self.assertTrue(output.startswith("::error::"))
        self.assertNotIn("\n::notice::", output)
        self.assertIn("%0A", output)
        self.assertIn("100%25", output)
        self.assertIn("%0D%0Ainjected", output)

    def test_summary_line_is_escaped_too(self):
        reason = clf.Reason(
            name="acme/evil2",
            from_version=None,
            to_version="1.0.0",
            message="broken\n::error::fake",
        )
        line = clf.format_reason_summary(reason)
        self.assertNotIn("\n", line)
        self.assertIn("%0A", line)


class FetchP2JsonTextTests(unittest.TestCase):
    """取り直し: urlopen を差し替えて確かめる。"""

    def test_4xx_is_not_retried(self):
        calls = []

        def fake_urlopen(request, timeout=None):
            calls.append(timeout)
            raise urllib.error.HTTPError(request.full_url, 404, "Not Found", {}, None)

        with mock.patch.object(clf.urllib.request, "urlopen", side_effect=fake_urlopen):
            with self.assertRaises(clf.PackagistFetchError):
                clf.fetch_p2_json_text(
                    "acme", "widget", sleep=lambda s: self.fail("4xx で待ってはいけない")
                )
        self.assertEqual(len(calls), 1)

    def test_5xx_is_retried_three_times_then_undeterminable(self):
        calls = []
        sleeps = []

        def fake_urlopen(request, timeout=None):
            calls.append(timeout)
            raise urllib.error.HTTPError(request.full_url, 503, "Service Unavailable", {}, None)

        with mock.patch.object(clf.urllib.request, "urlopen", side_effect=fake_urlopen):
            with self.assertRaises(clf.PackagistFetchError):
                clf.fetch_p2_json_text("acme", "widget", sleep=sleeps.append)
        self.assertEqual(len(calls), 3)
        self.assertEqual(sleeps, [2, 4])

    def test_timeout_is_retried_three_times_then_undeterminable(self):
        calls = []
        sleeps = []

        def fake_urlopen(request, timeout=None):
            calls.append(timeout)
            raise TimeoutError("timed out")

        with mock.patch.object(clf.urllib.request, "urlopen", side_effect=fake_urlopen):
            with self.assertRaises(clf.PackagistFetchError):
                clf.fetch_p2_json_text("acme", "widget", sleep=sleeps.append)
        self.assertEqual(len(calls), 3)
        self.assertEqual(sleeps, [2, 4])
        self.assertTrue(all(t == clf.HTTP_TIMEOUT_SECONDS for t in calls))

    def test_incomplete_read_is_retried_three_times_then_undeterminable(self):
        # 直すとよい7-4: IncompleteRead も取り直す。
        calls = []
        sleeps = []

        def fake_urlopen(request, timeout=None):
            calls.append(timeout)
            raise http.client.IncompleteRead(b"partial")

        with mock.patch.object(clf.urllib.request, "urlopen", side_effect=fake_urlopen):
            with self.assertRaises(clf.PackagistFetchError):
                clf.fetch_p2_json_text("acme", "widget", sleep=sleeps.append)
        self.assertEqual(len(calls), 3)
        self.assertEqual(sleeps, [2, 4])

    def test_bad_status_line_is_retried_three_times_then_undeterminable(self):
        # 直すとよい4: http.client.HTTPException の系統（IncompleteRead 以外の
        # BadStatusLine・LineTooLong 等）も取り直し、使い切ったら
        # PackagistFetchError(retryable=True) にする。
        calls = []
        sleeps = []

        def fake_urlopen(request, timeout=None):
            calls.append(timeout)
            raise http.client.BadStatusLine("garbage status line")

        with mock.patch.object(clf.urllib.request, "urlopen", side_effect=fake_urlopen):
            with self.assertRaises(clf.PackagistFetchError) as ctx:
                clf.fetch_p2_json_text("acme", "widget", sleep=sleeps.append)
        self.assertEqual(len(calls), 3)
        self.assertEqual(sleeps, [2, 4])
        self.assertTrue(ctx.exception.retryable)

    def test_line_too_long_is_retried_three_times_then_undeterminable(self):
        # 直すとよい4: LineTooLong も HTTPException の系統として取り直す。
        calls = []
        sleeps = []

        def fake_urlopen(request, timeout=None):
            calls.append(timeout)
            raise http.client.LineTooLong("header line")

        with mock.patch.object(clf.urllib.request, "urlopen", side_effect=fake_urlopen):
            with self.assertRaises(clf.PackagistFetchError) as ctx:
                clf.fetch_p2_json_text("acme", "widget", sleep=sleeps.append)
        self.assertEqual(len(calls), 3)
        self.assertEqual(sleeps, [2, 4])
        self.assertTrue(ctx.exception.retryable)

    def test_url_error_connection_failure_is_retried_three_times(self):
        # 直すとよい7-4: 接続の失敗（URLError）も取り直す。
        calls = []
        sleeps = []

        def fake_urlopen(request, timeout=None):
            calls.append(timeout)
            raise urllib.error.URLError("connection refused")

        with mock.patch.object(clf.urllib.request, "urlopen", side_effect=fake_urlopen):
            with self.assertRaises(clf.PackagistFetchError):
                clf.fetch_p2_json_text("acme", "widget", sleep=sleeps.append)
        self.assertEqual(len(calls), 3)
        self.assertEqual(sleeps, [2, 4])

    def test_499_is_not_retried(self):
        # 直すとよい7-4: 499 も 4xx なので取り直さない。
        calls = []

        def fake_urlopen(request, timeout=None):
            calls.append(timeout)
            raise urllib.error.HTTPError(request.full_url, 499, "Client Closed Request", {}, None)

        with mock.patch.object(clf.urllib.request, "urlopen", side_effect=fake_urlopen):
            with self.assertRaises(clf.PackagistFetchError):
                clf.fetch_p2_json_text(
                    "acme", "widget", sleep=lambda s: self.fail("499 で待ってはいけない")
                )
        self.assertEqual(len(calls), 1)

    def test_500_is_retried(self):
        # 直すとよい7-4: 500 は 5xx なので取り直す。
        calls = []
        sleeps = []

        def fake_urlopen(request, timeout=None):
            calls.append(timeout)
            raise urllib.error.HTTPError(request.full_url, 500, "Internal Server Error", {}, None)

        with mock.patch.object(clf.urllib.request, "urlopen", side_effect=fake_urlopen):
            with self.assertRaises(clf.PackagistFetchError):
                clf.fetch_p2_json_text("acme", "widget", sleep=sleeps.append)
        self.assertEqual(len(calls), 3)
        self.assertEqual(sleeps, [2, 4])

    def test_user_agent_header_and_timeout(self):
        captured = {}

        class _FakeResponse:
            def __enter__(self):
                return self

            def __exit__(self, *exc):
                return False

            def read(self):
                return b'{"ok": true}'

        def fake_urlopen(request, timeout=None):
            captured["user_agent"] = request.get_header("User-agent")
            captured["timeout"] = timeout
            return _FakeResponse()

        with mock.patch.object(clf.urllib.request, "urlopen", side_effect=fake_urlopen):
            result = clf.fetch_p2_json_text(
                "acme", "widget", sleep=lambda s: self.fail("不要な待ち")
            )

        self.assertEqual(result, '{"ok": true}')
        self.assertEqual(
            captured["user_agent"],
            "type89-dependency-freshness/1.0 (+https://github.com/yabutayukinari/type89)",
        )
        self.assertEqual(captured["timeout"], clf.HTTP_TIMEOUT_SECONDS)


class ArgValidationTests(unittest.TestCase):
    """参考から直すもの9・10. --min-age-days は1以上、--base/--head は7〜40桁の16進数のみ。"""

    def test_min_age_days_zero_is_argument_error(self):
        with contextlib.redirect_stderr(io.StringIO()):
            with self.assertRaises(SystemExit) as ctx:
                clf.build_arg_parser().parse_args(
                    ["--base", "0000000", "--head", "1111111", "--min-age-days", "0"]
                )
        self.assertEqual(ctx.exception.code, 2)

    def test_min_age_days_negative_is_argument_error(self):
        with contextlib.redirect_stderr(io.StringIO()):
            with self.assertRaises(SystemExit) as ctx:
                clf.build_arg_parser().parse_args(
                    ["--base", "0000000", "--head", "1111111", "--min-age-days", "-1"]
                )
        self.assertEqual(ctx.exception.code, 2)

    def test_min_age_days_one_is_accepted(self):
        args = clf.build_arg_parser().parse_args(
            ["--base", "0000000", "--head", "1111111", "--min-age-days", "1"]
        )
        self.assertEqual(args.min_age_days, 1)

    def test_base_not_hex_is_argument_error(self):
        with contextlib.redirect_stderr(io.StringIO()):
            with self.assertRaises(SystemExit) as ctx:
                clf.build_arg_parser().parse_args(["--base", "not-a-sha", "--head", "1111111"])
        self.assertEqual(ctx.exception.code, 2)

    def test_sha_too_short_is_argument_error(self):
        with contextlib.redirect_stderr(io.StringIO()):
            with self.assertRaises(SystemExit) as ctx:
                clf.build_arg_parser().parse_args(["--base", "abc12", "--head", "1111111"])
        self.assertEqual(ctx.exception.code, 2)

    def test_sha_too_long_is_argument_error(self):
        with contextlib.redirect_stderr(io.StringIO()):
            with self.assertRaises(SystemExit) as ctx:
                clf.build_arg_parser().parse_args(
                    ["--base", "a" * 41, "--head", "1111111"]
                )
        self.assertEqual(ctx.exception.code, 2)

    def test_sha_with_trailing_newline_is_argument_error(self):
        # 直すとよい7-5: `$` は文字列末尾の改行の直前にもマッチしてしまう
        # 正規表現の落とし穴があるが、`fullmatch` を使っているので
        # 「aaaaaaa\n」は 7桁ちょうどでも弾かれることを固定する。
        with contextlib.redirect_stderr(io.StringIO()):
            with self.assertRaises(SystemExit) as ctx:
                clf.build_arg_parser().parse_args(
                    ["--base", "aaaaaaa\n", "--head", "1111111"]
                )
        self.assertEqual(ctx.exception.code, 2)

    def test_head_dash_prefixed_is_argument_error(self):
        with contextlib.redirect_stderr(io.StringIO()):
            with self.assertRaises(SystemExit) as ctx:
                clf.build_arg_parser().parse_args(["--base", "0000000", "--head", "-rf"])
        self.assertEqual(ctx.exception.code, 2)

    def test_valid_sha_is_accepted(self):
        args = clf.build_arg_parser().parse_args(["--base", "511fa0b", "--head", "53a59b9"])
        self.assertEqual(args.base, "511fa0b")
        self.assertEqual(args.head, "53a59b9")


class UnexpectedFailureIsCaughtTests(unittest.TestCase):
    """必ず直す2・直すとよい6. run_check の except は LockParseError・GitCommandError
    （git の失敗の既定の実装が投げる専用の型）だけを受ける。それ以外
    （スクリプト自体の不具合、例えば ZeroDivisionError）は run_check では拾わず
    main の except まで素通しして ::error:: を出し、--override の有無によらず
    終了コード 1 のままになること。
    """

    def _run(self, *, override):
        def broken_git_show(ref):
            raise ZeroDivisionError("boom")

        def git_merge_base(base, head):
            return "MERGEBASE"

        argv = ["--base", "0000000", "--head", "1111111"]
        if override:
            argv.append("--override")
        stream = io.StringIO()
        exit_code = clf.main(
            argv,
            git_show=broken_git_show,
            git_merge_base=git_merge_base,
            fetch_json_text=lambda v, n: (_ for _ in ()).throw(AssertionError("no fetch")),
            now_func=lambda: dt.datetime(2026, 9, 15, tzinfo=dt.timezone.utc),
            stream=stream,
        )
        return exit_code, stream.getvalue()

    def test_unexpected_exception_reaches_main_except_without_override(self):
        exit_code, output = self._run(override=False)
        self.assertEqual(exit_code, 1)
        self.assertIn("::error::", output)
        # run_check の except（LockParseError・GitCommandError のみ）ではなく main の
        # except まで届いたことを、そこでだけ出る文言で確かめる。run_check だと
        # 「composer.lock を読めない」になるはず（この assertNotIn が、run_check の
        # except を元の except Exception に戻すと落ちる）。
        self.assertIn("check_lock_freshness.py の不具合で失敗した", output)
        self.assertIn("ZeroDivisionError", output)
        self.assertNotIn("composer.lock を読めない", output)

    def test_unexpected_exception_reaches_main_except_with_override(self):
        # --override が付いていても、判定結果の不合格ではなくスクリプト自体の
        # 不具合なので 1 のまま（override で 0 にならない）。main の except を
        # 削除・別の例外に差し替えると、この assertEqual(1) が落ちる。
        exit_code, output = self._run(override=True)
        self.assertEqual(exit_code, 1)
        self.assertIn("::error::", output)
        self.assertIn("check_lock_freshness.py の不具合で失敗した", output)


class BugInsideEvaluatePackagePropagatesTests(unittest.TestCase):
    """必ず直す2. evaluate_package 内で起きたスクリプト自体の不具合（例:
    expand_minified_versions の KeyError）は判定不能にせず、main の受け口まで
    素通しして --override の有無によらず終了コード 1 になる。
    """

    def test_expand_minified_versions_bug_propagates_and_override_stays_one(self):
        base = {
            "packages": [
                {
                    "name": "acme/buggy",
                    "version": "1.0.0",
                    "notification-url": PACKAGIST_URL,
                    "source": {"reference": "aaa"},
                    "dist": {"reference": "aaa"},
                }
            ],
            "packages-dev": [],
        }
        head = {
            "packages": [
                {
                    "name": "acme/buggy",
                    "version": "1.1.0",
                    "notification-url": PACKAGIST_URL,
                    "source": {"reference": "bbb"},
                    "dist": {"reference": "bbb"},
                }
            ],
            "packages-dev": [],
        }

        def fetch(vendor, name):
            payload = {
                "minified": "composer/2.0",
                "packages": {
                    "acme/buggy": [
                        {
                            "version": "1.1.0",
                            "source": {"reference": "bbb"},
                            "dist": {"reference": "bbb"},
                            "published-time": "2020-01-01T00:00:00+00:00",
                        }
                    ]
                },
            }
            return json.dumps(payload)

        git_show, git_merge_base = make_git_fakes(
            "faaa000", json.dumps(base), "fbbb000", json.dumps(head)
        )

        for override in (False, True):
            with self.subTest(override=override):
                argv = ["--base", "faaa000", "--head", "fbbb000"]
                if override:
                    argv.append("--override")
                stream = io.StringIO()
                with mock.patch.object(
                    clf, "expand_minified_versions", side_effect=KeyError("boom")
                ):
                    exit_code = clf.main(
                        argv,
                        git_show=git_show,
                        git_merge_base=git_merge_base,
                        fetch_json_text=fetch,
                        now_func=lambda: dt.datetime(2026, 9, 15, tzinfo=dt.timezone.utc),
                        stream=stream,
                    )
                output = stream.getvalue()
                # --override が付いていても、判定結果の不合格ではなくスクリプト
                # 自体の不具合なので 1 のまま（evaluate_package に広い except を
                # 戻すと判定不能・--override で 0 になり、この assertEqual が落ちる）。
                self.assertEqual(exit_code, 1, output)
                self.assertIn("check_lock_freshness.py の不具合で失敗した", output)
                self.assertIn("KeyError", output)

    def test_value_error_from_fetch_layer_propagates_and_override_stays_one(self):
        # 必ず直す1: except ValueError は parse_iso8601 の呼び出しだけを囲む。
        # fetch_json_text（取得の層）自体のバグで ValueError が出ても、
        # 「判定不能: published-time を読めない」に化けさせず、--override でも 1 になる。
        base = {
            "packages": [
                {
                    "name": "acme/fetchbug",
                    "version": "1.0.0",
                    "notification-url": PACKAGIST_URL,
                    "source": {"reference": "aaa"},
                    "dist": {"reference": "aaa"},
                }
            ],
            "packages-dev": [],
        }
        head = {
            "packages": [
                {
                    "name": "acme/fetchbug",
                    "version": "1.1.0",
                    "notification-url": PACKAGIST_URL,
                    "source": {"reference": "bbb"},
                    "dist": {"reference": "bbb"},
                }
            ],
            "packages-dev": [],
        }
        git_show, git_merge_base = make_git_fakes(
            "faaa111", json.dumps(base), "fbbb111", json.dumps(head)
        )

        def broken_fetch(vendor, name):
            raise ValueError("boom from fetch layer")

        for override in (False, True):
            with self.subTest(override=override):
                argv = ["--base", "faaa111", "--head", "fbbb111"]
                if override:
                    argv.append("--override")
                stream = io.StringIO()
                exit_code = clf.main(
                    argv,
                    git_show=git_show,
                    git_merge_base=git_merge_base,
                    fetch_json_text=broken_fetch,
                    now_func=lambda: dt.datetime(2026, 9, 15, tzinfo=dt.timezone.utc),
                    stream=stream,
                )
                output = stream.getvalue()
                self.assertEqual(exit_code, 1, output)
                self.assertIn("check_lock_freshness.py の不具合で失敗した", output)
                self.assertIn("ValueError", output)
                self.assertNotIn("published-time を読めない", output)

    def test_value_error_from_expand_propagates_and_override_stays_one(self):
        # 必ず直す1: 展開（expand_minified_versions）から出た ValueError も
        # 同様に外へ伝わり、--override でも 1 になる。
        base = {
            "packages": [
                {
                    "name": "acme/expandbug",
                    "version": "1.0.0",
                    "notification-url": PACKAGIST_URL,
                    "source": {"reference": "aaa"},
                    "dist": {"reference": "aaa"},
                }
            ],
            "packages-dev": [],
        }
        head = {
            "packages": [
                {
                    "name": "acme/expandbug",
                    "version": "1.1.0",
                    "notification-url": PACKAGIST_URL,
                    "source": {"reference": "bbb"},
                    "dist": {"reference": "bbb"},
                }
            ],
            "packages-dev": [],
        }

        def fetch(vendor, name):
            payload = {
                "minified": "composer/2.0",
                "packages": {
                    "acme/expandbug": [
                        {
                            "version": "1.1.0",
                            "source": {"reference": "bbb"},
                            "dist": {"reference": "bbb"},
                            "published-time": "2020-01-01T00:00:00+00:00",
                        }
                    ]
                },
            }
            return json.dumps(payload)

        git_show, git_merge_base = make_git_fakes(
            "faaa222", json.dumps(base), "fbbb222", json.dumps(head)
        )

        for override in (False, True):
            with self.subTest(override=override):
                argv = ["--base", "faaa222", "--head", "fbbb222"]
                if override:
                    argv.append("--override")
                stream = io.StringIO()
                with mock.patch.object(
                    clf, "expand_minified_versions", side_effect=ValueError("boom from expand")
                ):
                    exit_code = clf.main(
                        argv,
                        git_show=git_show,
                        git_merge_base=git_merge_base,
                        fetch_json_text=fetch,
                        now_func=lambda: dt.datetime(2026, 9, 15, tzinfo=dt.timezone.utc),
                        stream=stream,
                    )
                output = stream.getvalue()
                self.assertEqual(exit_code, 1, output)
                self.assertIn("check_lock_freshness.py の不具合で失敗した", output)
                self.assertIn("ValueError", output)
                self.assertNotIn("published-time を読めない", output)


class DeadlineAndCircuitBreakerTests(unittest.TestCase):
    """直すとよい6. 全体の締め切り（420秒）と、続けて3件失敗したときの打ち切り。
    どちらも判定不能として通常の流れ（--override なら警告で 0）に乗る。
    """

    def _make_lock_text(self, name_versions):
        packages = [
            {
                "name": name,
                "version": version,
                "notification-url": PACKAGIST_URL,
                "source": {"reference": f"ref-{name}-{version}"},
                "dist": {"reference": f"ref-{name}-{version}"},
            }
            for name, version in name_versions
        ]
        return json.dumps({"packages": packages, "packages-dev": []})

    def test_overall_deadline_marks_remaining_as_timeout_without_querying(self):
        names = ["acme/a", "acme/b", "acme/c"]
        base_text = self._make_lock_text([(n, "1.0.0") for n in names])
        head_text = self._make_lock_text([(n, "1.1.0") for n in names])
        git_show, git_merge_base = make_git_fakes("aaaa000", base_text, "bbbb000", head_text)

        calls: list[tuple[str, str]] = []

        def fetch(vendor, name):
            calls.append((vendor, name))
            full_name = f"{vendor}/{name}"
            payload = {
                "minified": "composer/2.0",
                "packages": {
                    full_name: [
                        {
                            "version": "1.1.0",
                            "source": {"reference": f"ref-{full_name}-1.1.0"},
                            "dist": {"reference": f"ref-{full_name}-1.1.0"},
                            "published-time": "2020-01-01T00:00:00+00:00",
                        }
                    ]
                },
            }
            return json.dumps(payload)

        # 時刻を取る関数を差し替える（実際には待たない）。締め切り計測の開始と
        # 1件目のパッケージの判定までは 0.0、それ以降はすべて締め切りを超えた
        # 500.0 を返す。
        call_count = [0]

        def fake_monotonic():
            call_count[0] += 1
            return 0.0 if call_count[0] <= 2 else 500.0

        stream = io.StringIO()
        exit_code = clf.main(
            ["--base", "aaaa000", "--head", "bbbb000"],
            git_show=git_show,
            git_merge_base=git_merge_base,
            fetch_json_text=fetch,
            now_func=lambda: dt.datetime(2026, 9, 15, tzinfo=dt.timezone.utc),
            monotonic=fake_monotonic,
            stream=stream,
        )
        output = stream.getvalue()
        self.assertEqual(exit_code, 1, output)
        self.assertEqual(len(calls), 1)
        self.assertIn("判定不能: 時間切れ", output)

    def test_overall_deadline_with_override_gives_warning_and_exit_zero(self):
        names = ["acme/a", "acme/b"]
        base_text = self._make_lock_text([(n, "1.0.0") for n in names])
        head_text = self._make_lock_text([(n, "1.1.0") for n in names])
        git_show, git_merge_base = make_git_fakes("aaaa111", base_text, "bbbb111", head_text)

        def fetch(vendor, name):
            raise AssertionError("締め切りを過ぎたパッケージは問い合わせないはず")

        call_count = [0]

        def fake_monotonic():
            # 最初の呼び出し（締め切りの起点）を 0.0 にし、以降はすべて締め切りを
            # 超えた 1000.0 を返す。
            call_count[0] += 1
            return 0.0 if call_count[0] == 1 else 1000.0

        stream = io.StringIO()
        exit_code = clf.main(
            ["--base", "aaaa111", "--head", "bbbb111", "--override"],
            git_show=git_show,
            git_merge_base=git_merge_base,
            fetch_json_text=fetch,
            now_func=lambda: dt.datetime(2026, 9, 15, tzinfo=dt.timezone.utc),
            monotonic=fake_monotonic,
            stream=stream,
        )
        output = stream.getvalue()
        self.assertEqual(exit_code, 0, output)
        self.assertIn("::warning::", output)
        self.assertNotIn("::error::", output)
        self.assertIn("判定不能: 時間切れ", output)

    def test_deadline_boundary_exactly_420_seconds_does_not_timeout(self):
        # 直すとよい7-5: ちょうど締め切り（420秒）ぴったりのときの扱いを固定する。
        # 今の実装は `>`（厳密に超えたときだけ時間切れ）なので、ちょうど 420 秒
        # 経過では時間切れにならず、通常どおり問い合わせて合格になる。
        names = ["acme/a"]
        base_text = self._make_lock_text([(n, "1.0.0") for n in names])
        head_text = self._make_lock_text([(n, "1.1.0") for n in names])
        git_show, git_merge_base = make_git_fakes("dddd000", base_text, "eeee000", head_text)

        def fetch(vendor, name):
            full_name = f"{vendor}/{name}"
            payload = {
                "minified": "composer/2.0",
                "packages": {
                    full_name: [
                        {
                            "version": "1.1.0",
                            "source": {"reference": f"ref-{full_name}-1.1.0"},
                            "dist": {"reference": f"ref-{full_name}-1.1.0"},
                            "published-time": "2020-01-01T00:00:00+00:00",
                        }
                    ]
                },
            }
            return json.dumps(payload)

        call_count = [0]

        def fake_monotonic():
            call_count[0] += 1
            # 1回目（締め切りの起点）は 0.0、2回目（唯一のパッケージの締め切り
            # 判定）はちょうど HTTP_OVERALL_DEADLINE_SECONDS。
            return 0.0 if call_count[0] == 1 else clf.HTTP_OVERALL_DEADLINE_SECONDS

        stream = io.StringIO()
        exit_code = clf.main(
            ["--base", "dddd000", "--head", "eeee000"],
            git_show=git_show,
            git_merge_base=git_merge_base,
            fetch_json_text=fetch,
            now_func=lambda: dt.datetime(2026, 9, 15, tzinfo=dt.timezone.utc),
            monotonic=fake_monotonic,
            stream=stream,
        )
        output = stream.getvalue()
        self.assertEqual(exit_code, 0, output)
        self.assertNotIn("判定不能: 時間切れ", output)

    def test_consecutive_fetch_failures_triggers_circuit_breaker(self):
        names = ["acme/a", "acme/b", "acme/c", "acme/d", "acme/e"]
        base_text = self._make_lock_text([(n, "1.0.0") for n in names])
        head_text = self._make_lock_text([(n, "1.1.0") for n in names])
        git_show, git_merge_base = make_git_fakes("cccc000", base_text, "dddd000", head_text)

        calls: list[tuple[str, str]] = []

        def fetch(vendor, name):
            calls.append((vendor, name))
            raise clf.PackagistFetchError(
                "タイムアウトなどで取得できなかった (...)", retryable=True
            )

        stream = io.StringIO()
        exit_code = clf.main(
            ["--base", "cccc000", "--head", "dddd000"],
            git_show=git_show,
            git_merge_base=git_merge_base,
            fetch_json_text=fetch,
            now_func=lambda: dt.datetime(2026, 9, 15, tzinfo=dt.timezone.utc),
            stream=stream,
        )
        output = stream.getvalue()
        self.assertEqual(exit_code, 1, output)
        # acme/a・acme/b・acme/c の3件で問い合わせの失敗が続き、acme/d・acme/e は
        # 問い合わせずに打ち切られる
        self.assertEqual(len(calls), 3)
        self.assertIn("Packagist への問い合わせが続けて失敗したため打ち切り", output)

    def test_consecutive_fetch_failures_with_override_gives_warning_and_exit_zero(self):
        names = ["acme/a", "acme/b", "acme/c", "acme/d"]
        base_text = self._make_lock_text([(n, "1.0.0") for n in names])
        head_text = self._make_lock_text([(n, "1.1.0") for n in names])
        git_show, git_merge_base = make_git_fakes("cccc111", base_text, "dddd111", head_text)

        calls: list[tuple[str, str]] = []

        def fetch(vendor, name):
            calls.append((vendor, name))
            raise clf.PackagistFetchError(
                "タイムアウトなどで取得できなかった (...)", retryable=True
            )

        stream = io.StringIO()
        exit_code = clf.main(
            ["--base", "cccc111", "--head", "dddd111", "--override"],
            git_show=git_show,
            git_merge_base=git_merge_base,
            fetch_json_text=fetch,
            now_func=lambda: dt.datetime(2026, 9, 15, tzinfo=dt.timezone.utc),
            stream=stream,
        )
        output = stream.getvalue()
        self.assertEqual(exit_code, 0, output)
        self.assertEqual(len(calls), 3)
        self.assertIn("::warning::", output)
        self.assertNotIn("::error::", output)
        self.assertIn("Packagist への問い合わせが続けて失敗したため打ち切り", output)

    def test_consecutive_failures_interleaved_with_non_queried_trips_after_third(self):
        # 直すとよい5: 失敗・失敗・beta（問い合わせない）・失敗 の順。beta は
        # 問い合わせないのでカウンタを動かさず、4件目（実質3件目の失敗）で
        # 連続失敗が3件になり、以降は問い合わせずに打ち切られる。
        names = ["acme/a", "acme/b", "acme/c", "acme/d", "acme/e"]
        base_text = self._make_lock_text([(n, "1.0.0") for n in names])
        head_versions = [
            ("acme/a", "1.1.0"),
            ("acme/b", "1.1.0"),
            ("acme/c", "1.0.0-beta1"),
            ("acme/d", "1.1.0"),
            ("acme/e", "1.1.0"),
        ]
        head_text = json.dumps(
            {
                "packages": [
                    {
                        "name": name,
                        "version": version,
                        "notification-url": PACKAGIST_URL,
                        "source": {"reference": f"ref-{name}-{version}"},
                        "dist": {"reference": f"ref-{name}-{version}"},
                    }
                    for name, version in head_versions
                ],
                "packages-dev": [],
            }
        )
        git_show, git_merge_base = make_git_fakes("caaa000", base_text, "cbbb000", head_text)

        calls: list[tuple[str, str]] = []

        def fetch(vendor, name):
            calls.append((vendor, name))
            raise clf.PackagistFetchError(
                "タイムアウトなどで取得できなかった (...)", retryable=True
            )

        stream = io.StringIO()
        exit_code = clf.main(
            ["--base", "caaa000", "--head", "cbbb000"],
            git_show=git_show,
            git_merge_base=git_merge_base,
            fetch_json_text=fetch,
            now_func=lambda: dt.datetime(2026, 9, 15, tzinfo=dt.timezone.utc),
            stream=stream,
        )
        output = stream.getvalue()
        self.assertEqual(exit_code, 1, output)
        # a・b・d の3件だけ問い合わせる（c は beta で問い合わせない、e は打ち切り）。
        self.assertEqual(calls, [("acme", "a"), ("acme", "b"), ("acme", "d")])
        self.assertIn("Packagist への問い合わせが続けて失敗したため打ち切り", output)

    def test_consecutive_failures_reset_by_success_does_not_trip(self):
        # 直すとよい5: 失敗・成功・失敗・失敗 の順。途中の成功でカウンタが
        # 0 に戻るので、4件すべて問い合わせて打ち切られない。
        names = ["acme/a", "acme/b", "acme/c", "acme/d"]
        base_text = self._make_lock_text([(n, "1.0.0") for n in names])
        head_text = self._make_lock_text([(n, "1.1.0") for n in names])
        git_show, git_merge_base = make_git_fakes("caaa111", base_text, "cbbb111", head_text)

        calls: list[tuple[str, str]] = []

        def fetch(vendor, name):
            calls.append((vendor, name))
            if name == "b":
                full_name = f"{vendor}/{name}"
                payload = {
                    "minified": "composer/2.0",
                    "packages": {
                        full_name: [
                            {
                                "version": "1.1.0",
                                "source": {"reference": f"ref-{full_name}-1.1.0"},
                                "dist": {"reference": f"ref-{full_name}-1.1.0"},
                                "published-time": "2020-01-01T00:00:00+00:00",
                            }
                        ]
                    },
                }
                return json.dumps(payload)
            raise clf.PackagistFetchError(
                "タイムアウトなどで取得できなかった (...)", retryable=True
            )

        stream = io.StringIO()
        exit_code = clf.main(
            ["--base", "caaa111", "--head", "cbbb111"],
            git_show=git_show,
            git_merge_base=git_merge_base,
            fetch_json_text=fetch,
            now_func=lambda: dt.datetime(2026, 9, 15, tzinfo=dt.timezone.utc),
            stream=stream,
        )
        output = stream.getvalue()
        self.assertEqual(exit_code, 1, output)
        # 4件すべて問い合わせる（途中の成功でカウンタがリセットされ打ち切られない）。
        self.assertEqual(len(calls), 4)
        self.assertNotIn("続けて失敗したため打ち切り", output)

    def test_consecutive_404_failures_do_not_trip_circuit_breaker(self):
        # 直すとよい5: 4xx（即時失敗、取り直しを使い切ったわけではない）は
        # 何件続いても連続失敗として数えない。
        names = ["acme/a", "acme/b", "acme/c", "acme/d"]
        base_text = self._make_lock_text([(n, "1.0.0") for n in names])
        head_text = self._make_lock_text([(n, "1.1.0") for n in names])
        git_show, git_merge_base = make_git_fakes("caaa222", base_text, "cbbb222", head_text)

        calls: list[tuple[str, str]] = []

        def fetch(vendor, name):
            calls.append((vendor, name))
            raise clf.PackagistFetchError("HTTP エラー 404")  # retryable=False（既定）

        stream = io.StringIO()
        exit_code = clf.main(
            ["--base", "caaa222", "--head", "cbbb222"],
            git_show=git_show,
            git_merge_base=git_merge_base,
            fetch_json_text=fetch,
            now_func=lambda: dt.datetime(2026, 9, 15, tzinfo=dt.timezone.utc),
            stream=stream,
        )
        output = stream.getvalue()
        self.assertEqual(exit_code, 1, output)
        self.assertEqual(len(calls), 4)
        self.assertNotIn("続けて失敗したため打ち切り", output)

    def test_404_between_exhausted_failures_does_not_reset_counter(self):
        # 差し戻し2: 「使い切り、使い切り、404、使い切り」の順。404 は数えも
        # 戻しもしない（据え置き）ので、連続失敗は a(1)→b(2)→c(404, 据え置き=2)
        # →d(3) で3件になり、d の後で打ち切られる（e は問い合わせない）。
        names = ["acme/a", "acme/b", "acme/c", "acme/d", "acme/e"]
        base_text = self._make_lock_text([(n, "1.0.0") for n in names])
        head_text = self._make_lock_text([(n, "1.1.0") for n in names])
        git_show, git_merge_base = make_git_fakes("caaa333", base_text, "cbbb333", head_text)

        calls: list[tuple[str, str]] = []

        def fetch(vendor, name):
            calls.append((vendor, name))
            if name == "c":
                raise clf.PackagistFetchError("HTTP エラー 404")  # retryable=False（既定）
            raise clf.PackagistFetchError(
                "タイムアウトなどで取得できなかった (...)", retryable=True
            )

        stream = io.StringIO()
        exit_code = clf.main(
            ["--base", "caaa333", "--head", "cbbb333"],
            git_show=git_show,
            git_merge_base=git_merge_base,
            fetch_json_text=fetch,
            now_func=lambda: dt.datetime(2026, 9, 15, tzinfo=dt.timezone.utc),
            stream=stream,
        )
        output = stream.getvalue()
        self.assertEqual(exit_code, 1, output)
        # a・b・c・d の4件だけ問い合わせる（e は打ち切り）。
        self.assertEqual(calls, [("acme", "a"), ("acme", "b"), ("acme", "c"), ("acme", "d")])
        self.assertIn("Packagist への問い合わせが続けて失敗したため打ち切り", output)

    def test_broken_json_between_exhausted_failures_does_not_reset_counter(self):
        # 差し戻し2: 「使い切り、使い切り、壊れた JSON、使い切り」でも同じ挙動。
        # 壊れた JSON も版一覧にたどり着く前の失敗なので、数えも戻しもしない。
        names = ["acme/a", "acme/b", "acme/c", "acme/d", "acme/e"]
        base_text = self._make_lock_text([(n, "1.0.0") for n in names])
        head_text = self._make_lock_text([(n, "1.1.0") for n in names])
        git_show, git_merge_base = make_git_fakes("caaa444", base_text, "cbbb444", head_text)

        calls: list[tuple[str, str]] = []

        def fetch(vendor, name):
            calls.append((vendor, name))
            if name == "c":
                return "{not valid json"
            raise clf.PackagistFetchError(
                "タイムアウトなどで取得できなかった (...)", retryable=True
            )

        stream = io.StringIO()
        exit_code = clf.main(
            ["--base", "caaa444", "--head", "cbbb444"],
            git_show=git_show,
            git_merge_base=git_merge_base,
            fetch_json_text=fetch,
            now_func=lambda: dt.datetime(2026, 9, 15, tzinfo=dt.timezone.utc),
            stream=stream,
        )
        output = stream.getvalue()
        self.assertEqual(exit_code, 1, output)
        self.assertEqual(calls, [("acme", "a"), ("acme", "b"), ("acme", "c"), ("acme", "d")])
        self.assertIn("Packagist への問い合わせが続けて失敗したため打ち切り", output)

    def test_success_between_exhausted_failures_resets_and_does_not_trip(self):
        # 差し戻し2: 「使い切り、使い切り、成功、使い切り、使い切り」では、成功で
        # カウンタが 0 に戻るので5件すべて問い合わせて打ち切られない。
        names = ["acme/a", "acme/b", "acme/c", "acme/d", "acme/e"]
        base_text = self._make_lock_text([(n, "1.0.0") for n in names])
        head_text = self._make_lock_text([(n, "1.1.0") for n in names])
        git_show, git_merge_base = make_git_fakes("caaa555", base_text, "cbbb555", head_text)

        calls: list[tuple[str, str]] = []

        def fetch(vendor, name):
            calls.append((vendor, name))
            if name == "c":
                full_name = f"{vendor}/{name}"
                payload = {
                    "minified": "composer/2.0",
                    "packages": {
                        full_name: [
                            {
                                "version": "1.1.0",
                                "source": {"reference": f"ref-{full_name}-1.1.0"},
                                "dist": {"reference": f"ref-{full_name}-1.1.0"},
                                "published-time": "2020-01-01T00:00:00+00:00",
                            }
                        ]
                    },
                }
                return json.dumps(payload)
            raise clf.PackagistFetchError(
                "タイムアウトなどで取得できなかった (...)", retryable=True
            )

        stream = io.StringIO()
        exit_code = clf.main(
            ["--base", "caaa555", "--head", "cbbb555"],
            git_show=git_show,
            git_merge_base=git_merge_base,
            fetch_json_text=fetch,
            now_func=lambda: dt.datetime(2026, 9, 15, tzinfo=dt.timezone.utc),
            stream=stream,
        )
        output = stream.getvalue()
        self.assertEqual(exit_code, 1, output)
        self.assertEqual(len(calls), 5)
        self.assertNotIn("続けて失敗したため打ち切り", output)


    def test_recent_publish_rejection_between_exhausted_failures_resets_counter(self):
        # 前回の査読「直すとよい」2件目: 「使い切り、使い切り、7日未満の不合格
        # （版一覧は読めている）、使い切り、使い切り」の順。0 に戻すのは
        # p2 の応答を取得でき、JSON として読めて、版一覧が読めたときだけで、
        # そのうえでの不合格（公開から7日未満）でも versions_readable=True
        # なので 0 に戻る。5件すべて問い合わせて打ち切られない。
        names = ["acme/a", "acme/b", "acme/c", "acme/d", "acme/e"]
        base_text = self._make_lock_text([(n, "1.0.0") for n in names])
        head_text = self._make_lock_text([(n, "1.1.0") for n in names])
        git_show, git_merge_base = make_git_fakes("caaa666", base_text, "cbbb666", head_text)

        now = dt.datetime(2026, 9, 15, tzinfo=dt.timezone.utc)
        recent_published = (now - dt.timedelta(days=2)).isoformat()

        calls: list[tuple[str, str]] = []

        def fetch(vendor, name):
            calls.append((vendor, name))
            if name == "c":
                full_name = f"{vendor}/{name}"
                payload = {
                    "minified": "composer/2.0",
                    "packages": {
                        full_name: [
                            {
                                "version": "1.1.0",
                                "source": {"reference": f"ref-{full_name}-1.1.0"},
                                "dist": {"reference": f"ref-{full_name}-1.1.0"},
                                # 版一覧・reference までは読めるが、公開から7日未満で不合格
                                "published-time": recent_published,
                            }
                        ]
                    },
                }
                return json.dumps(payload)
            raise clf.PackagistFetchError(
                "タイムアウトなどで取得できなかった (...)", retryable=True
            )

        stream = io.StringIO()
        exit_code = clf.main(
            ["--base", "caaa666", "--head", "cbbb666"],
            git_show=git_show,
            git_merge_base=git_merge_base,
            fetch_json_text=fetch,
            now_func=lambda: now,
            stream=stream,
        )
        output = stream.getvalue()
        self.assertEqual(exit_code, 1, output)
        # 5件すべて問い合わせる（c の不合格でカウンタがリセットされ打ち切られない）。
        self.assertEqual(len(calls), 5)
        self.assertNotIn("続けて失敗したため打ち切り", output)
        self.assertIn("日未満のため不合格", output)

    def test_response_shape_difference_between_exhausted_failures_does_not_reset_counter(self):
        # 前回の査読「直すとよい」2件目: 「使い切り、使い切り、応答の形の違い
        # （packages が無い＝版一覧にたどり着けていない）、使い切り」の順。
        # 応答の形の違いは数えも戻しもしない（据え置き）ので、
        # a(1)→b(2)→c(形の違い、据え置き=2)→d(3) で3件になり、d の後で打ち切られる
        # （e は問い合わせない）。
        names = ["acme/a", "acme/b", "acme/c", "acme/d", "acme/e"]
        base_text = self._make_lock_text([(n, "1.0.0") for n in names])
        head_text = self._make_lock_text([(n, "1.1.0") for n in names])
        git_show, git_merge_base = make_git_fakes("caaa888", base_text, "cbbb888", head_text)

        calls: list[tuple[str, str]] = []

        def fetch(vendor, name):
            calls.append((vendor, name))
            if name == "c":
                # packages キーが無い＝版一覧より手前の「応答の形の違い」
                return json.dumps({})
            raise clf.PackagistFetchError(
                "タイムアウトなどで取得できなかった (...)", retryable=True
            )

        stream = io.StringIO()
        exit_code = clf.main(
            ["--base", "caaa888", "--head", "cbbb888"],
            git_show=git_show,
            git_merge_base=git_merge_base,
            fetch_json_text=fetch,
            now_func=lambda: dt.datetime(2026, 9, 15, tzinfo=dt.timezone.utc),
            stream=stream,
        )
        output = stream.getvalue()
        self.assertEqual(exit_code, 1, output)
        # a・b・c・d の4件だけ問い合わせる（e は打ち切り）。
        self.assertEqual(calls, [("acme", "a"), ("acme", "b"), ("acme", "c"), ("acme", "d")])
        self.assertIn("Packagist への問い合わせが続けて失敗したため打ち切り", output)


class RunCanaryTests(unittest.TestCase):
    """直すとよい7-6. run_canary: published-time の一致・不一致・取得の失敗、
    出力のエスケープ。
    """

    def _payload(self, full_name, version, published_time):
        return json.dumps(
            {
                "minified": "composer/2.0",
                "packages": {full_name: [{"version": version, "published-time": published_time}]},
            }
        )

    def test_all_match_returns_zero(self):
        def fetch(vendor, name):
            full_name = f"{vendor}/{name}"
            for check_name, version, expected in clf.CANARY_CHECKS:
                if check_name == full_name:
                    return self._payload(full_name, version, expected)
            raise AssertionError(f"想定外の canary 問い合わせ: {full_name}")

        stream = io.StringIO()
        exit_code = clf.run_canary(fetch_json_text=fetch, stream=stream)
        self.assertEqual(exit_code, 0)
        output = stream.getvalue()
        for check_name, version, expected in clf.CANARY_CHECKS:
            self.assertIn(f"canary: {check_name}@{version} OK ({expected})", output)

    def test_mismatch_returns_one(self):
        def fetch(vendor, name):
            full_name = f"{vendor}/{name}"
            check_name, version, expected = next(
                c for c in clf.CANARY_CHECKS if c[0] == full_name
            )
            if full_name == "ramsey/uuid":
                return self._payload(full_name, version, "2000-01-01T00:00:00+00:00")
            return self._payload(full_name, version, expected)

        stream = io.StringIO()
        exit_code = clf.run_canary(fetch_json_text=fetch, stream=stream)
        self.assertEqual(exit_code, 1)
        self.assertIn("published-time が一致しない", stream.getvalue())

    def test_fetch_failure_returns_one_and_reports(self):
        def fetch(vendor, name):
            raise clf.PackagistFetchError("HTTP エラー 404")

        stream = io.StringIO()
        exit_code = clf.run_canary(fetch_json_text=fetch, stream=stream)
        self.assertEqual(exit_code, 1)
        self.assertIn("の取得に失敗した", stream.getvalue())

    def test_output_is_escaped(self):
        def fetch(vendor, name):
            full_name = f"{vendor}/{name}"
            check_name, version, expected = next(
                c for c in clf.CANARY_CHECKS if c[0] == full_name
            )
            evil = "2000-01-01T00:00:00+00:00\n::notice::pwned% "
            return self._payload(full_name, version, evil)

        stream = io.StringIO()
        exit_code = clf.run_canary(fetch_json_text=fetch, stream=stream)
        self.assertEqual(exit_code, 1)
        output = stream.getvalue()
        self.assertNotIn("\n::notice::", output)
        self.assertIn("%0A", output)


if __name__ == "__main__":
    unittest.main()
