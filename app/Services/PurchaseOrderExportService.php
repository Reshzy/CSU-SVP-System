<?php

namespace App\Services;

use App\Models\PoSignatory;
use App\Models\PurchaseOrder;
use NumberToWords\NumberToWords;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PurchaseOrderExportService
{
    public function download(PurchaseOrder $purchaseOrder): BinaryFileResponse
    {
        $template = storage_path('app/templates/PurchaseOrderTemplate.xlsx');

        if (! is_readable($template)) {
            throw new \RuntimeException('Purchase order Excel template is missing.');
        }

        $spreadsheet = IOFactory::load($template);
        $this->fillPoData($spreadsheet->getActiveSheet(), $purchaseOrder);

        $directory = storage_path('app/temp');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $tempPath = $directory.DIRECTORY_SEPARATOR.'PO-'.$purchaseOrder->id.'-'.time().'.xlsx';
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($tempPath);

        return response()
            ->download($tempPath, 'PO-'.$purchaseOrder->po_number.'.xlsx')
            ->deleteFileAfterSend(true);
    }

    private function fillPoData(Worksheet $sheet, PurchaseOrder $purchaseOrder): void
    {
        $purchaseOrder->loadMissing([
            'supplier',
            'items.purchaseRequestItem',
            'quotation.items.purchaseRequestItem',
            'purchaseRequest.items',
        ]);

        $supplierName = $purchaseOrder->supplier_name_override ?: ($purchaseOrder->supplier?->business_name ?? '');
        $sheet->setCellValue('C4', $supplierName);
        $sheet->setCellValue('C5', $purchaseOrder->supplier?->address ?? '');
        $sheet->setCellValue('C6', $purchaseOrder->tin ?: ($purchaseOrder->supplier?->tin ?? ''));
        $sheet->setCellValue('F4', $purchaseOrder->po_number);
        $sheet->setCellValue('F5', optional($purchaseOrder->po_date)->format('F d, Y'));

        $sheet->setCellValue('D57', $purchaseOrder->funds_cluster ?? '');

        if ($purchaseOrder->funds_available !== null) {
            $sheet->setCellValue('D58', $purchaseOrder->funds_available);
        }

        $sheet->setCellValue('G57', $purchaseOrder->ors_burs_no ?? '');

        if ($purchaseOrder->ors_burs_date) {
            $sheet->setCellValue('G58', $purchaseOrder->ors_burs_date->format('F d, Y'));
        }

        if ($purchaseOrder->total_amount !== null) {
            $sheet->setCellValue('G59', $purchaseOrder->total_amount);
        }

        $rowIndex = 14;

        foreach ($this->exportLines($purchaseOrder) as $line) {
            $sheet->setCellValue('C'.$rowIndex, $line['unit']);
            $sheet->setCellValue('D'.$rowIndex, $line['description']);
            $sheet->setCellValue('E'.$rowIndex, $line['quantity']);
            $sheet->setCellValue('F'.$rowIndex, $line['unit_cost']);
            $sheet->setCellValue('G'.$rowIndex, $line['amount']);
            $rowIndex++;
        }

        $sheet->setCellValue('D44', $this->amountInWords((float) $purchaseOrder->total_amount));
        $sheet->setCellValue('G44', $purchaseOrder->total_amount);

        $ceo = PoSignatory::query()->active()->position('ceo')->first();
        $chiefAccountant = PoSignatory::query()->active()->position('chief_accountant')->first();

        if ($ceo) {
            $sheet->setCellValue('E51', $ceo->formattedName());
        }

        if ($chiefAccountant) {
            $sheet->setCellValue('C61', $chiefAccountant->formattedName());
        }
    }

    /**
     * @return list<array{unit: string, description: string, quantity: int|float, unit_cost: mixed, amount: mixed}>
     */
    private function exportLines(PurchaseOrder $purchaseOrder): array
    {
        if ($purchaseOrder->items->isNotEmpty()) {
            return $purchaseOrder->items->map(function ($item): array {
                $prItem = $item->purchaseRequestItem;

                return [
                    'unit' => $prItem?->unit_of_measure ?? 'pcs',
                    'description' => $prItem?->item_name ?? '',
                    'quantity' => $item->quantity,
                    'unit_cost' => $item->unit_price,
                    'amount' => $item->total_price,
                ];
            })->all();
        }

        $quotationItems = $purchaseOrder->quotation?->items ?? collect();

        if ($quotationItems->isNotEmpty()) {
            return $quotationItems->map(function ($item): array {
                $prItem = $item->purchaseRequestItem;

                return [
                    'unit' => $prItem?->unit_of_measure ?? 'pcs',
                    'description' => $prItem?->item_name ?? '',
                    'quantity' => $prItem?->quantity_requested ?? 0,
                    'unit_cost' => $item->unit_price,
                    'amount' => $item->total_price,
                ];
            })->all();
        }

        return $purchaseOrder->purchaseRequest->items->map(function ($item): array {
            return [
                'unit' => $item->unit_of_measure ?? 'pcs',
                'description' => $item->item_name ?? '',
                'quantity' => $item->quantity_requested,
                'unit_cost' => $item->estimated_unit_cost,
                'amount' => $item->estimated_total_cost,
            ];
        })->all();
    }

    private function amountInWords(float $amount): string
    {
        $pesos = (int) floor($amount);
        $centavos = (int) round(($amount - $pesos) * 100);

        if ($centavos === 100) {
            $pesos++;
            $centavos = 0;
        }

        $words = ucwords((new NumberToWords)->getNumberTransformer('en')->toWords($pesos));

        return $words.' Pesos & '.str_pad((string) $centavos, 2, '0', STR_PAD_LEFT).'/100';
    }
}
