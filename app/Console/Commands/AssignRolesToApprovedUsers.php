<?php

namespace App\Console\Commands;

use App\Enums\ApprovalStatus;
use App\Models\User;
use App\Support\PositionRoleMap;
use Illuminate\Console\Command;

class AssignRolesToApprovedUsers extends Command
{
    protected $signature = 'users:assign-roles';

    protected $description = 'Give approved users a Spatie role based on their position';

    /**
     * Backfills roles for approved accounts that somehow have none, so role
     * middleware does not lock them out. Users who already hold any role are
     * skipped, since a CEO may have overridden the position mapping by hand.
     */
    public function handle(): int
    {
        $assigned = 0;

        User::query()
            ->where('approval_status', ApprovalStatus::Approved)
            ->with('position')
            ->each(function (User $user) use (&$assigned): void {
                if ($user->roles->isNotEmpty()) {
                    return;
                }

                $role = PositionRoleMap::roleFor($user->position?->name);
                $user->assignRole($role);
                $assigned++;

                $this->line("  {$user->email} → {$role}");
            });

        $this->info("Assigned roles to {$assigned} user(s).");

        return self::SUCCESS;
    }
}
