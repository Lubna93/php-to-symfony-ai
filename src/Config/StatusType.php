<?php

namespace App\Config;

enum StatusType: string
{
    case DRAFT = 'draft';
    case PROCESSING = 'processing';
    case EXTRACTED = 'extracted';
}
