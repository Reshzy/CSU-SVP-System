<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Shared\Font as FontMetric;
use PhpOffice\PhpSpreadsheet\Style\Font;

class PurchaseRequestDescriptionLines
{
    private const float BOLD_WIDTH_FACTOR = 1.1;

    private ?bool $boldAffectsMeasurement = null;

    /**
     * Wrap text so each returned line fits in one template row.
     * A prefix, such as a lot-child indent, is applied to every line and
     * counts toward the width.
     *
     * @return list<string>
     */
    public function lines(string $text, Font $font, float $maxColumnWidth, string $prefix = ''): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = [];

        foreach (explode("\n", $normalized) as $paragraph) {
            array_push($lines, ...$this->wrapParagraph($paragraph, $font, $maxColumnWidth, $prefix));
        }

        return $lines === [] ? [$prefix] : $lines;
    }

    /**
     * @return list<string>
     */
    private function wrapParagraph(string $paragraph, Font $font, float $maxColumnWidth, string $prefix): array
    {
        $words = preg_split('/\s+/u', trim($paragraph), -1, PREG_SPLIT_NO_EMPTY);

        if ($words === false || $words === []) {
            return [$prefix];
        }

        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;

            if ($this->fits($prefix.$candidate, $font, $maxColumnWidth)) {
                $current = $candidate;

                continue;
            }

            if ($current !== '') {
                $lines[] = $prefix.$current;
                $current = '';
            }

            if ($this->fits($prefix.$word, $font, $maxColumnWidth)) {
                $current = $word;

                continue;
            }

            $pieces = $this->breakWord($word, $font, $maxColumnWidth, $prefix);
            $current = array_pop($pieces);

            foreach ($pieces as $piece) {
                $lines[] = $prefix.$piece;
            }
        }

        $lines[] = $prefix.$current;

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function breakWord(string $word, Font $font, float $maxColumnWidth, string $prefix): array
    {
        $pieces = [];
        $current = '';

        foreach (mb_str_split($word) as $character) {
            $candidate = $current.$character;

            if ($current !== '' && ! $this->fits($prefix.$candidate, $font, $maxColumnWidth)) {
                $pieces[] = $current;
                $current = $character;

                continue;
            }

            $current = $candidate;
        }

        if ($current !== '') {
            $pieces[] = $current;
        }

        return $pieces === [] ? [''] : $pieces;
    }

    private function fits(string $text, Font $font, float $maxColumnWidth): bool
    {
        if ($text === '') {
            return true;
        }

        return $this->textWidth($text, $font) <= $maxColumnWidth;
    }

    private function textWidth(string $text, Font $font): float
    {
        $width = FontMetric::calculateColumnWidth($font, $text);

        if ($font->getBold() && ! $this->boldAffectsMeasurement($font)) {
            return $width * self::BOLD_WIDTH_FACTOR;
        }

        return $width;
    }

    private function boldAffectsMeasurement(Font $font): bool
    {
        if ($this->boldAffectsMeasurement !== null) {
            return $this->boldAffectsMeasurement;
        }

        $regular = new Font;
        $regular->setName($font->getName() ?? 'Arial');
        $regular->setSize($font->getSize() ?? 11);
        $regular->setBold(false);

        $bold = new Font;
        $bold->setName($font->getName() ?? 'Arial');
        $bold->setSize($font->getSize() ?? 11);
        $bold->setBold(true);

        $this->boldAffectsMeasurement = FontMetric::calculateColumnWidth($bold, 'MMMM')
            > FontMetric::calculateColumnWidth($regular, 'MMMM');

        return $this->boldAffectsMeasurement;
    }
}
