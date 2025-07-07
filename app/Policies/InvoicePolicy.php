<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class InvoicePolicy
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

    public function view(User $user, Invoice $invoice): bool
    {
        return $this->hasFinanceAccess($user);
    }

    public function create(User $user): bool
    {
        return $this->hasFinanceAccess($user);
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $this->hasFinanceAccess($user);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $this->hasFinanceAccess($user);
    }
}
