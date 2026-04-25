<?php

namespace App\Domain\StatsData\Enums;

enum StatsDataSnapshotStatus: string
{
    case Ok = 'ok';
    case Failed = 'failed';
}
