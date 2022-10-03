<?php

namespace App\Policies; //! changed because it was a wrong namespace

use App\Models\DebitCard;
use App\Models\DebitCardTransaction;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\Route;

/**
 * Class DebitCardTransactionPolicy
 */
class DebitCardTransactionPolicy
{
    use HandlesAuthorization;

    /**
     * View a Debit Transaction
     *
     * @param User           $user
     * @param DebitCardTransaction $debitCardTransaction
     *
     * @return bool
     */
    public function view(User $user, ?DebitCardTransaction $debitCardTransaction = null): bool
    {
        if (! isset($debitCardTransaction) && DebitCard::where(['user_id' => $user->id, 'id' => request('debitCard')])->exists()) {
            return true;
        } elseif (isset($debitCardTransaction)) {
            return $user->is($debitCardTransaction->debitCard->user);
        }
        return false;
    }

    /**
     *
     *  Create a Debit card transaction
     *
     * @param User      $user
     * @param DebitCard $debitCard
     *
     * @return bool
     */
    public function create(User $user, DebitCard $debitCard): bool
    {
        return $user->is($debitCard->user);
    }
}
