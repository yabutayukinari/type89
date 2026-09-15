#!/usr/bin/env python3
"""composer.lock の依存更新が公開直後でないか、安定版か、メジャーバージョンを
下げずに上げていないかを確かめる CI ゲート。

呼び方:
    check_lock_freshness.py --base <sha> --head <sha> [--min-age-days 7] [--override]
    check_lock_freshness.py --canary

終了コード 0 が合格、1 が不合格。判定不能も不合格として扱う。引数の誤り（argparse
が検出するもの）は 2 で終わる。--override を指定しても、判定結果ではなくこの
スクリプト自体の不具合による失敗（想定外の例外）は 1 のままになる。
リポジトリのルート（checkout 済み、履歴あり）で実行される前提。
標準ライブラリのみを使う（Python 3.12）。
"""

from __future__ import annotations

import argparse
import datetime as dt
import http.client
import json
import re
import subprocess
import sys
import time
import urllib.error
import urllib.request
from dataclasses import dataclass
from typing import Callable, Optional

USER_AGENT = "type89-dependency-freshness/1.0 (+https://github.com/yabutayukinari/type89)"
PACKAGIST_NOTIFICATION_URL = "https://packagist.org/downloads/"
DEFAULT_MIN_AGE_DAYS = 7
HTTP_TIMEOUT_SECONDS = 10
HTTP_MAX_ATTEMPTS = 3
# 取り直しの前に待つ秒数（1回目と2回目の失敗の後に、この順で待つ）
HTTP_RETRY_DELAYS_SECONDS: tuple[float, ...] = (2, 4)
# 問い合わせ全体（全パッケージ分）の締め切り（秒）。超えたら残りは問い合わせずに
# 「判定不能: 時間切れ」にする（timeout-minutes を大きく超えないようにするため）。
HTTP_OVERALL_DEADLINE_SECONDS = 420.0
# 問い合わせの失敗（取り直しを使い切った判定不能）がこの件数連続したら、残りは
# 問い合わせずに「判定不能: 続けて失敗したため打ち切り」にする。
CONSECUTIVE_FETCH_FAILURE_LIMIT = 3

# Composer のパッケージ名の規則（`vendor/name`）。合わない名前は Packagist に問い合わせない。
_COMPOSER_NAME_REGEX = re.compile(
    r"^[a-z0-9]([_.-]?[a-z0-9]+)*/[a-z0-9](([_.]|-{1,2})?[a-z0-9]+)*$"
)

# --base / --head に渡す sha（7〜40桁の16進数のみ）。`-` 始まりの値を git の
# オプションとして誤読させないための制約でもある。
_GIT_SHA_REGEX = re.compile(r"^[0-9a-fA-F]{7,40}$")

# --canary で使う既知の published-time（技術調査担当が実測した値と一致するはず）。
CANARY_CHECKS: tuple[tuple[str, str, str], ...] = (
    ("ramsey/uuid", "4.9.3", "2026-06-18T04:04:58+00:00"),
    ("laravel/framework", "v13.31.0", "2026-09-08T14:25:26+00:00"),
)


class PackagistFetchError(Exception):
    """p2 メタデータの取得に失敗したときに送出する。

    `retryable` が True のときは、5xx・タイムアウト・接続の失敗・
    `http.client.HTTPException`（`IncompleteRead`・`BadStatusLine`・
    `LineTooLong` 等）で取り直しを HTTP_MAX_ATTEMPTS 回使い切った末の
    失敗（run_check の連続失敗カウンタが数える対象）。4xx による即時の失敗の
    ときは False（既定）。
    """

    def __init__(self, message: str, *, retryable: bool = False) -> None:
        super().__init__(message)
        self.retryable = retryable


class LockParseError(Exception):
    """composer.lock を読めない（JSON が壊れている・形が想定と違う）ときに送出する。"""


class GitCommandError(Exception):
    """`git show`・`git merge-base` の既定の実装が失敗したときに送出する。"""


@dataclass(frozen=True)
class PackageEntry:
    name: str
    version: str
    source_reference: Optional[str]
    dist_reference: Optional[str]
    notification_url: Optional[str]


@dataclass(frozen=True)
class Reason:
    name: str
    from_version: Optional[str]
    to_version: str
    message: str
    # 取り直しを使い切った問い合わせの失敗（5xx・タイムアウト・接続の失敗・
    # HTTPException で HTTP_MAX_ATTEMPTS 回失敗した）かどうかの目印。
    # run_check の連続失敗カウンタは、文言の部分一致ではなくこのフラグで数える。
    retry_exhausted_fetch_failure: bool = False
    # p2 の応答を取得でき、JSON として読めて、そのパッケージの版一覧（minified の
    # ときは展開した後）まで読めたかどうかの目印。True のときだけ run_check の
    # 連続失敗カウンタを 0 に戻す（4xx・壊れた JSON・応答の形の違いなど、版一覧に
    # たどり着く前の失敗では、数えも戻しもしない）。
    versions_readable: bool = False


FetchJsonText = Callable[[str, str], str]
GitShow = Callable[[str], str]
GitMergeBase = Callable[[str, str], str]
NowFunc = Callable[[], dt.datetime]
SleepFunc = Callable[[float], None]
MonotonicFunc = Callable[[], float]


# ---------------------------------------------------------------------------
# git 連携（テストでは差し替える）
# ---------------------------------------------------------------------------


def run_git_show(ref_and_path: str) -> str:
    """`git show <sha>:composer.lock` を読む既定の実装。

    stdout は bytes のまま受け取り、UTF-8 として decode する。composer.lock が
    UTF-8 でなく decode に失敗したときは、GitCommandError（git 自体の失敗）や
    素の UnicodeDecodeError（スクリプトの不具合として main まで素通しされる）
    ではなく、LockParseError（composer.lock を読めない・判定不能）にする。
    """
    completed = subprocess.run(
        ["git", "show", ref_and_path],
        capture_output=True,
    )
    if completed.returncode != 0:
        stderr_text = completed.stderr.decode("utf-8", errors="replace")
        raise GitCommandError(
            f"git show {ref_and_path} に失敗した: {stderr_text.strip()}"
        )
    try:
        return completed.stdout.decode("utf-8")
    except UnicodeDecodeError as exc:
        raise LockParseError(
            f"composer.lock を UTF-8 として読めない ({exc})"
        ) from exc


def run_git_merge_base(base: str, head: str) -> str:
    """`git merge-base <base> <head>` を読む既定の実装。"""
    completed = subprocess.run(
        ["git", "merge-base", base, head],
        capture_output=True,
        text=True,
    )
    if completed.returncode != 0:
        raise GitCommandError(
            f"git merge-base {base} {head} に失敗した: {completed.stderr.strip()}"
        )
    return completed.stdout.strip()


# ---------------------------------------------------------------------------
# composer.lock の読み取りと、動いたパッケージの抽出
# ---------------------------------------------------------------------------


def parse_lock_packages(lock_text: str) -> tuple[dict[str, PackageEntry], set[str]]:
    """composer.lock のテキストから packages・packages-dev をまとめて読む。

    戻り値は (名前をキーにした辞書, 名前が2回以上出たパッケージ名の集合)。
    後者が空でない名前は、`packages` と `packages-dev` の区分をまたいでも・
    同じ区分の中でも、同じ名前が複数の項目を持つ（＝どの版が実際に使われるか
    この lock だけからは決められない）ことを示す。

    JSON が壊れている、トップレベルがオブジェクトでない、`packages`・
    `packages-dev` のどちらも無い、値が配列でない、項目がオブジェクトでない、
    のいずれかであれば LockParseError を送出する。
    """
    try:
        data = json.loads(lock_text)
    except json.JSONDecodeError as exc:
        raise LockParseError(f"JSON として壊れている ({exc})") from exc

    if not isinstance(data, dict):
        raise LockParseError("トップレベルがオブジェクトでない")

    if "packages" not in data and "packages-dev" not in data:
        raise LockParseError("packages・packages-dev のどちらも無い")

    packages: dict[str, PackageEntry] = {}
    seen_counts: dict[str, int] = {}
    for section in ("packages", "packages-dev"):
        if section not in data:
            # キーが無いときだけ「区分が無い」扱い（両方無ければ上で既に判定不能）。
            continue
        section_value = data[section]
        if not isinstance(section_value, list):
            # キーはあるが値が配列でない（null を含む）ときは形の違いとして判定不能。
            raise LockParseError(f"{section} が配列でない")
        for pkg in section_value:
            if not isinstance(pkg, dict):
                raise LockParseError(f"{section} の項目がオブジェクトでない")

            # name はキーが無い・null・空文字のいずれも、黙って飛ばさず判定不能にする
            # （合格として素通りさせないため）。
            if "name" not in pkg:
                raise LockParseError(f"{section} の項目に name が無い")
            name = pkg["name"]
            if not isinstance(name, str):
                raise LockParseError(f"{section} の項目の name が文字列でない")
            if not name:
                raise LockParseError(f"{section} の項目の name が空文字")

            # version もキーが無ければ黙って空文字扱いにせず判定不能にする
            # （合格として素通りさせないため。name の扱いと同じ）。
            if "version" not in pkg:
                raise LockParseError(f"{section} の項目に version が無い")
            version = pkg["version"]
            if not isinstance(version, str):
                raise LockParseError(f"{section} の項目の version が文字列でない")

            # source・dist はキーが無い／null なら「無い」扱い（reference も None）。
            # キーはあるが辞書でない、reference がある且つ文字列でないときだけ判定不能。
            source = pkg.get("source")
            if source is not None and not isinstance(source, dict):
                raise LockParseError(f"{section} の項目の source が辞書でない")
            source_reference = source.get("reference") if isinstance(source, dict) else None
            if source_reference is not None and not isinstance(source_reference, str):
                raise LockParseError(f"{section} の項目の source.reference が文字列でない")

            dist = pkg.get("dist")
            if dist is not None and not isinstance(dist, dict):
                raise LockParseError(f"{section} の項目の dist が辞書でない")
            dist_reference = dist.get("reference") if isinstance(dist, dict) else None
            if dist_reference is not None and not isinstance(dist_reference, str):
                raise LockParseError(f"{section} の項目の dist.reference が文字列でない")

            seen_counts[name] = seen_counts.get(name, 0) + 1
            packages[name] = PackageEntry(
                name=name,
                version=version,
                source_reference=source_reference,
                dist_reference=dist_reference,
                notification_url=pkg.get("notification-url"),
            )

    duplicate_names = {name for name, count in seen_counts.items() if count > 1}
    return packages, duplicate_names


def find_moved_packages(
    base_packages: dict[str, PackageEntry],
    head_packages: dict[str, PackageEntry],
) -> list[tuple[Optional[PackageEntry], PackageEntry]]:
    """名前ごとに、新しく入った／version が変わった／reference が変わったものを取り出す。

    消えたパッケージは合格（対象にしない）。
    """
    moved: list[tuple[Optional[PackageEntry], PackageEntry]] = []
    for name in sorted(head_packages):
        head_pkg = head_packages[name]
        base_pkg = base_packages.get(name)
        if base_pkg is None:
            moved.append((None, head_pkg))
            continue
        if (
            base_pkg.version != head_pkg.version
            or base_pkg.source_reference != head_pkg.source_reference
            or base_pkg.dist_reference != head_pkg.dist_reference
        ):
            moved.append((base_pkg, head_pkg))
    return moved


# ---------------------------------------------------------------------------
# 安定度の判定
# 出典: composer/semver 3.4.4, vendor/composer/semver/src/VersionParser.php
#       Composer\Semver\VersionParser::parseStability() を移した。
# ---------------------------------------------------------------------------

# 元の PHP の '*+'（所有量指定子）は Python 3.11 以降の re でも使えるが、
# ここではあえて使わず通常の '*' にしている（バックトラックの挙動は変わるが、
# このパターンでは結果に影響しない）。
_MODIFIER_REGEX = (
    r"[._-]?(?:(stable|beta|b|RC|alpha|a|patch|pl|p)((?:[.-]?\d+)*)?)?([.-]?dev)?"
)
_STABILITY_TAIL_REGEX = re.compile(_MODIFIER_REGEX + r"(?:\+.*)?$", re.IGNORECASE)


def parse_stability(version: str) -> str:
    """version 文字列から安定度を返す（'stable'|'RC'|'beta'|'alpha'|'dev'）。"""
    version = re.sub(r"#.+$", "", version)

    if version.startswith("dev-") or version[-4:] == "-dev":
        return "dev"

    match = _STABILITY_TAIL_REGEX.search(version.lower())

    if match and match.group(3):
        return "dev"

    if match and match.group(1):
        token = match.group(1)
        if token in ("beta", "b"):
            return "beta"
        if token in ("alpha", "a"):
            return "alpha"
        if token == "rc":
            return "RC"

    return "stable"


_STABILITY_LABEL = {"dev": "dev", "alpha": "alpha", "beta": "beta", "RC": "RC"}


# ---------------------------------------------------------------------------
# メジャーバージョンの判定
# ---------------------------------------------------------------------------

_LEADING_NUMERIC_VERSION = re.compile(r"^v?(\d+)(?:\.(\d+))?", re.IGNORECASE)


def parse_major_minor(version: str) -> Optional[tuple[int, int]]:
    """先頭の `v` を無視し、先頭の major・minor を数値で返す。比べられないときは None。"""
    match = _LEADING_NUMERIC_VERSION.match(version.strip())
    if not match:
        return None
    major = int(match.group(1))
    minor = int(match.group(2)) if match.group(2) else 0
    return major, minor


def major_bump_rejected(base_version: str, head_version: str) -> bool:
    """メジャー（1.x 以上は major、0.x は minor）が上がっていれば True。

    片方が比べられない（dev 版など）ときは False（判定を飛ばす）。
    下げのときも False（合格）。
    """
    # dev 版（例: "4.x-dev"）は数字の先頭部分だけなら正規表現に部分一致してしまうことがあるが、
    # 版として比べられないので、安定度が dev のときは判定を飛ばす。
    if parse_stability(base_version) == "dev" or parse_stability(head_version) == "dev":
        return False

    base_parsed = parse_major_minor(base_version)
    head_parsed = parse_major_minor(head_version)
    if base_parsed is None or head_parsed is None:
        return False

    base_major, base_minor = base_parsed
    head_major, head_minor = head_parsed

    if base_major >= 1:
        return head_major > base_major

    # base_major == 0: 0.x は minor が増えたときに不合格（0.x -> 1.x も対象になる）
    if head_major > base_major:
        return True
    return head_major == base_major and head_minor > base_minor


# ---------------------------------------------------------------------------
# composer/metadata-minifier の expand() の移植
# 出典: https://github.com/composer/metadata-minifier
#       src/MetadataMinifier.php の expand()
# ---------------------------------------------------------------------------


def expand_minified_versions(versions: list[dict]) -> list[dict]:
    """minify された p2 の版の配列を、元の形に戻す。

    先頭から順に見て、直前の展開結果を引き継ぎ、各要素のキーで上書きし、
    値が文字列 "__unset" のキーは消す。
    """
    expanded: list[dict] = []
    expanded_version: Optional[dict] = None
    for version_data in versions:
        if expanded_version is None:
            expanded_version = dict(version_data)
            expanded.append(expanded_version)
            continue

        expanded_version = dict(expanded_version)
        for key, val in version_data.items():
            if val == "__unset":
                expanded_version.pop(key, None)
            else:
                expanded_version[key] = val
        expanded.append(expanded_version)

    return expanded


# ---------------------------------------------------------------------------
# Packagist p2 からの取得
# ---------------------------------------------------------------------------


def fetch_p2_json_text(
    vendor: str,
    name: str,
    *,
    user_agent: str = USER_AGENT,
    sleep: SleepFunc = time.sleep,
) -> str:
    """`https://repo.packagist.org/p2/<vendor>/<name>.json` を読む既定の実装。

    タイムアウトは 1 回 10 秒で固定、最大 3 回まで取り直す。取り直しの前に
    2 秒、4 秒と待つ（`sleep` はテストで差し替えられるように引数で受け取る）。
    HTTP の 4xx は取り直さず即座に失敗にする。5xx・タイムアウト・接続の失敗・
    `http.client.HTTPException`（`IncompleteRead`・`BadStatusLine`・
    `LineTooLong` 等、通信の途中で壊れたことを示す例外の系統）は取り直す。
    """
    url = f"https://repo.packagist.org/p2/{vendor}/{name}.json"
    request = urllib.request.Request(url, headers={"User-Agent": user_agent})

    last_error: Optional[BaseException] = None
    for attempt in range(1, HTTP_MAX_ATTEMPTS + 1):
        try:
            with urllib.request.urlopen(request, timeout=HTTP_TIMEOUT_SECONDS) as response:
                return response.read().decode("utf-8")
        except urllib.error.HTTPError as exc:
            if 400 <= exc.code < 500:
                raise PackagistFetchError(f"HTTP エラー {exc.code}") from exc
            last_error = exc
        except (
            urllib.error.URLError,
            TimeoutError,
            http.client.HTTPException,
            OSError,
        ) as exc:
            # http.client.HTTPException は IncompleteRead・BadStatusLine・
            # LineTooLong などの系統をまとめて取り直す（IncompleteRead は
            # HTTPException のサブクラスなので個別に挙げなくても含まれる）。
            last_error = exc

        if attempt < HTTP_MAX_ATTEMPTS:
            sleep(HTTP_RETRY_DELAYS_SECONDS[attempt - 1])

    raise PackagistFetchError(
        f"タイムアウトなどで取得できなかった ({last_error})", retryable=True
    ) from last_error


def escape_annotation_text(text: str) -> str:
    """GitHub Actions の注記のエスケープ規則を適用する。

    本文中の `%`・`\\r`・`\\n` を、GitHub の決まりどおり
    `%25`・`%0D`・`%0A` に置き換える（この順で。先に `%` を置換しないと
    後段の置換で作った `%0D`・`%0A` 自体が壊れる）。lock の name・version、
    例外の文言、p2 の値をそのまま `::error::`・`::warning::` へ流すと、
    改行や `%` を混ぜた偽の注記（例: `::notice::...`）を作られてしまうため、
    出力前に必ず通す。
    """
    return text.replace("%", "%25").replace("\r", "%0D").replace("\n", "%0A")


def parse_iso8601(value: str) -> dt.datetime:
    """`2026-06-18T04:04:58+00:00` 形式（末尾 Z も許す）を datetime にする。"""
    text = value.strip()
    if text.endswith("Z"):
        text = text[:-1] + "+00:00"
    parsed = dt.datetime.fromisoformat(text)
    if parsed.tzinfo is None:
        parsed = parsed.replace(tzinfo=dt.timezone.utc)
    return parsed


# ---------------------------------------------------------------------------
# 1 パッケージの判定
# ---------------------------------------------------------------------------


def evaluate_package(
    base_pkg: Optional[PackageEntry],
    head_pkg: PackageEntry,
    *,
    now: dt.datetime,
    min_age_days: int,
    fetch_json_text: FetchJsonText,
) -> tuple[Optional[Reason], bool]:
    """1 パッケージを判定する。戻り値は (不合格の理由 or None, 問い合わせたか)。

    公開日の問い合わせを飛ばす（判定不能にせず短絡する）のは、安定度が
    dev・alpha・beta・RC のときだけ。メジャーの上げが決まったパッケージも
    公開日を問い合わせて判定し、複数の理由があれば1つの Reason にまとめる
    （override-freshness で通すときに、オーナーが警告の一覧だけで
    「メジャーの上げ」と「公開から N 日」の両方を見落とさず判断できるように）。
    """
    name = head_pkg.name
    from_version = base_pkg.version if base_pkg is not None else None
    messages: list[str] = []

    # 3. 安定度（ネットワークは使わない）。dev・alpha・beta・RC だけ問い合わせを飛ばす
    stability = parse_stability(head_pkg.version)
    if stability != "stable":
        label = _STABILITY_LABEL[stability]
        return (
            Reason(name, from_version, head_pkg.version, f"{label} 版のため不合格"),
            False,
        )

    # 4. メジャー（上げのときだけ不合格。新しく入ったパッケージは判定しない）。
    #    ここでは短絡せず、理由を積んだまま公開日の問い合わせに進む。
    if base_pkg is not None and major_bump_rejected(base_pkg.version, head_pkg.version):
        messages.append("メジャーバージョンが上がったため不合格")

    # notification-url が Packagist 以外の出どころなら判定不能（問い合わせできない）
    if head_pkg.notification_url != PACKAGIST_NOTIFICATION_URL:
        messages.append("判定不能: notification-url が Packagist 以外の出どころ")
        return (
            Reason(name, from_version, head_pkg.version, "; ".join(messages)),
            False,
        )

    # パッケージ名が Composer の命名規則に合わなければ、URL に入れる前に判定不能にする
    # （問い合わせはしない）。
    if not _COMPOSER_NAME_REGEX.fullmatch(name):
        messages.append("判定不能: composer.lock の name が Composer の命名規則に合わない")
        return (
            Reason(name, from_version, head_pkg.version, "; ".join(messages)),
            False,
        )

    # 5. 公開日と reference（ネットワークに問い合わせる）。
    # p2 の応答が想定の形でない（null・[]・packages が無い/形が違う・published-time が
    # 文字列でない・source/dist が辞書でない・reference が文字列でない 等）ときは、
    # 型を確かめたうえで明示的に「判定不能」にする。ネットワーク由来の失敗は
    # PackagistFetchError、JSON の破損・デコードの失敗は専用の except で扱う。
    # published-time のパース（parse_iso8601）の ValueError だけは、その呼び出しを
    # 囲むローカルな try/except で狭く受ける。それ以外の例外（このスクリプト
    # 自体の不具合。fetch_json_text の呼び出しや展開から出た ValueError も含む）は
    # ここで飲み込まず、run_check・main まで素通しして 1 で終わらせる
    # （--override でも 1）。
    vendor, _, short_name = name.partition("/")
    try:
        raw_text = fetch_json_text(vendor, short_name)
        data = json.loads(raw_text)

        packages_section = data.get("packages") if isinstance(data, dict) else None
        if not isinstance(packages_section, dict):
            messages.append("判定不能: p2 の応答の形が想定と違う (packages が無いか辞書でない)")
            return (
                Reason(name, from_version, head_pkg.version, "; ".join(messages)),
                True,
            )

        versions = packages_section.get(name)
        if not isinstance(versions, list):
            messages.append(
                "判定不能: p2 の応答の形が想定と違う (対象パッケージの版一覧が無いか配列でない)"
            )
            return (
                Reason(name, from_version, head_pkg.version, "; ".join(messages)),
                True,
            )

        if data.get("minified") == "composer/2.0":
            if not all(isinstance(v, dict) for v in versions):
                messages.append(
                    "判定不能: p2 の応答の形が想定と違う (版一覧の要素が辞書でない)"
                )
                return (
                    Reason(name, from_version, head_pkg.version, "; ".join(messages)),
                    True,
                )
            versions = expand_minified_versions(versions)

        found = next(
            (v for v in versions if isinstance(v, dict) and v.get("version") == head_pkg.version),
            None,
        )
        if found is None:
            messages.append("判定不能: 該当バージョンが p2 に見つからない")
            return (
                Reason(
                    name, from_version, head_pkg.version, "; ".join(messages),
                    versions_readable=True,
                ),
                True,
            )

        # reference の突合（version の文字列だけでなく、実体（commit）が一致するか確かめる）。
        # キーはあるが辞書でない・reference が文字列でないときは応答の形が想定と
        # 違うとして判定不能にする（比較を静かに飛ばさない）。
        found_source = found.get("source")
        if found_source is not None and not isinstance(found_source, dict):
            messages.append("判定不能: p2 の応答の形が想定と違う (source が辞書でない)")
            return (
                Reason(
                    name, from_version, head_pkg.version, "; ".join(messages),
                    versions_readable=True,
                ),
                True,
            )
        found_dist = found.get("dist")
        if found_dist is not None and not isinstance(found_dist, dict):
            messages.append("判定不能: p2 の応答の形が想定と違う (dist が辞書でない)")
            return (
                Reason(
                    name, from_version, head_pkg.version, "; ".join(messages),
                    versions_readable=True,
                ),
                True,
            )
        p2_source_reference = found_source.get("reference") if found_source is not None else None
        if p2_source_reference is not None and not isinstance(p2_source_reference, str):
            messages.append(
                "判定不能: p2 の応答の形が想定と違う (source.reference が文字列でない)"
            )
            return (
                Reason(
                    name, from_version, head_pkg.version, "; ".join(messages),
                    versions_readable=True,
                ),
                True,
            )
        p2_dist_reference = found_dist.get("reference") if found_dist is not None else None
        if p2_dist_reference is not None and not isinstance(p2_dist_reference, str):
            messages.append(
                "判定不能: p2 の応答の形が想定と違う (dist.reference が文字列でない)"
            )
            return (
                Reason(
                    name, from_version, head_pkg.version, "; ".join(messages),
                    versions_readable=True,
                ),
                True,
            )

        # lock と p2 の両方に reference がある種類（source 同士・dist 同士）だけを
        # 比べる。比べられる組み合わせが1つも無ければ、version の文字列が一致
        # しているだけで合格にはせず判定不能にする（開発部長の決定）。1つでも
        # 比べられて一致すれば、他が比べられなくても合格側（今のとおり）。
        source_comparable = (
            head_pkg.source_reference is not None and p2_source_reference is not None
        )
        dist_comparable = (
            head_pkg.dist_reference is not None and p2_dist_reference is not None
        )
        if not source_comparable and not dist_comparable:
            messages.append(
                "判定不能: reference を Packagist の登録と照らせない"
                " (lock・p2 の双方に source・dist いずれかの reference が無い)"
            )
            return (
                Reason(
                    name, from_version, head_pkg.version, "; ".join(messages),
                    versions_readable=True,
                ),
                True,
            )
        reference_mismatch = (
            source_comparable and head_pkg.source_reference != p2_source_reference
        ) or (
            dist_comparable and head_pkg.dist_reference != p2_dist_reference
        )
        if reference_mismatch:
            messages.append("判定不能: composer.lock の reference が Packagist の登録と合わない")
            return (
                Reason(
                    name, from_version, head_pkg.version, "; ".join(messages),
                    versions_readable=True,
                ),
                True,
            )

        published_raw = found.get("published-time")
        if not isinstance(published_raw, str) or not published_raw:
            messages.append("判定不能: published-time が無いか、形が想定と違う")
            return (
                Reason(
                    name, from_version, head_pkg.version, "; ".join(messages),
                    versions_readable=True,
                ),
                True,
            )

        try:
            published_at = parse_iso8601(published_raw)
        except ValueError as exc:
            # ValueError はここ（parse_iso8601 の呼び出し）だけを囲む。前後の処理
            # （fetch_json_text の呼び出しや p2 の応答の展開）で起きた ValueError まで
            # 「published-time を読めない」に化けさせないため、except の範囲を狭める。
            messages.append(f"判定不能: published-time を読めない ({exc})")
            return (
                Reason(
                    name, from_version, head_pkg.version, "; ".join(messages),
                    versions_readable=True,
                ),
                True,
            )

        if published_at > now + dt.timedelta(minutes=5):
            messages.append("判定不能: published-time が現在時刻より5分以上先")
            return (
                Reason(
                    name, from_version, head_pkg.version, "; ".join(messages),
                    versions_readable=True,
                ),
                True,
            )

        age = now - published_at
        if age < dt.timedelta(days=min_age_days):
            messages.append(f"公開から {min_age_days} 日未満のため不合格 (published-time={published_raw})")

    except PackagistFetchError as exc:
        messages.append(f"判定不能: p2 の取得に失敗した ({exc})")
        return (
            Reason(
                name,
                from_version,
                head_pkg.version,
                "; ".join(messages),
                retry_exhausted_fetch_failure=exc.retryable,
            ),
            True,
        )
    except json.JSONDecodeError as exc:
        messages.append(f"判定不能: p2 の応答が JSON として壊れている ({exc})")
        return (
            Reason(name, from_version, head_pkg.version, "; ".join(messages)),
            True,
        )
    except UnicodeDecodeError as exc:
        # UnicodeDecodeError は ValueError のサブクラスだが、下に広い except
        # ValueError を置いていない（parse_iso8601 の呼び出しだけをローカルな
        # try/except で囲んでいる）ので、ここでの捕捉順は他に影響しない。
        # fetch_json_text 自身が p2 の応答を UTF-8 として読めなかったときに
        # 送出する（既定の実装は response.read().decode("utf-8") を直接返す）。
        messages.append(f"判定不能: p2 の応答を読めない ({exc})")
        return (
            Reason(name, from_version, head_pkg.version, "; ".join(messages)),
            True,
        )
    # ここで広く except Exception も except ValueError も受けない。PackagistFetchError・
    # JSONDecodeError・UnicodeDecodeError 以外の例外（parse_iso8601 の呼び出し以外で
    # 起きた ValueError を含む）は、外から来たデータではなくこのスクリプト自体の
    # 不具合とみなし、run_check・main まで素通しして 1 で終わらせる（--override でも 1）。

    if messages:
        return (
            Reason(
                name, from_version, head_pkg.version, "; ".join(messages),
                versions_readable=True,
            ),
            True,
        )

    return None, True


# ---------------------------------------------------------------------------
# CLI
# ---------------------------------------------------------------------------


def _git_sha_arg(value: str) -> str:
    """--base / --head の引数の形を確かめる（7〜40桁の16進数のみ）。

    `-` で始まる値を git のオプションとして誤読させないための制約でもある。
    """
    if not _GIT_SHA_REGEX.fullmatch(value):
        raise argparse.ArgumentTypeError(
            f"7〜40桁の16進数で指定してください: {value!r}"
        )
    return value


def _positive_int_arg(value: str) -> int:
    """--min-age-days の引数の形を確かめる（1 以上の整数のみ）。"""
    try:
        parsed = int(value)
    except ValueError as exc:
        raise argparse.ArgumentTypeError(f"整数で指定してください: {value!r}") from exc
    if parsed < 1:
        raise argparse.ArgumentTypeError("1 以上を指定してください")
    return parsed


def build_arg_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        description="composer.lock の依存更新が公開直後・不安定・メジャー上げでないかを確かめる"
    )
    parser.add_argument("--base", type=_git_sha_arg, help="比較元の sha（7〜40桁の16進数）")
    parser.add_argument("--head", type=_git_sha_arg, help="比較先の sha（7〜40桁の16進数）")
    parser.add_argument(
        "--min-age-days",
        type=_positive_int_arg,
        default=DEFAULT_MIN_AGE_DAYS,
        help=f"公開からこの日数未満なら不合格にする（既定 {DEFAULT_MIN_AGE_DAYS}、1 以上）",
    )
    parser.add_argument(
        "--override",
        action="store_true",
        help="不合格の理由を ::warning:: にして終了コードを 0 にする",
    )
    parser.add_argument(
        "--canary",
        action="store_true",
        help="既知の published-time が実測どおりか確かめて終わる",
    )
    return parser


def format_reason_summary(reason: Reason) -> str:
    """Reason を1行のテキストに組み立てる（stdout の一覧・注記の両方で使う）。

    lock の name・version や例外の文言、p2 の値をそのまま流すと、改行や `%` を
    混ぜて行を増やしたり偽の注記を作られたりするため、`escape_annotation_text`
    で必ずエスケープする。
    """
    frm = reason.from_version if reason.from_version is not None else "(new)"
    text = f"{reason.name}: {frm} -> {reason.to_version}: {reason.message}"
    return escape_annotation_text(text)


def emit_reason(reason: Reason, *, override: bool, stream) -> None:
    level = "warning" if override else "error"
    print(f"::{level}::{format_reason_summary(reason)}", file=stream)


def run_check(
    args: argparse.Namespace,
    *,
    git_show: GitShow,
    git_merge_base: GitMergeBase,
    fetch_json_text: FetchJsonText,
    now_func: NowFunc,
    stream,
    monotonic: MonotonicFunc = time.monotonic,
) -> int:
    try:
        merge_base_sha = git_merge_base(args.base, args.head)
        base_text = git_show(f"{merge_base_sha}:composer.lock")
        head_text = git_show(f"{args.head}:composer.lock")
        base_packages, base_duplicates = parse_lock_packages(base_text)
        head_packages, head_duplicates = parse_lock_packages(head_text)
    except (LockParseError, GitCommandError) as exc:
        # LockParseError は composer.lock 自体の破損・形の違い、GitCommandError は
        # git show / git merge-base の失敗（既定実装が投げる専用の型）。どちらも
        # 「composer.lock を読めない（判定不能）」として扱ってよい。それ以外の
        # 例外（スクリプト自体の不具合）はここで飲み込まず、main の except まで
        # 素通しして 1 で終わらせる。
        reason = Reason(
            name="composer.lock",
            from_version=None,
            to_version=args.head or "",
            message=f"判定不能: composer.lock を読めない ({exc})",
        )
        print("動いたパッケージ: 0件、問い合わせ: 0件、不合格: 1件", file=stream)
        emit_reason(reason, override=args.override, stream=stream)
        return 0 if args.override else 1

    moved = find_moved_packages(base_packages, head_packages)

    # 同じ名前が base・head どちらかの lock に複数回出てくるパッケージは、
    # 辞書化する時点で後の項目に上書きされてしまい、実際に本番（--no-dev）で
    # 使われる版との対応が取れない。version が base と一致して見え、本来
    # 「動いた」はずなのに moved に含まれないケースがあるので、その分も追加する。
    duplicate_names = base_duplicates | head_duplicates
    moved_names = {head_pkg.name for _, head_pkg in moved}
    for name in sorted(duplicate_names):
        if name in moved_names or name not in head_packages:
            continue
        moved.append((base_packages.get(name), head_packages[name]))

    reasons: list[Reason] = []
    queried = 0
    now = now_func()
    deadline_start = monotonic()
    consecutive_fetch_failures = 0
    for base_pkg, head_pkg in moved:
        if head_pkg.name in duplicate_names:
            reason: Optional[Reason] = Reason(
                head_pkg.name,
                base_pkg.version if base_pkg is not None else None,
                head_pkg.version,
                "判定不能: composer.lock に同じ名前が複数ある",
            )
            was_queried = False
        elif monotonic() - deadline_start > HTTP_OVERALL_DEADLINE_SECONDS:
            # 全体の締め切りを過ぎたら、残りは問い合わせずに時間切れとして判定不能にする。
            reason = Reason(
                head_pkg.name,
                base_pkg.version if base_pkg is not None else None,
                head_pkg.version,
                "判定不能: 時間切れ",
            )
            was_queried = False
        elif consecutive_fetch_failures >= CONSECUTIVE_FETCH_FAILURE_LIMIT:
            # Packagist への問い合わせが続けて失敗しているなら、残りは問い合わせずに
            # 打ち切る（障害中に全パッケージ分のタイムアウトを待たされないように）。
            reason = Reason(
                head_pkg.name,
                base_pkg.version if base_pkg is not None else None,
                head_pkg.version,
                "判定不能: Packagist への問い合わせが続けて失敗したため打ち切り",
            )
            was_queried = False
        else:
            reason, was_queried = evaluate_package(
                base_pkg,
                head_pkg,
                now=now,
                min_age_days=args.min_age_days,
                fetch_json_text=fetch_json_text,
            )
            # カウンタは、問い合わせをした（p2 の応答を取りに行った）ときだけ動かす。
            # 加算するのは、取り直しを使い切った問い合わせの失敗（文言の部分一致
            # ではなく、Reason.retry_exhausted_fetch_failure で判定）だけ。0 に
            # 戻すのは、p2 の応答を取得でき、JSON として読めて、そのパッケージの
            # 版一覧が読めたとき（reason が None＝合格、または
            # Reason.versions_readable）だけ。4xx・壊れた JSON・応答の形の違いなど、
            # 版一覧にたどり着く前の失敗は、数えも戻しもしない（そのまま据え置く）。
            # 問い合わせをしない判定（dev 版・名前の規則違反など、
            # was_queried=False）でもどちらもしない。
            if was_queried:
                if reason is not None and reason.retry_exhausted_fetch_failure:
                    consecutive_fetch_failures += 1
                elif reason is None or reason.versions_readable:
                    consecutive_fetch_failures = 0
        if was_queried:
            queried += 1
        if reason is not None:
            reasons.append(reason)

    print(
        f"動いたパッケージ: {len(moved)}件、問い合わせ: {queried}件、不合格: {len(reasons)}件",
        file=stream,
    )
    for reason in reasons:
        print(f"  - {format_reason_summary(reason)}", file=stream)
        emit_reason(reason, override=args.override, stream=stream)

    if not reasons:
        return 0
    return 0 if args.override else 1


def run_canary(*, fetch_json_text: FetchJsonText, stream) -> int:
    ok = True
    for full_name, version, expected in CANARY_CHECKS:
        vendor, _, short_name = full_name.partition("/")
        try:
            raw_text = fetch_json_text(vendor, short_name)
            data = json.loads(raw_text)
        except Exception as exc:  # noqa: BLE001 - canary は失敗理由をそのまま出す
            print(
                f"canary: {full_name}@{version} の取得に失敗した: "
                f"{escape_annotation_text(str(exc))}",
                file=stream,
            )
            ok = False
            continue

        versions = (data.get("packages") or {}).get(full_name, [])
        if data.get("minified") == "composer/2.0":
            versions = expand_minified_versions(versions)

        found = next((v for v in versions if v.get("version") == version), None)
        actual = found.get("published-time") if found else None
        if actual != expected:
            print(
                f"canary: {full_name}@{version} の published-time が一致しない "
                f"(期待 {expected}, 実際 {escape_annotation_text(str(actual))})",
                file=stream,
            )
            ok = False
        else:
            print(f"canary: {full_name}@{version} OK ({actual})", file=stream)

    return 0 if ok else 1


def main(
    argv: Optional[list[str]] = None,
    *,
    git_show: GitShow = run_git_show,
    git_merge_base: GitMergeBase = run_git_merge_base,
    fetch_json_text: Optional[FetchJsonText] = None,
    now_func: NowFunc = lambda: dt.datetime.now(dt.timezone.utc),
    monotonic: MonotonicFunc = time.monotonic,
    stream=sys.stdout,
) -> int:
    parser = build_arg_parser()
    args = parser.parse_args(argv)

    if fetch_json_text is None:
        fetch_json_text = fetch_p2_json_text

    try:
        if args.canary:
            return run_canary(fetch_json_text=fetch_json_text, stream=stream)

        if not args.base or not args.head:
            parser.error("--base と --head が必要（--canary のときは不要）")

        return run_check(
            args,
            git_show=git_show,
            git_merge_base=git_merge_base,
            fetch_json_text=fetch_json_text,
            now_func=now_func,
            monotonic=monotonic,
            stream=stream,
        )
    except SystemExit:
        # parser.error() などが送出する正常な終了は素通しする（引数の誤りは終了コード 2）。
        raise
    except Exception as exc:  # noqa: BLE001 - スクリプト自体の不具合はここで受けて 1 にする
        # --override が指定されていても、判定結果ではなくスクリプト自体の不具合による
        # 失敗なので終了コードは 1 のまま。ただし注記は出す。
        print(
            "::error::check_lock_freshness.py の不具合で失敗した"
            f" ({type(exc).__name__}: {escape_annotation_text(str(exc))})",
            file=stream,
        )
        return 1


if __name__ == "__main__":
    sys.exit(main())
