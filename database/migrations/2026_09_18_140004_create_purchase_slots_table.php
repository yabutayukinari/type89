<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_slots', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('performance_id')->constrained('performances')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('queue_entry_id')->constrained('queue_entries')->cascadeOnDelete();
            $table->dateTime('assigned_at');
            $table->timestamps();
            $table->unique(['performance_id', 'user_id']);
            $table->unique('queue_entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_slots');
    }
};
