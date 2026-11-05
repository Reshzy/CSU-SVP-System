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
            $stockNumber += $chunk->count();
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
     * @param  Collection<int, PurchaseRequestItem>  $items
     */
    private function fillItems(Worksheet $sheet, Collection $items, int $startingNumber): void
    {
        $this->clearItemRows($sheet);

        $row = self::FIRST_ITEM_ROW;
        $stockNumber = $startingNumber;

        foreach ($items as $item) {
            $sheet->setCellValue("A{$row}", $stockNumber);
            $sheet->setCellValue("B{$row}", $this->unit($item));
            $sheet->setCellValue("C{$row}", $this->description($item));
            $sheet->getStyle("C{$row}")->getAlignment()->setWrapText(true);
            $sheet->setCellValue("E{$row}", $item->quantity_requested);
            $sheet->setCellValue("F{$row}", $item->estimated_unit_cost);
            $sheet->setCellValue("G{$row}", "=F{$row}*E{$row}");

            $stockNumber++;
            $row++;
        }
    }

    private function clearItemRows(Worksheet $sheet): void
    {
        for ($row = self::FIRST_ITEM_ROW; $row <= self::LAST_ITEM_ROW; $row++) {
            foreach (['A', 'B', 'C', 'E', 'F'] as $column) {
                $sheet->setCellValue("{$column}{$row}", null);
            }

            $sheet->setCellValue("G{$row}", "=F{$row}*E{$row}");
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

    /**
     * Top-level lines only. A lot is one row; its children stay in the database
     * and are omitted from the printed form.
     *
     * @return Collection<int, PurchaseRequestItem>
     */
    private function orderedItems(PurchaseRequest $purchaseRequest): Collection
    {
        return $purchaseRequest->items
            ->whereNull('parent_lot_id')
            ->sortBy('id')
            ->values();
    }
}
