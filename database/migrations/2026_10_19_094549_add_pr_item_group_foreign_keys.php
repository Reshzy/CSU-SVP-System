<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('purchase_request_items', 'pr_item_group_id')) {
            Schema::table('purchase_request_items', function (Blueprint $table): void {
                $table->foreign('pr_item_group_id')->references('id')->on('pr_item_groups')->nullOnDelete();
            });
        }

        Schema::table('quotations', function (Blueprint $table) {
            $table->foreign('pr_item_group_id')->references('id')->on('pr_item_groups')->nullOnDelete();
        });

        Schema::table('aoq_generations', function (Blueprint $table) {
            $table->foreign('pr_item_group_id')->references('id')->on('pr_item_groups')->nullOnDelete();
        });

        Schema::table('rfq_signatories', function (Blueprint $table) {
            $table->foreign('rfq_generation_id')->references('id')->on('rfq_generations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rfq_signatories', function (Blueprint $table) {
            $table->dropForeign(['rfq_generation_id']);
        });

        Schema::table('aoq_generations', function (Blueprint $table) {
            $table->dropForeign(['pr_item_group_id']);
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropForeign(['pr_item_group_id']);
        });

        if (Schema::hasColumn('purchase_request_items', 'pr_item_group_id')) {
            Schema::table('purchase_request_items', function (Blueprint $table): void {
                $table->dropForeign(['pr_item_group_id']);
            });
        }
    }
};
