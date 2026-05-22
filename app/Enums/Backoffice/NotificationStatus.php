<?php

namespace App\Enums\Backoffice;

enum NotificationStatus: string
{
    case UNREAD = 'UNREAD';
    case READ = 'READ';
}
