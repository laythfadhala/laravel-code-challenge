<?php

namespace App\Http\Controllers;

use App\Models\DebitCard;
use Illuminate\Http\JsonResponse;
use App\Models\DebitCardTransaction;
use Illuminate\Routing\Controller as BaseController;
use App\Http\Resources\DebitCardTransactionResource;
use App\Http\Requests\DebitCardTransactionShowRequest;
use App\Http\Requests\DebitCardTransactionCreateRequest;
use App\Http\Requests\DebitCardTransactionShowIndexRequest;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class DebitCardTransactionController extends BaseController
{
    /**
     * Get debit card transactions list
     *
     * @param DebitCardTransactionShowIndexRequest $request
     * @param DebitCard $debitCard
     * @return JsonResponse
     */
    public function index(DebitCardTransactionShowIndexRequest $request, $debitCard): JsonResponse
    {
        // ! changed because the code was for post request not get.
        $debitCardTransactions = DebitCard::find($debitCard)
            ->debitCardTransactions;

        return response()->json(DebitCardTransactionResource::collection($debitCardTransactions), HttpResponse::HTTP_OK);
    }

    /**
     * Create a new debit card transaction
     *
     * @param DebitCardTransactionCreateRequest $request
     *
     * @return JsonResponse
     */
    public function store(DebitCardTransactionCreateRequest $request)
    {
        $debitCard = DebitCard::find($request->input('debit_card_id'));

        $debitCardTransaction = $debitCard->debitCardTransactions()->create([
            'amount' => $request->input('amount'),
            'currency_code' => $request->input('currency_code'),
        ]);

        return response()->json(new DebitCardTransactionResource($debitCardTransaction), HttpResponse::HTTP_CREATED);
    }

    /**
     * Show a debit card transaction
     *
     * @param DebitCardTransactionShowRequest $request
     * @param DebitCardTransaction            $debitCardTransaction
     *
     * @return JsonResponse
     */
    public function show(DebitCardTransactionShowRequest $request, DebitCardTransaction $debitCardTransaction): JsonResponse
    {
        return response()->json(new DebitCardTransactionResource($debitCardTransaction), HttpResponse::HTTP_OK);
    }
}
