<?php

namespace App\Services;

use App\Models\Document;
use App\Models\PurchaseRequest;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

class BacResolutionService
{
    public function __construct(private PurchaseRequestActivityLogger $activityLogger) {}

    public function generateResolution(PurchaseRequest $purchaseRequest): ?Document
    {
        try {
            if (blank($purchaseRequest->resolution_number)) {
                $purchaseRequest->forceFill([
                    'resolution_number' => PurchaseRequest::generateNextResolutionNumber(),
                ])->saveQuietly();
            }

            $phpWord = new PhpWord;
            $phpWord->setDefaultFontName('Century Gothic');
            $phpWord->setDefaultFontSize(10);

            $section = $phpWord->addSection([
                'orientation' => 'portrait',
                'marginTop' => 720,
                'marginBottom' => 720,
                'marginLeft' => 720,
                'marginRight' => 720,
            ]);

            $section->addText('BIDS AND AWARDS COMMITTEE', ['bold' => true], ['alignment' => Jc::CENTER]);
            $section->addText('RESOLUTION', ['bold' => true], ['alignment' => Jc::CENTER]);
            $section->addTextBreak();
            $section->addText("Resolution No. {$purchaseRequest->resolution_number}");
            $section->addTextBreak();
            $section->addText(
                "WHEREAS, the purchase request {$purchaseRequest->pr_number} titled \"{$purchaseRequest->pr_title}\" with an estimated total of {$purchaseRequest->estimated_total} has been approved for Small Value Procurement;",
            );
            $section->addTextBreak();
            $section->addText(
                'RESOLVED, as it is hereby resolved, that the Bids and Awards Committee endorse the conduct of canvassing and abstract of quotations for the said purchase request.',
            );
            $section->addTextBreak(2);
            $section->addText('Prepared for BAC and CEO sign-off.');

            $relativePath = 'resolutions/'.$purchaseRequest->resolution_number.'.docx';
            $absolutePath = Storage::disk('local')->path($relativePath);
            $directory = dirname($absolutePath);

            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            IOFactory::createWriter($phpWord, 'Word2007')->save($absolutePath);

            $document = $purchaseRequest->documents()->create([
                'document_number' => $purchaseRequest->resolution_number,
                'document_type' => 'bac_resolution',
                'title' => "BAC Resolution {$purchaseRequest->resolution_number}",
                'file_path' => $relativePath,
                'file_name' => $purchaseRequest->resolution_number.'.docx',
                'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'file_size' => filesize($absolutePath) ?: null,
                'version' => 1,
                'is_current_version' => true,
                'uploaded_by' => auth()->id(),
                'is_public' => false,
                'status' => 'active',
            ]);

            $this->activityLogger->log(
                $purchaseRequest,
                'resolution_generated',
                "Resolution {$purchaseRequest->resolution_number} generated.",
            );

            return $document;
        } catch (\Throwable $exception) {
            report($exception);

            return null;
        }
    }
}
