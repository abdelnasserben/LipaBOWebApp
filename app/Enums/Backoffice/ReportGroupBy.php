<?php

namespace App\Enums\Backoffice;

enum ReportGroupBy: string
{
    case DAY = 'DAY';
    case WEEK = 'WEEK';
    case MONTH = 'MONTH';
}
