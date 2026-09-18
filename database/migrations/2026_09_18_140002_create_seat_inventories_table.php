<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seat_inventories', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('performance_id')->unique()->constrained('performances')->cascadeOnDelete();
            $table->unsignedInteger('capacity');
            $table->unsignedInteger('remaining_seats');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seat_inventories');
    }
};
