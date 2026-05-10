<?php declare(strict_types=1);

namespace App\Enums;

enum AdminRole: string
{
    case SystemAdmin = 'system_admin';
    case GeneralAdmin = 'general_admin';

    public function label(): string
    {
        return match ($this) {
            self::SystemAdmin => 'システム管理者',
            self::GeneralAdmin => '一般管理者',
        };
    }
}
