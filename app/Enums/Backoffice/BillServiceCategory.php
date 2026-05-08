<?php

namespace App\Enums\Backoffice;

enum BillServiceCategory: string
{
    case ELECTRICITY = 'ELECTRICITY';
    case WATER = 'WATER';
    case TV = 'TV';
    case TELECOM = 'TELECOM';
    case AIRTIME = 'AIRTIME';
    case INTERNET = 'INTERNET';
    case OTHER = 'OTHER';
}
