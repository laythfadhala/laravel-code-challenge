<?php

namespace Database\Factories;

use App\Models\Loan;
use App\Models\ScheduledRepayment;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduledRepaymentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ScheduledRepayment::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            'loan_id' => Loan::factory(),
            'amount' => $this->faker->randomDigit(),
            'outstanding_amount' => $this->faker->randomDigit(),
            'currency_code' => 'VND',
            'due_date' => $this->faker->date(),
            'status' => ScheduledRepayment::STATUS_DUE,
        ];
    }
}
