<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Slice 2 only stored the columns remaining-quantity math reads. This
     * adds the file-02 hub fields used from create onward. Later slices
     * fill earmark, replacement, and BAC columns; they stay nullable here.
     */
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->string('pr_number')->nullable()->unique()->after('id');
            $table->string('pr_title')->nullable()->after('pr_number');

            $table->string('purpose')->default('')->after('requester_id');
            $table->text('justification')->nullable()->after('purpose');
            $table->date('date_needed')->nullable()->after('justification');
            $table->decimal('estimated_total', 15, 2)->default(0)->after('date_needed');

            $table->foreignId('current_handler_id')->nullable()->after('department_id')->constrained('users')->nullOnDelete();
            $table->foreignId('returned_by')->nullable()->after('current_handler_id')->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by')->nullable()->after('returned_by')->constrained('users')->nullOnDelete();

            $table->string('funding_source')->nullable()->after('estimated_total');
            $table->string('fund_cluster_code', 2)->nullable()->after('funding_source');
            $table->text('fund_details')->nullable()->after('fund_cluster_code');
            $table->string('budget_code')->nullable()->after('fund_details');

            $table->string('procurement_type')->nullable()->after('budget_code');
            $table->string('procurement_method')->nullable()->after('procurement_type');
            $table->timestamp('procurement_method_set_at')->nullable()->after('procurement_method');
            $table->foreignId('procurement_method_set_by')->nullable()->after('procurement_method_set_at')->constrained('users')->nullOnDelete();

            $table->unsignedTinyInteger('pr_quarter')->nullable()->after('status');
            $table->text('current_step_notes')->nullable()->after('is_archived');
            $table->boolean('has_ppmp')->default(false)->after('current_step_notes');
            $table->string('ppmp_reference')->nullable()->after('has_ppmp');

            $table->string('earmark_id')->nullable()->after('ppmp_reference');
            $table->text('legal_basis')->nullable()->after('earmark_id');
            $table->text('earmark_programs_activities')->nullable()->after('legal_basis');
            $table->string('earmark_responsibility_center')->nullable()->after('earmark_programs_activities');
            $table->date('earmark_date_to')->nullable()->after('earmark_responsibility_center');
            $table->json('earmark_object_expenditures')->nullable()->after('earmark_date_to');

            $table->string('resolution_number')->nullable()->after('earmark_object_expenditures');
            $table->string('rfq_number')->nullable()->after('resolution_number');

            $table->foreignId('replaces_pr_id')->nullable()->after('rfq_number')->constrained('purchase_requests')->nullOnDelete();
            $table->foreignId('replaced_by_pr_id')->nullable()->after('replaces_pr_id')->constrained('purchase_requests')->nullOnDelete();

            $table->text('return_remarks')->nullable()->after('replaced_by_pr_id');
            $table->text('rejection_reason')->nullable()->after('return_remarks');

            $table->timestamp('submitted_at')->nullable()->after('rejection_reason');
            $table->timestamp('approved_at')->nullable()->after('submitted_at');
            $table->timestamp('completed_at')->nullable()->after('approved_at');
            $table->timestamp('returned_at')->nullable()->after('completed_at');
            $table->timestamp('rejected_at')->nullable()->after('returned_at');
            $table->timestamp('status_updated_at')->nullable()->after('rejected_at');

            $table->index(['requester_id', 'is_archived']);
            $table->index(['department_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropIndex(['requester_id', 'is_archived']);
            $table->dropIndex(['department_id', 'status']);

            $table->dropConstrainedForeignId('current_handler_id');
            $table->dropConstrainedForeignId('returned_by');
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropConstrainedForeignId('procurement_method_set_by');
            $table->dropConstrainedForeignId('replaces_pr_id');
            $table->dropConstrainedForeignId('replaced_by_pr_id');

            $table->dropColumn([
                'pr_number',
                'pr_title',
                'purpose',
                'justification',
                'date_needed',
                'estimated_total',
                'funding_source',
                'fund_cluster_code',
                'fund_details',
                'budget_code',
                'procurement_type',
                'procurement_method',
                'procurement_method_set_at',
                'pr_quarter',
                'current_step_notes',
                'has_ppmp',
                'ppmp_reference',
                'earmark_id',
                'legal_basis',
                'earmark_programs_activities',
                'earmark_responsibility_center',
                'earmark_date_to',
                'earmark_object_expenditures',
                'resolution_number',
                'rfq_number',
                'return_remarks',
                'rejection_reason',
                'submitted_at',
                'approved_at',
                'completed_at',
                'returned_at',
                'rejected_at',
                'status_updated_at',
            ]);
        });
    }
};
