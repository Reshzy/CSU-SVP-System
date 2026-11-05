<?php

use App\Services\PurchaseRequestDescriptionLines;
use PhpOffice\PhpSpreadsheet\Shared\Font as FontMetric;
use PhpOffice\PhpSpreadsheet\Style\Font;

test('wraps the next word onto a new line when it does not fit', function () {
    $font = purchaseRequestLineFont();
    $breaker = new PurchaseRequestDescriptionLines;
    $maxWidth = FontMetric::calculateColumnWidth($font, 'alpha');

    expect($breaker->lines('alpha beta', $font, $maxWidth))->toBe(['alpha', 'beta']);
});

test('keeps a hard line break', function () {
    $font = purchaseRequestLineFont();

    expect((new PurchaseRequestDescriptionLines)->lines("alpha\r\n\r\nbeta", $font, 1000))
        ->toBe(['alpha', '', 'beta']);
});

test('breaks a word that is wider than the row', function () {
    $font = purchaseRequestLineFont();
    $maxWidth = FontMetric::calculateColumnWidth($font, 'W');

    expect((new PurchaseRequestDescriptionLines)->lines('WWWW', $font, $maxWidth))
        ->toBe(['W', 'W', 'W', 'W']);
});

test('puts the prefix on every wrapped line', function () {
    $font = purchaseRequestLineFont();
    $prefix = '  ';
    $maxWidth = FontMetric::calculateColumnWidth($font, $prefix.'alpha');

    expect((new PurchaseRequestDescriptionLines)->lines('alpha beta', $font, $maxWidth, $prefix))
        ->toBe([$prefix.'alpha', $prefix.'beta']);
});

test('wraps a bold line sooner when bold text is wider', function () {
    $regular = purchaseRequestLineFont();
    $bold = purchaseRequestLineFont(true);
    $text = 'alpha beta';
    $maxWidth = FontMetric::calculateColumnWidth($regular, $text);
    $breaker = new PurchaseRequestDescriptionLines;

    expect($breaker->lines($text, $regular, $maxWidth))->toBe([$text])
        ->and($breaker->lines($text, $bold, $maxWidth))->not->toBe([$text]);
});

test('returns one empty line for an empty description', function () {
    expect((new PurchaseRequestDescriptionLines)->lines('', purchaseRequestLineFont(), 1000))->toBe(['']);
});

function purchaseRequestLineFont(bool $bold = false): Font
{
    $font = new Font;
    $font->setName('Arial');
    $font->setSize(11);
    $font->setBold($bold);

    return $font;
}
