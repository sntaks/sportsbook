<?php

namespace App\Http\Controllers\Api\V1;

use App\Betting\BookingCodes\CreateBookingCode;
use App\Betting\BookingCodes\ResolveBookingCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreBookingCodeRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * @tags Bet slip
 */
class BookingCodeController extends Controller
{
    /**
     * Share a slip as a booking code.
     *
     * Keeps only the selections open for betting now, one per match. The code expires when the first of those
     * matches kicks off. Limited to 10 a minute.
     *
     * @unauthenticated
     */
    public function store(StoreBookingCodeRequest $request, CreateBookingCode $createBookingCode): JsonResponse
    {
        $user = $request->user('sanctum');

        try {
            $bookingCode = $createBookingCode->handle(
                array_map(intval(...), $request->array('selection_ids')),
                $user instanceof User ? $user : null,
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['selection_ids' => $exception->getMessage()]);
        }

        return response()->json([
            'code' => $bookingCode->code,
            'expires_at' => $bookingCode->expires_at->toIso8601String(),
            /** The selections saved on the code. @var list<int> */
            'selection_ids' => $bookingCode->selection_ids,
        ], 201);
    }

    /**
     * Load a booking code.
     *
     * Returns picks ready to send to the slip quote, at today's prices. Selections that can no longer be bet on
     * are left out and described in `unavailable`. Limited to 30 a minute.
     *
     * @unauthenticated
     */
    public function show(string $code, ResolveBookingCode $resolveBookingCode): JsonResponse
    {
        try {
            $resolved = $resolveBookingCode->handle($code);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        }

        return response()->json([
            'code' => strtoupper(trim($code)),
            /** @var list<array{fixture_id: int, selection_id: int, price: string}> */
            'picks' => $resolved['picks'],
            /** The saved selections that can no longer be bet on, described for the player. */
            'unavailable' => $resolved['unavailable'],
        ]);
    }
}
