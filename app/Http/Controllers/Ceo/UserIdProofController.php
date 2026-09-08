<?php

namespace App\Http\Controllers\Ceo;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a registrant's ID proof to the Executive Officer.
 *
 * A narrow stand-in for the general `files.show` route and document policies,
 * which arrive with the rest of the document module. Approving a registration
 * without being able to open the ID is not a decision anyone can make.
 */
class UserIdProofController extends Controller
{
    public function __invoke(User $user, Document $document): StreamedResponse
    {
        abort_unless(
            $document->documentable_type === User::class
                && $document->documentable_id === $user->id
                && $document->document_type === 'other',
            404,
        );

        abort_unless(Storage::exists($document->file_path), 404);

        return Storage::response($document->file_path, $document->file_name);
    }
}
