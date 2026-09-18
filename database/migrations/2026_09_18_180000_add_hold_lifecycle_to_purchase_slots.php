<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_slots', static function (Blueprint $table): void {
            $table->string('status', 32)->default('held')->after('queue_entry_id');
            $table->dateTime('expires_at')->nullable()->after('assigned_at');
            $table->dateTime('confirmed_at')->nullable()->after('expires_at');
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('purchase_slots', static function (Blueprint $table): void {
            $table->dropIndex(['status', 'expires_at']);
            $table->dropColumn(['status', 'expires_at', 'confirmed_at']);
        });
    }
};
