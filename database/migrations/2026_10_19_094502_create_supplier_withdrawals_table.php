<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_request_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pr_item_group_id')->nullable()->constrained()->nullOnDelete();
            $table->text('withdrawal_reason');
            $table->timestamp('withdrawn_at');
            $table->foreignId('withdrawn_by')->constrained('users');
            $table->foreignId('successor_quotation_item_id')->nullable()->constrained('quotation_items')->nullOnDelete();
            $table->boolean('resulted_in_failure')->default(false);
            $table->timestamps();

            $table->index(['quotation_item_id', 'withdrawn_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_withdrawals');
    }
};
