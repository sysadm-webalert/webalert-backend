<?php

namespace App\Service;

use App\Entity\Threshold;
use App\Entity\Status;
use App\Entity\Metrics;

class ThresholdService
{
    public function checkThreshold(Status $status, Threshold $threshold): array
    {
        $violations = [];
    
        if ($this->isHttpCodeOutsideThreshold($status->getStatusCode(), $threshold->getHttpCode())) {
            $violations[] = ['kind' => 'status_alive'];
        }
    
        if ($threshold->getMaxResponse() !== null && $status->getResponseTime() > $threshold->getMaxResponse()) {
            $violations[] = ['kind' => 'response_time'];
        }

        return $violations;
    }

    private function isHttpCodeOutsideThreshold(int $statusCode, string $httpCodeRange): bool
    {
        if (strpos($httpCodeRange, '-') !== false) {
            [$min, $max] = explode('-', $httpCodeRange);
            return $statusCode < (int)$min || $statusCode > (int)$max;
        }

        return $statusCode != (int)$httpCodeRange;
    }

    public function checkThresholdForMetrics(Metrics $metrics, Threshold $threshold): array
    {
        $violations = [];
    
        if ($threshold->getMaxCPU() !== null && $metrics->getCpuUsage() > $threshold->getMaxCPU()) {
            $violations[] = ['kind' => 'max_cpu'];
        }
    
        if ($threshold->getMaxRAM() !== null && $metrics->getMemoryUsage() > $threshold->getMaxRAM()) {
            $violations[] = ['kind' => 'max_ram'];
        }
    
        if ($threshold->getMaxDISK() !== null && $metrics->getDiskUsage() > $threshold->getMaxDISK()) {
            $violations[] = ['kind' => 'max_disk'];
        }
    
        return $violations;
    }
}
