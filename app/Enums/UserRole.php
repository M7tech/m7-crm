<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case CompanyAdmin = 'company_admin';
    case SalesManager = 'sales_manager';
    case Salesperson = 'salesperson';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super admin',
            self::CompanyAdmin => 'Company admin',
            self::SalesManager => 'Sales manager',
            self::Salesperson => 'Salesperson',
        };
    }
}
