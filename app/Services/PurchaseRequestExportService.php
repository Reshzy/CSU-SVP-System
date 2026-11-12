<?php

namespace App\Services;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PurchaseRequestExportService
{
    private const int FIRST_ITEM_ROW = 11;

    private const int LAST_ITEM_ROW = 55;

    private const int SUBTOTAL_ROW = 56;

    private const int TOTAL_ROW = 57;

    private const string CONTINUED_LABEL = 'CONTINUED ON NEXT PAGE';

    private const string TOTAL_LABEL = 'TOTAL';

    private const float DESCRIPTION_WIDTH_MARGIN = 1.0;

    private const string LOT_CHILD_INDENT = '  ';

    public const string DEFAULT_APPROVER_DESIGNATION = 'Campus Executive Officer';

    public function __construct(private PurchaseRequestDescriptionLines $descriptionLines) {}

    public function download(PurchaseRequest $purchaseRequest): BinaryFileResponse
    {
        $template = storage_path('app/templates/PurchaseRequestTemplate.xlsx');

        if (! is_readable($template)) {
            throw new \RuntimeException('Purchase request Excel template is missing.');
        }

        $purchaseRequest->loadMissing(['items', 'requester.position', 'department']);

        $spreadsheet = IOFactory::load($template);
        $templateSheet = $spreadsheet->getActiveSheet();
        $templateSheet->getStyle('C'.self::LAST_ITEM_ROW)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        [$regularFont, $boldFont] = $this->descriptionFonts($templateSheet);
        $pages = $this->paginate(
            $this->orderedItems($purchaseRequest),
            $regularFont,
            $boldFont,
            $this->descriptionWidth($templateSheet),
        );

        $sheets = [$templateSheet];

        foreach ($pages as $pageIndex => $page) {
            if ($pageIndex === 0) {
                continue;
            }

            $sheet = clone $templateSheet;
            $sheet->setTitle('Page '.($pageIndex + 1));
            $spreadsheet->addSheet($sheet, $pageIndex);
            $sheets[] = $sheet;
        }

        if (count($pages) > 1) {
            $templateSheet->setTitle('Page 1');
        }

        $executiveOfficer = User::signingExecutiveOfficer();
        $stockNumber = 1;

        foreach ($sheets as $pageIndex => $sheet) {
            $this->fillHeader($sheet, $purchaseRequest, $executiveOfficer);
            $this->fillItems($sheet, $pages[$pageIndex], $stockNumber);
            $stockNumber += count(array_filter(
                $pages[$pageIndex],
                fn (array $line): bool => $line['showAmounts'],
            ));
            $this->fillFooter($sheet, $pageIndex + 1, $sheets);
        }

        $directory = storage_path('app/temp');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $tempPath = $directory.DIRECTORY_SEPARATOR.'PR-'.$purchaseRequest->id.'-'.time().'.xlsx';
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($tempPath);

        return response()
            ->download($tempPath, 'PR-'.$purchaseRequest->pr_number.'.xlsx')
            ->deleteFileAfterSend(true);
    }

    /**
     * The "Approved by" designation for the signing Executive Officer: their
     * own designation when set, otherwise the campus title.
     */
    public static function approverDesignation(?User $executiveOfficer): string
    {
        return filled($executiveOfficer?->designation)
            ? $executiveOfficer->designation
            : self::DEFAULT_APPROVER_DESIGNATION;
    }

    private function fillHeader(Worksheet $sheet, PurchaseRequest $purchaseRequest, ?User $executiveOfficer): void
    {
        $sheet->setCellValue('D7', $purchaseRequest->pr_number);
        $sheet->setCellValue('F7', 'Date: '.optional($purchaseRequest->created_at)->format('Y-m-d'));
        $sheet->setCellValue('B58', $purchaseRequest->purpose);
        $sheet->setCellValue('B66', $purchaseRequest->requester?->name);
        $sheet->setCellValue('B67', $purchaseRequest->requester?->signatureDesignation());
        $sheet->setCellValue('E66', $executiveOfficer?->name);
        $sheet->setCellValue('E67', self::approverDesignation($executiveOfficer));
    }

    /**
     * @param  list<array{item: PurchaseRequestItem, text: string, showAmounts: bool, bold: bool}>  $rows
     */
    private function fillItems(Worksheet $sheet, array $rows, int $startingNumber): void
    {
        $this->clearItemRows($sheet);

        $rowNumber = self::FIRST_ITEM_ROW;
        $stockNumber = $startingNumber;

        foreach ($rows as $row) {
            $sheet->getStyle("C{$rowNumber}")->getAlignment()->setWrapText(true);
            $sheet->setCellValue("C{$rowNumber}", $row['text']);
            $sheet->getStyle("C{$rowNumber}")->getFont()->setBold($row['bold']);

            if ($row['showAmounts']) {
                $item = $row['item'];
                $sheet->setCellValue("A{$rowNumber}", $stockNumber);
                $sheet->setCellValue("B{$rowNumber}", $this->unit($item));
                $sheet->setCellValue("E{$rowNumber}", $item->quantity_requested);
                $sheet->setCellValue("F{$rowNumber}", $item->estimated_unit_cost);
                $sheet->setCellValue("G{$rowNumber}", "=F{$rowNumber}*E{$rowNumber}");
                $stockNumber++;
            } else {
                $sheet->setCellValue("G{$rowNumber}", null);
            }

            $rowNumber++;
        }
    }

    /**
     * On multi-page exports, label each page, print its subtotal, and put the
     * grand total of every page's subtotal on the last page only. A single
     * page keeps the template footer untouched.
     *
     * @param  list<Worksheet>  $sheets
     */
    private function fillFooter(Worksheet $sheet, int $pageNumber, array $sheets): void
    {
        $pageCount = count($sheets);

        if ($pageCount === 1) {
            return;
        }

        $subtotalCell = 'G'.self::SUBTOTAL_ROW;

        $sheet->setCellValue('C'.self::SUBTOTAL_ROW, "Page {$pageNumber} of {$pageCount}");
        $sheet->setCellValue($subtotalCell, '=SUM(G'.self::FIRST_ITEM_ROW.':G'.self::LAST_ITEM_ROW.')');

        if ($pageNumber < $pageCount) {
            $sheet->setCellValue('C'.self::TOTAL_ROW, self::CONTINUED_LABEL);
            $sheet->setCellValue('G'.self::TOTAL_ROW, null);

            return;
        }

        $subtotals = array_map(
            fn (Worksheet $page): string => "'".str_replace("'", "''", $page->getTitle())."'!{$subtotalCell}",
            $sheets,
        );

        $sheet->setCellValue('C'.self::TOTAL_ROW, self::TOTAL_LABEL);
        $sheet->setCellValue('G'.self::TOTAL_ROW, '='.implode('+', $subtotals));
    }

    private function clearItemRows(Worksheet $sheet): void
    {
        for ($row = self::FIRST_ITEM_ROW; $row <= self::LAST_ITEM_ROW; $row++) {
            foreach (['A', 'B', 'C', 'E', 'F'] as $column) {
                $sheet->setCellValue("{$column}{$row}", null);
            }

            $sheet->setCellValue("G{$row}", "=F{$row}*E{$row}");
            $sheet->getStyle("C{$row}")->getFont()->setBold(false);
        }
    }

    /**
     * @param  Collection<int, array{item: PurchaseRequestItem, priced: bool}>  $lines
     * @return list<list<array{item: PurchaseRequestItem, text: string, showAmounts: bool, bold: bool}>>
     */
    private function paginate(Collection $lines, Font $regularFont, Font $boldFont, float $maxColumnWidth): array
    {
        $capacity = self::LAST_ITEM_ROW - self::FIRST_ITEM_ROW + 1;
        $pages = [];
        $page = [];
        $used = 0;

        foreach ($lines as $line) {
            $item = $line['item'];
            $bold = $line['priced'] && $item->is_lot;
            $wrapped = $this->descriptionLines->lines(
                $line['priced'] ? $this->description($item) : ($item->item_name ?? ''),
                $bold ? $boldFont : $regularFont,
                $maxColumnWidth,
                $line['priced'] ? '' : self::LOT_CHILD_INDENT,
            );
            $count = count($wrapped);
            $remaining = $capacity - $used;

            if ($count <= $remaining) {
                $this->appendLines($page, $item, $wrapped, $line['priced'], $bold);
                $used += $count;

                continue;
            }

            if ($count <= $capacity) {
                if ($page !== []) {
                    $pages[] = $page;
                }

                $page = [];
                $used = 0;
                $this->appendLines($page, $item, $wrapped, $line['priced'], $bold);
                $used += $count;

                continue;
            }

            $offset = 0;
            $firstSegment = true;

            while ($offset < $count) {
                if ($used === $capacity) {
                    $pages[] = $page;
                    $page = [];
                    $used = 0;
                }

                $take = min($capacity - $used, $count - $offset);
                $this->appendLines(
                    $page,
                    $item,
                    array_slice($wrapped, $offset, $take),
                    $firstSegment && $line['priced'],
                    $bold,
                );
                $offset += $take;
                $used += $take;
                $firstSegment = false;
            }
        }

        if ($page !== [] || $pages === []) {
            $pages[] = $page;
        }

        return $pages;
    }

    /**
     * @param  list<array{item: PurchaseRequestItem, text: string, showAmounts: bool, bold: bool}>  $page
     * @param  list<string>  $lines
     */
    private function appendLines(array &$page, PurchaseRequestItem $item, array $lines, bool $showAmounts, bool $bold): void
    {
        foreach ($lines as $index => $text) {
            $page[] = [
                'item' => $item,
                'text' => $text,
                'showAmounts' => $showAmounts && $index === 0,
                'bold' => $bold,
            ];
        }
    }

    /**
     * @return array{0: Font, 1: Font}
     */
    private function descriptionFonts(Worksheet $sheet): array
    {
        $source = $sheet->getStyle('C11')->getFont();
        $regular = new Font;
        $regular->setName($source->getName() ?? 'Arial');
        $regular->setSize($source->getSize() ?? 11);
        $regular->setBold(false);

        $bold = new Font;
        $bold->setName($source->getName() ?? 'Arial');
        $bold->setSize($source->getSize() ?? 11);
        $bold->setBold(true);

        return [$regular, $bold];
    }

    private function descriptionWidth(Worksheet $sheet): float
    {
        $width = $sheet->getColumnDimension('C')->getWidth()
            + $sheet->getColumnDimension('D')->getWidth();

        return max(1, $width - self::DESCRIPTION_WIDTH_MARGIN);
    }

    private function unit(PurchaseRequestItem $item): string
    {
        if ($item->is_lot) {
            return 'lot';
        }

        return $item->unit_of_measure ?? '';
    }

    private function description(PurchaseRequestItem $item): string
    {
        if ($item->is_lot) {
            return $item->lot_name ?: ($item->item_name ?? '');
        }

        return $item->item_name ?? '';
    }

    /**
     * Top-level lines, with each lot followed by one row per child. A child
     * prints its name only. The lot row already carries the group's quantity,
     * unit, and cost.
     *
     * @return Collection<int, array{item: PurchaseRequestItem, priced: bool}>
     */
    private function orderedItems(PurchaseRequest $purchaseRequest): Collection
    {
        $childrenByLot = $purchaseRequest->items
            ->whereNotNull('parent_lot_id')
            ->groupBy('parent_lot_id');

        return $purchaseRequest->items
            ->whereNull('parent_lot_id')
            ->sortBy('id')
            ->flatMap(function (PurchaseRequestItem $item) use ($childrenByLot): Collection {
                $rows = collect([
                    ['item' => $item, 'priced' => true],
                ]);

                if (! $item->is_lot) {
                    return $rows;
                }

                $children = $childrenByLot
                    ->get($item->id, collect())
                    ->sortBy('id')
                    ->map(fn (PurchaseRequestItem $child): array => [
                        'item' => $child,
                        'priced' => false,
                    ]);

                return $rows->concat($children);
            })
            ->values();
    }
}
