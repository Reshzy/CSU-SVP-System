<?php

namespace App\Services;

use App\Models\PurchaseRequest;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PurchaseRequestExportService
{
    public function download(PurchaseRequest $purchaseRequest): BinaryFileResponse
    {
        $template = storage_path('app/templates/PurchaseRequestTemplate.xlsx');

        if (! is_readable($template)) {
            throw new \RuntimeException('Purchase request Excel template is missing.');
        }

        $purchaseRequest->loadMissing(['items', 'requester', 'department']);

        $spreadsheet = IOFactory::load($template);
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('D7', $purchaseRequest->pr_number);
        $sheet->setCellValue('F7', optional($purchaseRequest->created_at)->format('Y-m-d'));
        $sheet->setCellValue('B58', $purchaseRequest->purpose);
        $sheet->setCellValue('B66', $purchaseRequest->requester?->name);
        $sheet->setCellValue('B67', $purchaseRequest->requester?->position?->name);

        $row = 11;

        foreach ($purchaseRequest->items as $item) {
            if ($row > 55) {
                break;
            }

            $sheet->setCellValue("A{$row}", $item->item_code);
            $sheet->setCellValue("B{$row}", $item->is_lot ? $item->lot_name : $item->item_name);
            $sheet->setCellValue("C{$row}", $item->unit_of_measure);
            $sheet->setCellValue("D{$row}", $item->quantity_requested);
            $sheet->setCellValue("E{$row}", $item->estimated_unit_cost);
            $sheet->setCellValue("F{$row}", $item->estimated_total_cost);
            $row++;
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
}
