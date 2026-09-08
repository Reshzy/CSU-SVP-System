<?php

namespace App\Services;

use App\Models\PurchaseRequest;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EarmarkExportService
{
    public function download(PurchaseRequest $purchaseRequest): BinaryFileResponse
    {
        $template = storage_path('app/templates/EarmarkTemplate.xlsx');

        if (! is_file($template)) {
            throw ValidationException::withMessages([
                'template' => 'The earmark Excel template is missing.',
            ]);
        }

        $purchaseRequest->ensureEarmarkId();
        $purchaseRequest->save();

        $spreadsheet = IOFactory::load($template);
        $this->fillEarmarkData($spreadsheet->getActiveSheet(), $purchaseRequest);

        $tempDir = storage_path('app/temp');

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $tempPath = $tempDir.DIRECTORY_SEPARATOR.'EARMARK-'.$purchaseRequest->id.'-'.now()->format('YmdHis').'.xlsx';

        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($tempPath);

        return response()
            ->download($tempPath, 'Earmark-'.$purchaseRequest->earmark_id.'.xlsx')
            ->deleteFileAfterSend(true);
    }

    private function fillEarmarkData(Worksheet $sheet, PurchaseRequest $purchaseRequest): void
    {
        $printed = now()->format('F j, Y');
        $dateTo = $purchaseRequest->earmark_date_to?->format('F j, Y') ?? '';

        $sheet->setCellValue('A6', 'EARMARK NO. '.$purchaseRequest->earmark_id);
        $sheet->setCellValue('A7', "Dated: {$printed} to {$dateTo}");
        $sheet->setCellValue('B10', (string) $purchaseRequest->funding_source);
        $sheet->setCellValue('B11', (string) $purchaseRequest->legal_basis);
        $sheet->setCellValue('B12', (string) $purchaseRequest->requester?->name);
        $sheet->setCellValue('B13', (string) $purchaseRequest->current_step_notes);
        $sheet->setCellValue('B14', trim(sprintf(
            '%s / %s / %s',
            $purchaseRequest->pr_title ?: $purchaseRequest->purpose,
            $purchaseRequest->pr_number,
            $purchaseRequest->created_at?->format('F j, Y') ?? '',
        ), ' /'));
        $sheet->setCellValue('A16', (string) $purchaseRequest->earmark_programs_activities);
        $sheet->setCellValue('A17', (string) $purchaseRequest->earmark_responsibility_center);

        $row = 18;

        foreach ($purchaseRequest->earmark_object_expenditures ?? [] as $item) {
            $sheet->setCellValue('A'.$row, (string) ($item['description'] ?? ''));
            $sheet->setCellValue('C'.$row, $item['amount'] ?? '');
            $row++;
        }

        $sheet->setCellValue('B27', $printed);
    }
}
