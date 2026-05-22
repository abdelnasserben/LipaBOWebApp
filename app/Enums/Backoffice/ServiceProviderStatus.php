<?php

namespace App\Enums\Backoffice;

enum ServiceProviderStatus: string
{
    case ACTIVE = 'ACTIVE';
    case MAINTENANCE = 'MAINTENANCE';
    case SUSPENDED = 'SUSPENDED';
    case INACTIVE = 'INACTIVE';
}
