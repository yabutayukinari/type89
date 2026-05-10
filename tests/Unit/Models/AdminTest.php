<?php declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function testIsSystemAdminLoggedInReturnsTrueForSystemAdmin(): void
    {
        $admin = Admin::factory()->systemAdmin()->create();
        Auth::guard('admin')->setUser($admin);

        $this->assertTrue(Admin::isSystemAdminLoggedIn());
    }

    public function testIsSystemAdminLoggedInReturnsFalseForGeneralAdmin(): void
    {
        $admin = Admin::factory()->generalAdmin()->create();
        Auth::guard('admin')->setUser($admin);

        $this->assertFalse(Admin::isSystemAdminLoggedIn());
    }

    public function testIsSystemAdminLoggedInReturnsFalseWhenNotLoggedIn(): void
    {
        $this->assertFalse(Admin::isSystemAdminLoggedIn());
    }

    public function testIsSystemAdminInstanceMethod(): void
    {
        $systemAdmin = Admin::factory()->systemAdmin()->create();
        $generalAdmin = Admin::factory()->generalAdmin()->create();

        $this->assertTrue($systemAdmin->isSystemAdmin());
        $this->assertFalse($generalAdmin->isSystemAdmin());
    }
}
