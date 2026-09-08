<?php

namespace App\Services;

use DateTimeInterface;
use Illuminate\Support\Facades\Date;

/**
 * Resolves which PPMP quarter a date falls in. Purchase requests may only draw
 * against the current quarter's allocation, and the purchase request slice
 * stamps `pr_quarter` from here.
 */
class PpmpQuarterlyTracker
{
    public function currentQuarter(): int
    {
        return $this->quarterFor(Date::now());
    }

    public function currentFiscalYear(): int
    {
        return (int) Date::now()->format('Y');
    }

    public function quarterFor(DateTimeInterface $date): int
    {
        return (int) ceil((int) $date->format('n') / 3);
    }
}
