<?php

namespace App\Services;

use Generator;
use RuntimeException;

/**
 * Reads an APP-CSE worksheet exported to CSV.
 *
 * The catalog import and the PPMP import consume the same file, so the column
 * map and the rules for telling a category banner apart from an item line live
 * here once. Both importers walk the sheet top to bottom, carrying the last
 * category banner down onto the item rows beneath it.
 */
class AppCsvParser
{
    public const COL_SEQUENCE = 0;

    public const COL_ITEM_CODE = 1;

    public const COL_ITEM_NAME = 2;

    public const COL_UNIT_OF_MEASURE = 3;

    public const COL_Q1_QUANTITY = 7;

    public const COL_Q2_QUANTITY = 12;

    public const COL_Q3_QUANTITY = 17;

    public const COL_Q4_QUANTITY = 22;

    public const COL_UNIT_PRICE = 25;

    /**
     * All-caps banners that are sheet furniture rather than a real category.
     *
     * @var list<string>
     */
    private const NON_CATEGORY_MARKERS = ['PART I.', 'APP-CSE', 'ANNUAL'];

    /**
     * Item rows in sheet order, each already tagged with the category banner
     * that preceded it. Rows before the first banner, and rows that carry no
     * item code or name, are dropped.
     *
     * @return Generator<int, array{
     *     category: string,
     *     item_code: string,
     *     item_name: string,
     *     unit_of_measure: string,
     *     unit_price: float|null,
     *     quarters: array<int, int>,
     * }>
     */
    public function items(string $path): Generator
    {
        $handle = @fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("Unable to read CSV file [{$path}].");
        }

        $category = null;

        try {
            while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                if ($this->isEmptyRow($row)) {
                    continue;
                }

                $banner = $this->categoryFor($row);

                if ($banner !== null) {
                    $category = $banner;

                    continue;
                }

                if ($category === null || ! $this->isItemRow($row)) {
                    continue;
                }

                yield [
                    'category' => $category,
                    'item_code' => trim($this->cell($row, self::COL_ITEM_CODE)),
                    'item_name' => trim($this->cell($row, self::COL_ITEM_NAME)),
                    'unit_of_measure' => trim($this->cell($row, self::COL_UNIT_OF_MEASURE)),
                    'unit_price' => $this->parsePrice($this->cell($row, self::COL_UNIT_PRICE)),
                    'quarters' => [
                        1 => $this->parseQuantity($this->cell($row, self::COL_Q1_QUANTITY)),
                        2 => $this->parseQuantity($this->cell($row, self::COL_Q2_QUANTITY)),
                        3 => $this->parseQuantity($this->cell($row, self::COL_Q3_QUANTITY)),
                        4 => $this->parseQuantity($this->cell($row, self::COL_Q4_QUANTITY)),
                    ],
                ];
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * The category this row announces, or null when it is not a banner.
     *
     * @param  list<string|null>  $row
     */
    private function categoryFor(array $row): ?string
    {
        $first = trim($this->cell($row, self::COL_SEQUENCE));

        if (strcasecmp($first, 'SOFTWARE') === 0) {
            return 'SOFTWARE';
        }

        $joined = mb_strtoupper(implode(' ', array_map(fn ($cell): string => (string) $cell, $row)));

        if (str_contains($joined, 'PART II') && str_contains($joined, 'OTHER ITEMS')) {
            return 'PART II - OTHER ITEMS NOT AVAILABLE AT PS-DBM';
        }

        if ($first === '' || is_numeric($first) || mb_strlen($first) <= 10) {
            return null;
        }

        if ($first !== mb_strtoupper($first)) {
            return null;
        }

        foreach (self::NON_CATEGORY_MARKERS as $marker) {
            if (str_contains($first, $marker)) {
                return null;
            }
        }

        return $first;
    }

    /**
     * @param  list<string|null>  $row
     */
    private function isItemRow(array $row): bool
    {
        return is_numeric(trim($this->cell($row, self::COL_SEQUENCE)))
            && trim($this->cell($row, self::COL_ITEM_CODE)) !== ''
            && trim($this->cell($row, self::COL_ITEM_NAME)) !== '';
    }

    /**
     * @param  list<string|null>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string|null>  $row
     */
    private function cell(array $row, int $index): string
    {
        return (string) ($row[$index] ?? '');
    }

    /**
     * Prices arrive as `₱ 1,234.00`. Anything that does not resolve to a
     * positive figure is treated as unpriced rather than free.
     */
    private function parsePrice(string $value): ?float
    {
        $price = (float) preg_replace('/[₱,\s]/u', '', trim($value));

        return $price > 0 ? $price : null;
    }

    private function parseQuantity(string $value): int
    {
        return (int) preg_replace('/[^\d-]/', '', trim($value));
    }
}
