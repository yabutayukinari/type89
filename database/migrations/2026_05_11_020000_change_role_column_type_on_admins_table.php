<?php declare(strict_types=1);

use App\Enums\AdminRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('admins', static function (Blueprint $table): void {
            $table->enum('role', array_column(AdminRole::cases(), 'value'))
                ->default(AdminRole::GeneralAdmin->value)
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admins', static function (Blueprint $table): void {
            $table->string('role', 32)
                ->default(AdminRole::GeneralAdmin->value)
                ->change();
        });
    }
};
