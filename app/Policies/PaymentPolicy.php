<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PaymentPolicy
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

    public function view(User $user, Payment $payment): bool
    {
        return $this->hasFinanceAccess($user);
    }

    public function create(User $user): bool
    {
        return $this->hasFinanceAccess($user);
    }

    public function update(User $user, Payment $payment): bool
    {
        return $this->hasFinanceAccess($user);
    }

    public function delete(User $user, Payment $payment): bool
    {
        return $this->hasFinanceAccess($user);
    }
}
