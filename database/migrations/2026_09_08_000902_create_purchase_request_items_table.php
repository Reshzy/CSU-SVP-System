<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Slice 2 stub. Carries only the PPMP linkage that remaining-quantity math
     * consumes. Slice 3 adds the catalog snapshot, lots, item statuses, and the
     * remaining PPMP snapshot columns.
     */
    public function up(): void
    {
        Schema::create('purchase_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ppmp_item_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('ppmp_quarter')->nullable();
            $table->integer('quantity_requested');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_request_items');
    }
};
