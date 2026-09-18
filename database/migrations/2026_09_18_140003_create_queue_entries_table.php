<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_entries', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('performance_id')->constrained('performances')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 32);
            $table->unsignedInteger('position');
            $table->dateTime('joined_at');
            $table->timestamps();
            $table->unique(['performance_id', 'user_id']);
            $table->index(['performance_id', 'status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_entries');
    }
};
