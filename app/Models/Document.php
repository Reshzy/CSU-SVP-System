<?php

namespace App\Models;

use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Polymorphic file record. Slice 1 only writes user ID proofs
 * (`document_type = other` morphed to a `User`); purchase request attachments
 * and generated BAC/PO documents land here in later slices.
 *
 * @property int $id
 * @property string $documentable_type
 * @property int $documentable_id
 * @property string|null $document_number
 * @property string $document_type
 * @property string $title
 * @property string|null $description
 * @property string $file_path
 * @property string $file_name
 * @property string|null $mime_type
 * @property int|null $file_size
 * @property int $version
 * @property int|null $previous_version_id
 * @property bool $is_current_version
 * @property int|null $uploaded_by
 * @property bool $is_public
 * @property array<int, string>|null $visible_to_roles
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'document_number',
    'document_type',
    'title',
    'description',
    'file_path',
    'file_name',
    'mime_type',
    'file_size',
    'version',
    'previous_version_id',
    'is_current_version',
    'uploaded_by',
    'is_public',
    'visible_to_roles',
    'status',
])]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    /**
     * Next file record number for the given month: DOC-MMYY-####.
     */
    public static function generateNextDocumentNumber(?Carbon $asOf = null): string
    {
        $asOf ??= now();
        $prefix = 'DOC-'.$asOf->format('my').'-';

        $last = static::query()
            ->where('document_number', 'like', $prefix.'%')
            ->orderByDesc('document_number')
            ->value('document_number');

        $nextSequence = 1;

        if (is_string($last)) {
            $parts = explode('-', $last);
            $nextSequence = ((int) end($parts)) + 1;
        }

        return $prefix.str_pad((string) $nextSequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'version' => 'integer',
            'is_current_version' => 'boolean',
            'is_public' => 'boolean',
            'visible_to_roles' => 'array',
        ];
    }
}
