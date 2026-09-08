<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the 48 permissions and 12 roles the SVP workflow enforces.
 *
 * Names are contractual: route middleware and policies reference them as
 * literals (`role:Budget Office`, `can:edit-purchase-request`). Renaming any
 * entry here silently unauthorizes screens, so treat this list as fixed.
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * @var array<string, list<string>>
     */
    public const PERMISSION_GROUPS = [
        'purchase-request' => [
            'create-purchase-request',
            'view-purchase-request',
            'edit-purchase-request',
            'approve-purchase-request',
            'reject-purchase-request',
            'assign-pr-control-number',
        ],
        'budget' => [
            'view-budget-info',
            'create-earmark',
            'approve-earmark',
            'validate-budget',
        ],
        'bac' => [
            'view-bac-documents',
            'create-bac-resolution',
            'evaluate-quotations',
            'approve-abstract-quotation',
            'conduct-bac-meeting',
            'award-contract',
        ],
        'suppliers' => [
            'manage-suppliers',
            'view-supplier-info',
            'request-quotations',
            'evaluate-supplier-performance',
        ],
        'purchase-orders' => [
            'create-purchase-order',
            'approve-purchase-order',
            'send-po-to-supplier',
            'track-delivery',
            'accept-delivery',
        ],
        'documents' => [
            'upload-documents',
            'view-documents',
            'approve-documents',
            'archive-documents',
        ],
        'workflow' => [
            'view-workflow-status',
            'manage-approvals',
            'assign-tasks',
            'escalate-issues',
        ],
        'reports' => [
            'view-reports',
            'create-reports',
            'view-analytics',
            'export-data',
        ],
        'system' => [
            'manage-users',
            'manage-roles',
            'manage-permissions',
            'manage-ps-dbms',
            'view-consolidated-app',
            'system-configuration',
            'view-audit-logs',
        ],
        'accounting' => [
            'process-payments',
            'view-financial-data',
            'create-disbursement-voucher',
            'validate-costs',
        ],
    ];

    /**
     * @var list<string>
     */
    public const ROLES = [
        'System Admin',
        'End User',
        'Dean',
        'Supply Officer',
        'Budget Office',
        'BAC Chair',
        'BAC Members',
        'BAC Secretariat',
        'Canvassing Unit',
        'Executive Officer',
        'Accounting Office',
        'Supplier',
    ];

    /**
     * @return list<string>
     */
    public static function permissions(): array
    {
        return array_merge(...array_values(self::PERMISSION_GROUPS));
    }

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::permissions() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (self::ROLES as $role) {
            Role::findOrCreate($role, 'web');
        }

        foreach ($this->rolePermissions() as $role => $permissions) {
            Role::findByName($role, 'web')->syncPermissions($permissions);
        }

        Department::updateOrCreate(
            ['code' => 'ADMIN'],
            ['name' => 'Administrative Office', 'is_active' => true, 'is_archived' => false],
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return array<string, list<string>>
     */
    private function rolePermissions(): array
    {
        $all = self::permissions();

        $endUser = [
            'create-purchase-request',
            'view-purchase-request',
            'view-workflow-status',
            'upload-documents',
            'view-documents',
        ];

        $dean = [...$endUser, 'view-budget-info', 'view-reports'];

        return [
            // System Admin and Executive Officer also bypass every gate via
            // `Gate::before`; the full grant keeps `can:` middleware honest.
            'System Admin' => $all,
            'Executive Officer' => $all,
            'End User' => $endUser,
            'Dean' => $dean,
            'Supply Officer' => [
                'view-purchase-request',
                'edit-purchase-request',
                'assign-pr-control-number',
                'create-purchase-order',
                'send-po-to-supplier',
                'track-delivery',
                'accept-delivery',
                'manage-suppliers',
                'view-supplier-info',
                'request-quotations',
                'upload-documents',
                'view-documents',
                'view-workflow-status',
                'manage-approvals',
                'view-reports',
            ],
            'Budget Office' => [
                'view-purchase-request',
                'view-budget-info',
                'create-earmark',
                'approve-earmark',
                'validate-budget',
                'validate-costs',
                'view-workflow-status',
                'view-documents',
                'view-reports',
            ],
            'BAC Chair' => [
                ...$dean,
                'view-bac-documents',
                'create-bac-resolution',
                'evaluate-quotations',
                'approve-abstract-quotation',
                'conduct-bac-meeting',
                'award-contract',
                'view-supplier-info',
                'evaluate-supplier-performance',
                'manage-approvals',
                'approve-documents',
            ],
            'BAC Members' => [
                ...$dean,
                'view-bac-documents',
                'evaluate-quotations',
                'conduct-bac-meeting',
                'view-supplier-info',
            ],
            'BAC Secretariat' => [
                ...$dean,
                'view-bac-documents',
                'create-bac-resolution',
                'evaluate-quotations',
                'conduct-bac-meeting',
                'view-supplier-info',
                'request-quotations',
                'create-reports',
                'manage-ps-dbms',
                'view-consolidated-app',
            ],
            'Canvassing Unit' => [
                'view-purchase-request',
                'manage-suppliers',
                'view-supplier-info',
                'request-quotations',
                'evaluate-supplier-performance',
                'view-workflow-status',
                'upload-documents',
                'view-documents',
                'view-reports',
            ],
            'Accounting Office' => [
                'view-purchase-request',
                'process-payments',
                'view-financial-data',
                'create-disbursement-voucher',
                'validate-costs',
                'view-workflow-status',
                'view-documents',
                'view-reports',
            ],
            // Seeded but not routed; the supplier portal is a documented gap.
            'Supplier' => [
                'view-purchase-request',
                'view-supplier-info',
                'upload-documents',
                'view-documents',
                'track-delivery',
            ],
        ];
    }
}
