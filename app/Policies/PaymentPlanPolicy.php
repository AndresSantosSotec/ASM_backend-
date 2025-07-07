<?php

namespace App\Policies;

use App\Models\PaymentPlan;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PaymentPlanPolicy
{
    use HandlesAuthorization;

    private function hasFinanceAccess(User $user): bool
    {
        return in_array(strtoupper($user->rol), ['ADMIN', 'FINANZAS']);
    }

    public function viewAny(User $user): bool
    {
        return $this->hasFinanceAccess($user);
    }

    public function view(User $user, PaymentPlan $paymentPlan): bool
    {
        return $this->hasFinanceAccess($user);
    }

    public function create(User $user): bool
    {
        return $this->hasFinanceAccess($user);
    }

    public function update(User $user, PaymentPlan $paymentPlan): bool
    {
        return $this->hasFinanceAccess($user);
    }

    public function delete(User $user, PaymentPlan $paymentPlan): bool
    {
        return $this->hasFinanceAccess($user);
    }
}
