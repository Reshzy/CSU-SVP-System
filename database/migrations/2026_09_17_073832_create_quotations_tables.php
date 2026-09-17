<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('quotations')) {
            Schema::create('quotations', function (Blueprint $table) {
                $table->id();
                $table->string('quotation_number')->unique();
                $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('pr_item_group_id')->nullable();
                $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
                $table->string('supplier_location')->nullable();
                $table->date('quotation_date')->nullable();
                $table->date('validity_date')->nullable();
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->boolean('exceeds_abc')->default(false);
                $table->string('bac_status')->default('pending_evaluation');
                $table->decimal('technical_score', 8, 2)->nullable();
                $table->decimal('financial_score', 8, 2)->nullable();
                $table->decimal('total_score', 8, 2)->nullable();
                $table->boolean('is_winning_bid')->default(false);
                $table->string('quotation_file_path')->nullable();
                $table->json('supporting_documents')->nullable();
                $table->timestamps();

                $table->unique(['purchase_request_id', 'supplier_id', 'pr_item_group_id'], 'quotations_pr_supplier_group_unique');
            });
        }

        if (! Schema::hasTable('quotation_items')) {
            Schema::create('quotation_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
                $table->foreignId('purchase_request_item_id')->constrained()->cascadeOnDelete();
                $table->decimal('unit_price', 15, 2)->nullable();
                $table->decimal('total_price', 15, 2)->nullable();
                $table->boolean('is_within_abc')->default(true);
                $table->unsignedInteger('rank')->nullable();
                $table->boolean('is_lowest')->default(false);
                $table->boolean('is_tied')->default(false);
                $table->boolean('is_winner')->default(false);
                $table->string('disqualification_reason')->nullable();
                $table->boolean('is_withdrawn')->default(false);
                $table->timestamp('withdrawn_at')->nullable();
                $table->text('withdrawal_reason')->nullable();
                $table->timestamps();

                $table->unique(['quotation_id', 'purchase_request_item_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
    }
};
