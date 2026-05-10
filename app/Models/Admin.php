<?php declare(strict_types=1);

namespace App\Models;

use App\Enums\AdminRole;
use Database\Factories\AdminFactory;
use Illuminate\Auth\Passwords\CanResetPassword as CanResetPasswordTrait;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;

/** @use HasFactory<AdminFactory> */
class Admin extends Authenticatable implements CanResetPassword, MustVerifyEmail
{
    /** @use HasFactory<AdminFactory> */
    use CanResetPasswordTrait, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'last_login_at',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'integer',
        'role' => AdminRole::class,
        'last_login_at' => 'datetime',
        'email_verified_at' => 'datetime',
    ];

    public function isSystemAdmin(): bool
    {
        return $this->role === AdminRole::SystemAdmin;
    }

    public static function isSystemAdminLoggedIn(): bool
    {
        $user = Auth::guard('admin')->user();

        return $user instanceof self && $user->isSystemAdmin();
    }
}
