<?php
namespace App\Domain\HealthCheck\Enums;

enum HealthStatus: string
{
    case OK = 'ok';
    case FAIL = 'fail';
}
?>