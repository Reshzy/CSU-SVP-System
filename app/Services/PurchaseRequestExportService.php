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
    public function download(PurchaseRequest $purchaseRequest): BinaryFileResponse
    {
        $template = storage_path('app/templates/PurchaseRequestTemplate.xlsx');

        if (! is_readable($template)) {
            throw new \RuntimeException('Purchase request Excel template is missing.');
        }

        $purchaseRequest->loadMissing(['items', 'requester.position', 'department']);

        $spreadsheet = IOFactory::load($template);
        $templateSheet = $spreadsheet->getActiveSheet();
        $chunks = $this->orderedItems($purchaseRequest)->chunk(45)->values();
        $pageCount = max(1, $chunks->count());
        $ceoName = User::role('Executive Officer')->value('name');

        foreach ($chunks as $pageIndex => $chunk) {
            if ($pageIndex === 0) {
                $sheet = $templateSheet;
            } else {
                $sheet = clone $templateSheet;
                $sheet->setTitle('Page '.($pageIndex + 1));
                $spreadsheet->addSheet($sheet, $pageIndex);
            }

            $this->fillHeader($sheet, $purchaseRequest, $ceoName);
            $this->fillItems($sheet, $chunk);
            $sheet->setCellValue('C56', ($pageIndex + 1).' of '.$pageCount);
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
        $sheet->setCellValue('F7', optional($purchaseRequest->created_at)->format('Y-m-d'));
        $sheet->setCellValue('B58', $purchaseRequest->purpose);
        $sheet->setCellValue('B66', $purchaseRequest->requester?->name);
        $sheet->setCellValue('B67', $purchaseRequest->requester?->position?->name);
        $sheet->setCellValue('E66', $ceoName);
    }

    /**
     * @param  Collection<int, PurchaseRequestItem>  $items
     */
    private function fillItems(Worksheet $sheet, Collection $items): void
    {
        $row = 11;

        foreach ($items as $item) {
            $name = $item->is_lot ? $item->lot_name : $item->item_name;
            $uom = $item->is_lot ? 'lot' : $item->unit_of_measure;
            $description = $item->parent_lot_id ? '    '.$name : $name;

            $sheet->setCellValue("A{$row}", $item->item_code);
            $sheet->setCellValue("B{$row}", $name);
            $sheet->setCellValue("C{$row}", $item->parent_lot_id ? $description : $uom);
            $sheet->setCellValue("D{$row}", $item->quantity_requested);
            $sheet->setCellValue("E{$row}", $item->estimated_unit_cost);
            $sheet->setCellValue("F{$row}", $item->estimated_total_cost);
            $row++;
        }
    }

    /**
     * @return Collection<int, PurchaseRequestItem>
     */
    private function orderedItems(PurchaseRequest $purchaseRequest): Collection
    {
        $items = $purchaseRequest->items;
        $ordered = collect();

        foreach ($items->whereNull('parent_lot_id') as $item) {
            $ordered->push($item);

            if ($item->is_lot) {
                $ordered = $ordered->concat($items->where('parent_lot_id', $item->id));
            }
        }

        return $ordered->values();
    }
}
