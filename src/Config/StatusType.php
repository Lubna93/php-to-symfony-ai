<?php

namespace App\Config;

enum StatusType: string
{
    case DRAFT = 'draft';
    case PROCESSING = 'processing';
    case UPLOADED = 'uploaded';
    case EXTRACTED = 'extracted';
}
