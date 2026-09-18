<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performances', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('show_id')->constrained('shows')->cascadeOnDelete();
            $table->unsignedInteger('price');
            $table->dateTime('starts_at');
            $table->dateTime('sale_opens_at');
            $table->dateTime('sale_closes_at');
            $table->timestamps();
            $table->index(['sale_opens_at', 'sale_closes_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performances');
    }
};
