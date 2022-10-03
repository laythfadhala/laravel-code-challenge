<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\ReceivedRepayment;
use App\Models\ScheduledRepayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LoanService
{
    /**
     * Create a Loan
     *
     * @param  User  $user
     * @param  int  $amount
     * @param  string  $currencyCode
     * @param  int  $terms
     * @param  string  $processedAt
     *
     * @return Loan
     */
    public function createLoan(User $user, int $amount, string $currencyCode, int $terms, string $processedAt): Loan
    {
        try {

            DB::beginTransaction();
            // Create a loan
            $newLoan = Loan::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'terms' => $terms,
                'outstanding_amount' => $amount,
                'currency_code' => Loan::CURRENCY_VND,
                'processed_at' => $processedAt,
                'status' => Loan::STATUS_DUE,
            ]);

            $outstandingAmountPerMonth = intdiv($amount , 3); // the rounded number

            for ($i=1; $i <= $terms; $i++) {

                // * we calculate the remaining amount from the rounding method above, and add it to the last scheduled repayment below.
                $remainingAmount = $amount - ($outstandingAmountPerMonth * $terms);

                ScheduledRepayment::create([
                    'loan_id' => $newLoan->id,
                    'amount' => ($i == $terms) ? ($outstandingAmountPerMonth + $remainingAmount) : $outstandingAmountPerMonth,
                    'outstanding_amount' => ($i == $terms) ? ($outstandingAmountPerMonth + $remainingAmount) : $outstandingAmountPerMonth,
                    'currency_code' => $currencyCode,
                    'due_date' => date('Y-m-d', strtotime("+" . $i .  " months", strtotime($processedAt))),
                    'status' => ScheduledRepayment::STATUS_DUE,
                ]);
            }
            DB::commit();

            return $newLoan;

        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    /**
     * Repay Scheduled Repayments for a Loan
     *
     * @param  Loan  $loan
     * @param  int  $amount
     * @param  string  $currencyCode
     * @param  string  $receivedAt
     *
     * @return ReceivedRepayment
     */
    public function repayLoan(Loan $loan, int $receivedRepayment, string $currencyCode, string $receivedAt): ReceivedRepayment
    {
        try {
            DB::beginTransaction();

            $outstandingAmountPerMonth = intdiv($loan->amount , 3); // the rounded number

            $loan->update([
                'outstanding_amount' => $this->lastRepay($loan) ? 0 : $loan->amount - $receivedRepayment,
                'status' =>  $this->lastRepay($loan) ? ScheduledRepayment::STATUS_REPAID : ScheduledRepayment::STATUS_DUE,
            ]);

            $loan->scheduledRepayments->where(
                'due_date', $receivedAt)->where('status', ScheduledRepayment::STATUS_DUE)
                    ->first()->update([
                        'outstanding_amount' => ($receivedRepayment <= $outstandingAmountPerMonth) ?
                            ($outstandingAmountPerMonth - $receivedRepayment) : 0,

                        'status' => ($receivedRepayment < $outstandingAmountPerMonth) ?
                            ScheduledRepayment::STATUS_PARTIAL :
                            ScheduledRepayment::STATUS_REPAID,
            ]);

            if($receivedRepayment > $outstandingAmountPerMonth){
                $this->partialPayment($loan, $outstandingAmountPerMonth, $receivedRepayment);
            }

            $receivedPayment = ReceivedRepayment::create([
                'loan_id' => $loan->id,
                'amount' => $receivedRepayment,
                'currency_code' => $currencyCode,
                'received_at' => $receivedAt,
            ]);

            DB::commit();

            return $receivedPayment;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }

    }

    /**
     * Check if the repayment is the last scheduled repay
     *
     * @param  Loan  $loan
     */
    public function lastRepay($loan) : bool
    {
        return (ScheduledRepayment::where([
            'loan_id' => $loan->id,
            'status' => ScheduledRepayment::STATUS_DUE,
        ])->get()->count()) <= 1 ?  true : false;
    }

    /**
     * Check if the repayment is the last scheduled repay
     *
     * @param  Loan  $loan
     * @param int $outstandingAmountPerMonth
     * @param int $receivedRepayment
     */
    public function partialPayment($loan, $outstandingAmountPerMonth, $receivedRepayment) : int
    {
        $loan->scheduledRepayments->where('status', ScheduledRepayment::STATUS_DUE)
            ->first()?->update([
                'outstanding_amount' => $receivedRepayment - $outstandingAmountPerMonth,
                'status' => ScheduledRepayment::STATUS_PARTIAL
            ]);
        return $receivedRepayment - $outstandingAmountPerMonth;
    }
}
