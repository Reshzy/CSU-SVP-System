<?php

namespace App\Http\Requests\Supply;

use App\Models\Supplier;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-suppliers') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $supplier = $this->route('supplier');
        $supplierId = $supplier instanceof Supplier ? $supplier->id : null;

        return [
            'business_name' => ['required', 'string', 'max:255'],
            'contact_person' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(Supplier::class, 'email')->ignore($supplierId)],
            'phone' => ['required', 'string', 'max:50'],
            'address' => ['required', 'string'],
            'tin' => ['nullable', 'string', 'max:50'],
            'business_type' => ['required', Rule::in(['sole_proprietorship', 'partnership', 'corporation', 'cooperative'])],
            'status' => ['required', Rule::in(['active', 'inactive', 'blacklisted', 'pending_verification'])],
        ];
    }
}
