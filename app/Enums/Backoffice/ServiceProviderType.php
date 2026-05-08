<?php

namespace App\Enums\Backoffice;

enum ServiceProviderType: string
{
    case EXTERNAL_API = 'EXTERNAL_API';
    case INTERNAL = 'INTERNAL';
}
