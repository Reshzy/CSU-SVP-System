<?php

namespace App\Services;

use App\Models\PurchaseRequest;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EarmarkExportService
{
    public function download(PurchaseRequest $purchaseRequest): BinaryFileResponse
    {
        $template = storage_path('app/templates/EarmarkTemplate.xlsx');

        if (! is_readable($template)) {
            throw new \RuntimeException('Earmark Excel template is missing.');
        }

        if (blank($purchaseRequest->earmark_id)) {
            $purchaseRequest->forceFill([
                'earmark_id' => PurchaseRequest::generateNextEarmarkId(),
            ])->saveQuietly();
        }

        $purchaseRequest->loadMissing(['requester', 'department']);

        $spreadsheet = IOFactory::load($template);
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A6', 'EARMARK NO. '.$purchaseRequest->earmark_id);
        $sheet->setCellValue(
            'A7',
            'Dated: '.now()->format('Y-m-d').' to '.optional($purchaseRequest->earmark_date_to)->format('Y-m-d'),
        );
        $sheet->setCellValue('B10', $purchaseRequest->funding_source);
        $sheet->setCellValue('B11', $purchaseRequest->legal_basis);
        $sheet->setCellValue('B12', $purchaseRequest->requester?->name);
        $sheet->setCellValue('B13', $purchaseRequest->current_step_notes);
        $sheet->setCellValue(
            'B14',
            trim(($purchaseRequest->pr_title ?? '').' / '.($purchaseRequest->pr_number ?? '').' / '.optional($purchaseRequest->created_at)->format('Y-m-d')),
        );
        $sheet->setCellValue('A16', $purchaseRequest->earmark_programs_activities);
        $sheet->setCellValue('A17', $purchaseRequest->earmark_responsibility_center);

        $row = 18;
        foreach ($purchaseRequest->earmark_object_expenditures ?? [] as $expenditure) {
            $sheet->setCellValue("A{$row}", $expenditure['description'] ?? $expenditure['code'] ?? '');
            $sheet->setCellValue("C{$row}", $expenditure['amount'] ?? 0);
            $row++;
        }

        $directory = storage_path('app/temp');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $tempPath = $directory.DIRECTORY_SEPARATOR.'EARMARK-'.$purchaseRequest->id.'-'.time().'.xlsx';
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($tempPath);

        return response()
            ->download($tempPath, 'Earmark-'.$purchaseRequest->earmark_id.'.xlsx')
            ->deleteFileAfterSend(true);
    }
}
