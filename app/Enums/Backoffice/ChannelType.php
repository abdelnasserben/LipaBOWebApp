<?php

namespace App\Enums\Backoffice;

enum ChannelType: string
{
    case TERMINAL_NFC = 'TERMINAL_NFC';
    case TERMINAL_MANUAL = 'TERMINAL_MANUAL';
    case MOBILE_APP = 'MOBILE_APP';
    case AGENT_CHANNEL = 'AGENT_CHANNEL';
    case WEB_APP = 'WEB_APP';
    case BACKOFFICE_UI = 'BACKOFFICE_UI';
    case BACKOFFICE_JOB = 'BACKOFFICE_JOB';
}
