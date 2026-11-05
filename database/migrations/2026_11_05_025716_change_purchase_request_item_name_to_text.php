<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Item descriptions include specifications and can exceed a 255-character name.
     */
    public function up(): void
    {
        Schema::table('purchase_request_items', function (Blueprint $table) {
            $table->text('item_name')->nullable()->change();
        });
    }

    /**
     * Rolling back truncates descriptions longer than 255 characters.
     */
    public function down(): void
    {
        Schema::table('purchase_request_items', function (Blueprint $table) {
            $table->string('item_name')->nullable()->change();
        });
    }
};
