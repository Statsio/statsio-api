<?php

namespace App\Domain\StatsData\Enums;

enum StatsDataApiResponseFormat: string
{
    case Json = 'json';
    case Xml = 'xml';
    case Csv = 'csv';
    case Text = 'text';
    case Unknown = 'unknown';
}
