<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_system_admin_logged_in_returns_true_for_system_admin(): void
    {
        $admin = Admin::factory()->systemAdmin()->create();
        Auth::guard('admin')->setUser($admin);

        $this->assertTrue(Admin::isSystemAdminLoggedIn());
    }

    public function test_is_system_admin_logged_in_returns_false_for_general_admin(): void
    {
        $admin = Admin::factory()->generalAdmin()->create();
        Auth::guard('admin')->setUser($admin);

        $this->assertFalse(Admin::isSystemAdminLoggedIn());
    }

    public function test_is_system_admin_logged_in_returns_false_when_not_logged_in(): void
    {
        $this->assertFalse(Admin::isSystemAdminLoggedIn());
    }

    public function test_is_system_admin_instance_method(): void
    {
        $systemAdmin = Admin::factory()->systemAdmin()->create();
        $generalAdmin = Admin::factory()->generalAdmin()->create();

        $this->assertTrue($systemAdmin->isSystemAdmin());
        $this->assertFalse($generalAdmin->isSystemAdmin());
    }
}
