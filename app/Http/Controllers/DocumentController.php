<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function show(Document $document): StreamedResponse
    {
        $document->loadMissing('documentable');

        Gate::authorize('view', $document);

        $filePath = $document->file_path;
        $disk = null;

        if (Storage::disk('public')->exists($filePath)) {
            $disk = 'public';
        } elseif (Storage::disk('local')->exists($filePath)) {
            $disk = 'local';
        }

        if ($disk === null) {
            abort(404, 'Document file not found in storage.');
        }

        $mimeType = $document->mime_type ?: Storage::disk($disk)->mimeType($filePath);
        $fileName = $document->file_name ?: basename($filePath);
        $lowerName = strtolower($fileName);
        $forceDownload = str_ends_with($lowerName, '.docx')
            || str_ends_with($lowerName, '.xlsx')
            || str_ends_with($lowerName, '.pptx');
        $disposition = $forceDownload ? 'attachment' : 'inline';

        return Storage::disk($disk)->download($filePath, $fileName, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => $disposition.'; filename="'.$fileName.'"',
        ]);
    }
}
