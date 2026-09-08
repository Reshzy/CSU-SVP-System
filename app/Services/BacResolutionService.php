<?php

namespace App\Services;

use App\Models\Document;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use NumberToWords\NumberToWords;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use RuntimeException;

class BacResolutionService
{
    public function __construct(private PurchaseRequestActivityLogger $activityLogger) {}

    public function generateResolution(PurchaseRequest $purchaseRequest, User $generatedBy): Document
    {
        if (! filled($purchaseRequest->resolution_number)) {
            throw new RuntimeException('A resolution number is required before generating the document.');
        }

        $purchaseRequest->loadMissing(['department:id,name', 'requester:id,name']);

        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Century Gothic');
        $phpWord->setDefaultFontSize(10);

        $section = $phpWord->addSection([
            'orientation' => 'portrait',
        ]);

        $center = ['alignment' => Jc::CENTER];
        $justify = ['alignment' => Jc::BOTH];

        $section->addText('Republic of the Philippines', ['bold' => true], $center);
        $section->addText('Cagayan State University', ['bold' => true], $center);
        $section->addText('BIDS AND AWARDS COMMITTEE', ['bold' => true], $center);
        $section->addTextBreak(1);
        $section->addText('RESOLUTION NO. '.$purchaseRequest->resolution_number, ['bold' => true], $center);
        $section->addTextBreak(1);

        $amount = $purchaseRequest->calculateTotalCost();
        $amountWords = $this->amountInWords($amount);
        $formattedAmount = number_format($amount, 2);

        $section->addText(
            'WHEREAS, the '.$purchaseRequest->department?->name.' submitted Purchase Request '.$purchaseRequest->pr_number.' for "'.$purchaseRequest->purpose.'" with an Approved Budget for the Contract of '.$amountWords.' (Php '.$formattedAmount.');',
            [],
            $justify,
        );
        $section->addTextBreak(1);
        $section->addText(
            'WHEREAS, the Bids and Awards Committee finds Small Value Procurement appropriate for this request;',
            [],
            $justify,
        );
        $section->addTextBreak(1);
        $section->addText(
            'RESOLVED, as it is hereby resolved, to recommend the use of Small Value Procurement for Purchase Request '.$purchaseRequest->pr_number.';',
            [],
            $justify,
        );
        $section->addTextBreak(2);

        $bacChair = User::role('BAC Chair')->orderBy('id')->first();
        $ceo = User::role('Executive Officer')->orderBy('id')->first();

        $section->addText('BAC Chairperson', ['bold' => true]);
        $section->addText($bacChair?->name ?? '________________');
        $section->addTextBreak(1);
        $section->addText('Executive Officer', ['bold' => true]);
        $section->addText($ceo?->name ?? '________________');

        Storage::disk('local')->makeDirectory('resolutions');

        $relativePath = 'resolutions/'.$purchaseRequest->resolution_number.'.docx';
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save(Storage::disk('local')->path($relativePath));

        $document = $purchaseRequest->documents()->create([
            'document_number' => Document::generateNextDocumentNumber(),
            'document_type' => 'bac_resolution',
            'title' => 'BAC Resolution '.$purchaseRequest->resolution_number,
            'file_path' => $relativePath,
            'file_name' => $purchaseRequest->resolution_number.'.docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_size' => Storage::disk('local')->size($relativePath),
            'uploaded_by' => $generatedBy->id,
            'is_public' => false,
            'visible_to_roles' => ['BAC Chair', 'BAC Secretariat', 'BAC Members', 'Executive Officer'],
            'status' => 'generated',
        ]);

        $this->activityLogger->logResolutionGenerated(
            $purchaseRequest,
            (string) $purchaseRequest->resolution_number,
            $generatedBy->id,
        );

        return $document;
    }

    private function amountInWords(float $amount): string
    {
        $pesos = (int) floor($amount);
        $centavos = (int) round(($amount - $pesos) * 100);
        $transformer = (new NumberToWords)->getNumberTransformer('en');

        $words = ucfirst($transformer->toWords($pesos)).' pesos';

        if ($centavos > 0) {
            $words .= ' and '.$transformer->toWords($centavos).' centavos';
        }

        return $words;
    }
}
