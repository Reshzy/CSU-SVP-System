<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catalog snapshot, lot shape, and PPMP remaining-qty stamps for create.
     * `pr_item_group_id` waits for BAC item groups.
     */
    public function up(): void
    {
        Schema::table('purchase_request_items', function (Blueprint $table) {
            $table->boolean('is_lot')->default(false)->after('ppmp_item_id');
            $table->string('lot_name')->nullable()->after('is_lot');
            $table->foreignId('parent_lot_id')->nullable()->after('lot_name')->constrained('purchase_request_items')->nullOnDelete();

            $table->string('item_code')->nullable()->after('parent_lot_id');
            $table->string('item_name')->default('')->after('item_code');
            $table->text('detailed_specifications')->nullable()->after('item_name');
            $table->string('unit_of_measure')->default('')->after('detailed_specifications');
            $table->decimal('estimated_unit_cost', 12, 2)->default(0)->after('quantity_requested');
            $table->decimal('estimated_total_cost', 15, 2)->default(0)->after('estimated_unit_cost');
            $table->string('item_category')->nullable()->after('estimated_total_cost');

            $table->unsignedInteger('ppmp_planned_qty_for_quarter')->nullable()->after('ppmp_quarter');
            $table->unsignedInteger('ppmp_remaining_qty_at_creation')->nullable()->after('ppmp_planned_qty_for_quarter');

            $table->string('item_status')->default('pending')->after('ppmp_remaining_qty_at_creation');
            $table->string('procurement_status')->default('pending')->after('item_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_request_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_lot_id');

            $table->dropColumn([
                'is_lot',
                'lot_name',
                'item_code',
                'item_name',
                'detailed_specifications',
                'unit_of_measure',
                'estimated_unit_cost',
                'estimated_total_cost',
                'item_category',
                'ppmp_planned_qty_for_quarter',
                'ppmp_remaining_qty_at_creation',
                'item_status',
                'procurement_status',
            ]);
        });
    }
};
