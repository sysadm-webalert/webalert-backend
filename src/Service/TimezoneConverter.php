<?php

namespace App\Service;

use DateTimeInterface;
use DateTimeImmutable;
use DateTimeZone;

class TimezoneConverter
{
    public function convertToUserTimezone(DateTimeInterface $date, ?string $timezone): DateTimeInterface
    {
        if ($timezone) {
            $userTimezone = new DateTimeZone($timezone);
            if ($date instanceof DateTimeImmutable) {
                return $date->setTimezone($userTimezone);
            } else {
                return (clone $date)->setTimezone($userTimezone);
            }
        }
        return $date;
    }
}