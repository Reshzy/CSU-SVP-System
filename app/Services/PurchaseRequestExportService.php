<?php

namespace App\Services;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PurchaseRequestExportService
{
    private const int FIRST_ITEM_ROW = 11;

    private const int LAST_ITEM_ROW = 55;

    private const string LOT_CHILD_INDENT = '  ';

    public function download(PurchaseRequest $purchaseRequest): BinaryFileResponse
    {
        $template = storage_path('app/templates/PurchaseRequestTemplate.xlsx');

        if (! is_readable($template)) {
            throw new \RuntimeException('Purchase request Excel template is missing.');
        }

        $purchaseRequest->loadMissing(['items', 'requester.position', 'department']);

        $spreadsheet = IOFactory::load($template);
        $templateSheet = $spreadsheet->getActiveSheet();
        $lines = $this->orderedItems($purchaseRequest);
        $chunks = $lines->chunk(self::LAST_ITEM_ROW - self::FIRST_ITEM_ROW + 1)->values();

        if ($chunks->isEmpty()) {
            $chunks = collect([collect()]);
        }

        $ceoName = User::role('Executive Officer')->value('name');
        $stockNumber = 1;

        foreach ($chunks as $pageIndex => $chunk) {
            if ($pageIndex === 0) {
                $sheet = $templateSheet;
            } else {
                $sheet = clone $templateSheet;
                $sheet->setTitle('Page '.($pageIndex + 1));
                $spreadsheet->addSheet($sheet, $pageIndex);
            }

            $this->fillHeader($sheet, $purchaseRequest, $ceoName);
            $this->fillItems($sheet, $chunk->values(), $stockNumber);
            $stockNumber += $chunk->where('priced', true)->count();
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

    private function fillHeader(Worksheet $sheet, PurchaseRequest $purchaseRequest, ?string $ceoName): void
    {
        $sheet->setCellValue('D7', $purchaseRequest->pr_number);
        $sheet->setCellValue('F7', 'Date: '.optional($purchaseRequest->created_at)->format('Y-m-d'));
        $sheet->setCellValue('B58', $purchaseRequest->purpose);
        $sheet->setCellValue('B66', $purchaseRequest->requester?->name);
        $sheet->setCellValue('B67', $purchaseRequest->requester?->position?->name);
        $sheet->setCellValue('E66', $ceoName);
    }

    /**
     * @param  Collection<int, array{item: PurchaseRequestItem, priced: bool}>  $rows
     */
    private function fillItems(Worksheet $sheet, Collection $rows, int $startingNumber): void
    {
        $this->clearItemRows($sheet);

        $rowNumber = self::FIRST_ITEM_ROW;
        $stockNumber = $startingNumber;

        foreach ($rows as $row) {
            $item = $row['item'];
            $sheet->getStyle("C{$rowNumber}")->getAlignment()->setWrapText(true);

            if ($row['priced']) {
                $sheet->setCellValue("A{$rowNumber}", $stockNumber);
                $sheet->setCellValue("B{$rowNumber}", $this->unit($item));
                $sheet->setCellValue("C{$rowNumber}", $this->description($item));
                $sheet->setCellValue("E{$rowNumber}", $item->quantity_requested);
                $sheet->setCellValue("F{$rowNumber}", $item->estimated_unit_cost);
                $sheet->setCellValue("G{$rowNumber}", "=F{$rowNumber}*E{$rowNumber}");
                $stockNumber++;
            } else {
                $sheet->setCellValue("C{$rowNumber}", $this->childDescription($item));
                $sheet->setCellValue("G{$rowNumber}", null);
            }

            $sheet->getStyle("C{$rowNumber}")->getFont()->setBold($item->is_lot);

            $rowNumber++;
        }
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

    private function childDescription(PurchaseRequestItem $item): string
    {
        return self::LOT_CHILD_INDENT.($item->item_name ?? '');
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
