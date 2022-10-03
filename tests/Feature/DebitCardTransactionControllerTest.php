<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\DebitCard;
use Laravel\Passport\Passport;
use App\Models\DebitCardTransaction;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DebitCardTransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected DebitCard $debitCard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['id' => 999]);
        Passport::actingAs($this->user);
    }

    // * The user model has many debit cards and each card has many transactions.
    // * This means we need to pass the debit card id in order to get all its transactions
    // * And the route should look like this: api/debit-card-transactions/{debitCardId}
    public function testCustomerCanSeeAListOfDebitCardTransactions()
    {
        // * get api/debit-card-transactions/{debitCardId}

        $debitCard = DebitCard::factory()->active()->create([
            'user_id' => $this->user->id
        ]);

        // check if user is authenticated
        $this->assertAuthenticatedAs($this->user);

        // create some transactions for the above debit card
        $debitCardTransactions =  DebitCardTransaction::factory()->count(10)->create([
            'debit_card_id' => $debitCard->id
        ]);

        $this->getJson('api/debit-card-transactions/' .  $debitCard->id)
            ->assertOk()
            ->assertJsonCount($debitCardTransactions->count())
            ->assertJson(
                fn (AssertableJson $json) =>
                $json->first(
                    fn ($json) => $json
                    ->where('amount', intval($debitCardTransactions->first()->amount))
                    ->where('currency_code', $debitCardTransactions->first()->currency_code)
                    ->etc()
                )
            );
    }

    // * The user model has many debit cards and each card has many transactions.
    // * This means we need to pass the debit card id in order to get all its transactions
    // * And the route should look like this: api/debit-card-transactions/{debitCardId}
    public function testCustomerCannotSeeAListOfDebitCardTransactionsOfOtherCustomerDebitCard()
    {
        // * get api/debit-card-transactions/{debitCardId}

        // create another user
        $anotherUser = User::factory()->create();

        // create a debit card for the other user
        $debitCard = DebitCard::factory()->active()->create([
            'user_id' => $anotherUser->id
        ]);

        // check if user is authenticated
        $this->assertAuthenticatedAs($this->user);

        // check the user can not see debit card transactions for other user/s
        $this->getJson('api/debit-card-transactions/' .  $debitCard->id)
            ->assertForbidden();
    }

    public function testCustomerCanCreateADebitCardTransaction()
    {
        // * post api/debit-card-transactions

        // create a debit card for the other user
        $debitCard = DebitCard::factory()->active()->create([
            'user_id' => $this->user->id
        ]);

        // check if user is authenticated
        $this->assertAuthenticatedAs($this->user);

        $this->postJson('api/debit-card-transactions', [
            'debit_card_id' => $debitCard->id,
            'amount' => 1000,
            'currency_code' => 'EUR',
        ])
            ->assertValid(['type']) // correctly validated
            ->assertCreated() // returned status 201
            ->assertJson(
                fn ($json) => $json
                ->where('amount', 1000)
                ->where('currency_code', 'EUR')
                ->etc()
            )
            ->assertJsonStructure([  // check if the return json has right keys
                'amount',
                'currency_code',
            ]);

        $this->assertDatabaseHas('debit_card_transactions', [
            'debit_card_id' => $debitCard->id,
            'amount' => 1000,
            'currency_code' => 'EUR',
        ]);
    }

    public function testCustomerCannotCreateADebitCardTransactionToOtherCustomerDebitCard()
    {
        // * post api/debit-card-transactions

        // create another user
        $anotherUser = User::factory()->create();

        // create a debit card for the other user
        $debitCard = DebitCard::factory()->active()->create([
            'user_id' => $anotherUser->id
        ]);

        // check if user is authenticated
        $this->assertAuthenticatedAs($this->user);

        $this->postJson('api/debit-card-transactions', [
            'debit_card_id' => $debitCard->id,
            'amount' => 1000,
            'currency_code' => 'EUR',
        ])
            ->assertValid(['type']) // correctly validated
            ->assertForbidden(); // returned status 403

        $this->assertDatabaseMissing('debit_card_transactions', [
            'debit_card_id' => $debitCard->id,
            'amount' => 1000,
            'currency_code' => 'EUR',
        ]);
    }

    public function testCustomerCanSeeADebitCardTransaction()
    {
        // * get api/debit-card-transactions/{debitCardTransaction}

        $debitCard = DebitCard::factory()->active()->create([
            'user_id' => $this->user->id
        ]);

        // check if user is authenticated
        $this->assertAuthenticatedAs($this->user);

        // create some transactions for the above debit card
        $debitCardTransactions =  DebitCardTransaction::factory()->count(10)->create([
            'debit_card_id' => $debitCard->id
        ]);

        $this->getJson('api/debit-card-transaction/' . $debitCardTransactions->first()->id)
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJson(
                fn ($json) => $json
                ->where('amount', intval($debitCardTransactions->first()->amount))
                ->where('currency_code', $debitCardTransactions->first()->currency_code)
                ->etc()
            );
    }

    public function testCustomerCannotSeeADebitCardTransactionAttachedToOtherCustomerDebitCard()
    {
        // * get api/debit-card-transactions/{debitCardTransaction}

        // create another user
        $anotherUser = User::factory()->create();

        $debitCard = DebitCard::factory()->active()->create([
            'user_id' => $anotherUser->id
        ]);

        // check if user is authenticated
        $this->assertAuthenticatedAs($this->user);

        // create some transactions for the above debit card
        $debitCardTransactions =  DebitCardTransaction::factory()->count(10)->create([
            'debit_card_id' => $debitCard->id
        ]);

        $this->getJson('api/debit-card-transaction/' . $debitCardTransactions->first()->id)
            ->assertForbidden();
    }

    // Extra bonus for extra tests :)
}
