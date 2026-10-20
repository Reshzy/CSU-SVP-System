<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backfill Slice 7 item columns skipped on Herd when Slice 6 returned early.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('purchase_request_items', 'pr_item_group_id')) {
            Schema::table('purchase_request_items', function (Blueprint $table): void {
                $table->unsignedBigInteger('pr_item_group_id')->nullable();
            });
        }

        if (! Schema::hasColumn('purchase_request_items', 'replacement_pr_id')) {
            Schema::table('purchase_request_items', function (Blueprint $table): void {
                $table->foreignId('replacement_pr_id')->nullable()->constrained('purchase_requests')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('purchase_request_items', 'failed_at')) {
            Schema::table('purchase_request_items', function (Blueprint $table): void {
                $table->timestamp('failed_at')->nullable();
            });
        }

        if (! Schema::hasColumn('purchase_request_items', 'failure_reason')) {
            Schema::table('purchase_request_items', function (Blueprint $table): void {
                $table->text('failure_reason')->nullable();
            });
        }
    }

    /**
     * Leave columns in place. Fresh installs already have them from Slice 6;
     * dropping them here would desync that earlier migration.
     */
    public function down(): void
    {
        //
    }
};
