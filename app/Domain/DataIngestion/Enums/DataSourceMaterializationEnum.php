<?php

namespace App\Domain\DataIngestion\Enums;

enum DataSourceMaterializationEnum: string
{
    case SNAPSHOT = 'snapshot';
    case LIVE = 'live';
}
