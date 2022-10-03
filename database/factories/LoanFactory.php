<?php

namespace Database\Factories;

use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Loan::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'amount' => $this->faker->randomDigit(),
            'terms' => $this->faker->randomDigit(),
            'outstanding_amount' => $this->faker->randomDigit(),
            'currency_code' => $this->faker->currencyCode(),
            'processed_at' => now(),
            'status' => 'due',
        ];
    }
}
