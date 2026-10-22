<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique();
            $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pr_item_group_id')->nullable()->constrained('pr_item_groups')->nullOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->foreignId('quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->date('po_date');
            $table->decimal('total_amount', 15, 2)->nullable();
            $table->text('delivery_address');
            $table->date('delivery_date_required');
            $table->text('terms_and_conditions');
            $table->text('special_instructions')->nullable();
            $table->string('tin')->nullable();
            $table->string('supplier_name_override')->nullable();
            $table->string('funds_cluster')->nullable();
            $table->decimal('funds_available', 15, 2)->nullable();
            $table->string('ors_burs_no')->nullable();
            $table->date('ors_burs_date')->nullable();
            $table->string('status')->default('pending_approval');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('sent_to_supplier_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->date('actual_delivery_date')->nullable();
            $table->text('delivery_notes')->nullable();
            $table->boolean('delivery_complete')->default(false);
            $table->timestamps();

            $table->index(['status', 'po_date']);
            $table->index(['supplier_id', 'status']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('purchase_request_item_id')->constrained('purchase_request_items')->restrictOnDelete();
            $table->foreignId('quotation_item_id')->constrained('quotation_items')->restrictOnDelete();
            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total_price', 15, 2);
            $table->timestamps();

            $table->index(['purchase_order_id', 'purchase_request_item_id'], 'po_items_po_id_pr_item_id_index');
        });

        Schema::create('po_signatories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('manual_name')->nullable();
            $table->string('position');
            $table->string('prefix')->nullable();
            $table->string('suffix')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('inventory_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->date('received_date');
            $table->string('reference_no')->nullable();
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('received_by')->constrained('users');
            $table->timestamps();

            $table->index(['purchase_order_id', 'received_date']);
        });

        Schema::create('inventory_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_receipt_id')->constrained('inventory_receipts')->cascadeOnDelete();
            $table->string('description');
            $table->string('unit_of_measure', 50)->nullable();
            $table->decimal('quantity', 12, 2)->default(0);
            $table->decimal('unit_price', 15, 2)->nullable();
            $table->decimal('total_price', 15, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('disbursement_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_number')->unique();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->decimal('amount', 15, 2);
            $table->date('voucher_date');
            $table->string('status')->default('draft');
            $table->foreignId('prepared_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['status', 'voucher_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disbursement_vouchers');
        Schema::dropIfExists('inventory_receipt_items');
        Schema::dropIfExists('inventory_receipts');
        Schema::dropIfExists('po_signatories');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};
