<?php

namespace App\Services;

use App\Models\Document;
use App\Models\PurchaseRequest;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

class BacRfqService
{
    public function __construct(private PurchaseRequestActivityLogger $activityLogger) {}

    public function generateRfq(PurchaseRequest $purchaseRequest): Document
    {
        abort_unless($purchaseRequest->hasBeenThroughBac(), 403);

        if (blank($purchaseRequest->rfq_number)) {
            $purchaseRequest->forceFill([
                'rfq_number' => PurchaseRequest::generateNextRfqNumber(),
            ])->saveQuietly();
        }

        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Century Gothic');
        $phpWord->setDefaultFontSize(10);
        $section = $phpWord->addSection();
        $section->addText('REQUEST FOR QUOTATION', ['bold' => true], ['alignment' => Jc::CENTER]);
        $section->addText("RFQ No. {$purchaseRequest->rfq_number}");
        $section->addText("PR No. {$purchaseRequest->pr_number}");
        $section->addTextBreak();

        foreach ($purchaseRequest->items()->orderBy('id')->get() as $item) {
            $indent = $item->parent_lot_id ? '    ' : '';
            $label = $item->is_lot ? $item->lot_name : "{$item->item_code} {$item->item_name}";
            $section->addText("{$indent}{$label} — qty {$item->quantity_requested} {$item->unit_of_measure}");
        }

        $section->addTextBreak(2);
        $section->addText('BAC Chairperson: ______________________');
        $section->addText('Canvassing Officer: ______________________');

        $relativePath = 'rfq/'.$purchaseRequest->rfq_number.'.docx';
        $absolutePath = Storage::disk('local')->path($relativePath);
        $directory = dirname($absolutePath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        IOFactory::createWriter($phpWord, 'Word2007')->save($absolutePath);

        $document = $purchaseRequest->documents()->updateOrCreate(
            [
                'document_type' => 'bac_rfq',
                'document_number' => $purchaseRequest->rfq_number,
            ],
            [
                'title' => "RFQ {$purchaseRequest->rfq_number}",
                'file_path' => $relativePath,
                'file_name' => $purchaseRequest->rfq_number.'.docx',
                'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'file_size' => filesize($absolutePath) ?: null,
                'version' => 1,
                'is_current_version' => true,
                'uploaded_by' => auth()->id(),
                'is_public' => false,
                'status' => 'active',
            ],
        );

        $this->activityLogger->log($purchaseRequest, 'rfq_generated', "RFQ {$purchaseRequest->rfq_number} generated.");

        return $document;
    }
}
