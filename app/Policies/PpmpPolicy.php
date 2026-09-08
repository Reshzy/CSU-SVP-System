<?php

namespace App\Policies;

use App\Models\Ppmp;
use App\Models\User;

/**
 * A PPMP belongs to one department, and only that department plans against it.
 * System Admin and Executive Officer reach every plan through the `Gate::before`
 * in `AppServiceProvider`.
 */
class PpmpPolicy
{
    public function view(User $user, Ppmp $ppmp): bool
    {
        return $this->belongsToSameDepartment($user, $ppmp);
    }

    public function update(User $user, Ppmp $ppmp): bool
    {
        return $this->belongsToSameDepartment($user, $ppmp);
    }

    /**
     * Validating locks the plan in as the basis for purchase requests.
     */
    public function validatePlan(User $user, Ppmp $ppmp): bool
    {
        return $this->belongsToSameDepartment($user, $ppmp);
    }

    private function belongsToSameDepartment(User $user, Ppmp $ppmp): bool
    {
        return $user->department_id !== null
            && $user->department_id === $ppmp->department_id;
    }
}
