<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', static function (Blueprint $table): void {
            // 落札確定処理 (auctions:close) を通した時刻。
            // null の間は「終了時刻を過ぎていても未確定」であることを表し、
            // コマンドの二重実行を冪等に防ぐためのフラグを兼ねる。
            $table->timestamp('settled_at')->nullable()->after('ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('auctions', static function (Blueprint $table): void {
            $table->dropColumn('settled_at');
        });
    }
};
