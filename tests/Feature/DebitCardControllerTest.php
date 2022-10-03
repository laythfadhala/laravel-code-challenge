<?php

namespace Tests\Feature;

use App\Models\DebitCard;
use App\Models\DebitCardTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class DebitCardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function testCustomerCanSeeAListOfDebitCards()
    {
        // * get api/debit-cards

        $debitCards = DebitCard::factory()->active()->count(5)->create([
            'user_id' => $this->user->id,
        ]);

        Passport::actingAs($this->user);

        // check if user is authenticated
        $this->assertAuthenticatedAs($this->user);

        // check the user can see its own debit cards
        $this->getJson('api/debit-cards')
            ->assertOk()
            ->assertJsonCount($debitCards->count())
            ->assertJson(
                fn (AssertableJson $json) =>
                $json->first(
                    fn ($json) =>
                    $json->where('number', intval($debitCards->first()->number))
                        ->where('type', $debitCards->first()->type)
                        ->where('is_active', true)
                        ->where('expiration_date', date_format($debitCards->first()->expiration_date, 'Y-m-d H:i:s'))
                        ->where('id', $debitCards->first()->id)
                        ->etc()
                )
            );
    }

    public function testCustomerCannotSeeAListOfDebitCardsOfOtherCustomers()
    {
        // * get api/debit-cards

        Passport::actingAs($this->user);
        $anotherUser = User::factory()->create();
        DebitCard::factory()->active()->count(5)->create([
            'user_id' => $anotherUser->id,
        ]);

        // check if user is authenticated
        $this->assertAuthenticatedAs($this->user);

        // check the user can not see debit cards for other user/s
        $this->getJson('api/debit-cards')
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function testCustomerCanCreateADebitCard()
    {
        // * post api/debit-cards

        Passport::actingAs($this->user);
        $this->postJson('api/debit-cards', [
            'type' => 'creditTestEntry'
        ])
            ->assertValid(['type']) // correctly validated
            ->assertCreated() // returned status 201
            ->assertJson(
                fn ($json) => $json->where('type', 'creditTestEntry')->etc()
            )
            ->assertJsonStructure([  // check if the return json has right keys
                'id',
                'type',
                'number',
                'expiration_date',
                'is_active',
            ]);

        $this->assertDatabaseHas('debit_cards', [
            'type' => 'creditTestEntry'
        ]);
    }

    public function testCustomerCanSeeASingleDebitCardDetails()
    {
        // * get api/debit-cards/{debitCard}

        $debitCards = DebitCard::factory()->active()->count(5)->create([
            'user_id' => $this->user->id,
        ]);

        Passport::actingAs($this->user);

        // check if user is authenticated
        $this->assertAuthenticatedAs($this->user);


        // check the user can not see debit cards for another user/s
        $this->getJson('api/debit-cards/' . $debitCards->first()->id)
            ->assertOk()
            ->assertJson(
                fn ($json) => $json->where('number', intval($debitCards->first()->number))
                    ->where('type', $debitCards->first()->type)
                    ->where('is_active', true)
                    ->where('expiration_date', date_format($debitCards->first()->expiration_date, 'Y-m-d H:i:s'))
                    ->where('id', $debitCards->first()->id)
                    ->etc()
            );
    }

    public function testCustomerCannotSeeASingleDebitCardDetails()  // TODO: under which condition? if unauthenticated?, card of other customer?, wrong route?, etc.
    {
        // * We will assume that the user can not see a single card details of another customer.
        // * get api/debit-cards/{debitCard}

        Passport::actingAs($this->user);
        $anotherUser = User::factory()->create();
        $debitCards = DebitCard::factory()->active()->count(5)->create([
            'user_id' => $anotherUser->id,
        ]);

        // check the user can not see debit cards for other user/s
        $this->getJson('api/debit-cards/' . $debitCards->first()->id)
            ->assertForbidden()
            ->assertJson(
                fn ($json) => $json->where('message', 'This action is unauthorized.')->etc()
            );
    }

    public function testCustomerCanActivateADebitCard()
    {
        // * put api/debit-cards/{debitCard}

        $inactiveDebitCard = DebitCard::factory()->expired()->create([
            'user_id' => $this->user->id,
        ]);

        Passport::actingAs($this->user);

        // check if user is authenticated
        $this->assertAuthenticatedAs($this->user);

        $this->putJson('api/debit-cards/' . $inactiveDebitCard->id, [
            'is_active' => true
        ])
            ->assertValid(['is_active']) // correctly validated
            ->assertOk() // returned status 200
            ->assertJson(
                fn ($json) => $json->where('is_active', true)->etc()
            );
    }

    public function testCustomerCanDeactivateADebitCard()
    {
        // * put api/debit-cards/{debitCard}

        $activeDebitCard = DebitCard::factory()->active()->create([
            'user_id' => $this->user->id,
        ]);

        Passport::actingAs($this->user);

        // check if user is authenticated
        $this->assertAuthenticatedAs($this->user);

        $this->putJson('api/debit-cards/' . $activeDebitCard->id, [
            'is_active' => false
        ])
            ->assertValid(['is_active']) // correctly validated
            ->assertOk() // returned status 200
            ->assertJson(
                fn ($json) => $json->where('is_active', false)->etc()
            );
    }

    public function testCustomerCannotUpdateADebitCardWithWrongValidation()
    {
        // * put api/debit-cards/{debitCard}

        $inactiveDebitCard = DebitCard::factory()->expired()->create([
            'user_id' => $this->user->id,
        ]);

        Passport::actingAs($this->user);

        // check if user is authenticated
        $this->assertAuthenticatedAs($this->user);

        $this->putJson('api/debit-cards/' . $inactiveDebitCard->id, [
            'is_active' => 'giving a sting rather than boolean' // wrong validation
        ])
            ->assertUnprocessable() // returned status 422
            ->assertInvalid(['is_active']);
    }

    public function testCustomerCanDeleteADebitCard()
    {
        // * delete api/debit-cards/{debitCard}

        $activeDebitCard = DebitCard::factory()->active()->create([
            'user_id' => $this->user->id,
        ]);

        Passport::actingAs($this->user);

        // check if user is authenticated
        $this->assertAuthenticatedAs($this->user);

        $this->deleteJson('api/debit-cards/' . $activeDebitCard->id)
            ->assertNoContent();  // returned status 204

        $this->assertSoftDeleted('debit_cards', [
                'id' => $activeDebitCard->id,
                'number' => intval($activeDebitCard->number),
                'type' => $activeDebitCard->type,
                'user_id' => $activeDebitCard->user->id,
            ]);
    }

    public function testCustomerCannotDeleteADebitCardWithTransaction()
    {
        // * delete api/debit-cards/{debitCard}

        $activeDebitCard = DebitCard::factory()->active()->create([
            'user_id' => $this->user->id,
        ]);

        DebitCardTransaction::factory()->count(5)->create([
            'debit_card_id' => $activeDebitCard->id
        ]);

        Passport::actingAs($this->user);

        // check if user is authenticated
        $this->assertAuthenticatedAs($this->user);

        $this->deleteJson('api/debit-cards/' . $activeDebitCard->id)
            ->assertForbidden()  // returned status 403
            ->assertJson(
                fn ($json) => $json->where('message', 'This action is unauthorized.')->etc()
            );
    }

    // Extra bonus for extra tests :)

    public function testCustomerCannotSeeAListOfDebitCardsIfNotAuthenticated()
    {
        // * get /debit-cards
        $this->getJson('api/debit-cards')
            ->assertUnauthorized()
            ->assertJson(
                fn ($json) => $json->where('message', 'Unauthenticated.')
            );
    }
}
