<?php

namespace App\Enum;

enum MetricType: string
{
    case MAX_CPU = 'max_cpu';
    case MAX_RAM = 'max_ram';
    case MAX_DISK = 'max_disk';
}
