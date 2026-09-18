<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slot_events', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('performance_id')->constrained('performances')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('queue_entry_id')->constrained('queue_entries')->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('release_reason', 32)->nullable();
            $table->unsignedInteger('remaining_seats_after');
            $table->dateTime('occurred_at');
            $table->timestamps();
            $table->index(['performance_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slot_events');
    }
};
