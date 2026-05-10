<?php declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AdminRole;
use App\Models\Admin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Admin>
 */
class AdminFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Admin>
     */
    protected $model = Admin::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => Hash::make($this->faker->password()),
            'role' => AdminRole::GeneralAdmin,
            'last_login_at' => null,
            'email_verified_at' => $this->faker->dateTime(),
            'remember_token' => Str::random(10),
        ];
    }

    public function systemAdmin(): static
    {
        return $this->state([
            'role' => AdminRole::SystemAdmin,
        ]);
    }

    public function generalAdmin(): static
    {
        return $this->state([
            'role' => AdminRole::GeneralAdmin,
        ]);
    }
}
