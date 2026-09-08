<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('password')->constrained()->nullOnDelete();
            $table->foreignId('position_id')->nullable()->after('department_id')->constrained()->nullOnDelete();
            $table->string('employee_id')->nullable()->after('position_id');
            $table->string('phone')->nullable()->after('employee_id');
            $table->boolean('is_active')->default(false)->after('phone');
            $table->boolean('is_archived')->default(false)->after('is_active');
            $table->string('approval_status')->default('pending')->after('is_archived');
            $table->timestamp('approved_at')->nullable()->after('approval_status');
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
            $table->foreignId('approved_by')->nullable()->after('rejected_at')->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by')->nullable()->after('approved_by')->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable()->after('rejected_by');

            $table->index('approval_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('position_id');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('rejected_by');

            $table->dropIndex(['approval_status']);

            $table->dropColumn([
                'employee_id',
                'phone',
                'is_active',
                'is_archived',
                'approval_status',
                'approved_at',
                'rejected_at',
                'rejection_reason',
            ]);
        });
    }
};
