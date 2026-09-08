<?php

namespace App\Enums;

/**
 * Lifecycle of `ppmps.status`. A purchase request may only draw from a
 * `Validated` plan, and the Consolidated APP aggregates those plans only.
 */
enum PpmpStatus: string
{
    case Draft = 'draft';
    case Validated = 'validated';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Validated => 'Validated',
        };
    }
}
