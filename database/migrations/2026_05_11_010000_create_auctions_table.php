<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auctions', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seller_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 120);
            $table->text('description');
            $table->unsignedBigInteger('starting_price');
            $table->unsignedBigInteger('bid_increment');
            $table->unsignedBigInteger('current_price');
            $table->foreignId('current_winner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->timestamps();
            $table->index(['ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auctions');
    }
};
