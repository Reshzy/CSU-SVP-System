<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'aoq_reference_number',
    'purchase_request_id',
    'pr_item_group_id',
    'generated_by',
    'document_hash',
    'exported_data_snapshot',
    'file_path',
    'file_format',
    'supplier_count',
    'item_count',
])]
class AoqGeneration extends Model
{
    /**
     * @return BelongsTo<PurchaseRequest, $this>
     */
    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    protected function casts(): array
    {
        return [
            'exported_data_snapshot' => 'array',
        ];
    }
}
