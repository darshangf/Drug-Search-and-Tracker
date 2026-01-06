<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserMedicationRequest;
use App\Services\UserMedicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserMedicationController extends Controller
{
    public function __construct(
        protected UserMedicationService $medicationService
    ) {}

    /**
     * Get all medications for authenticated user
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $medications = $this->medicationService->getUserMedications($request->user());

        return response()->json([
            'message' => 'Medications retrieved successfully',
            'count' => $medications->count(),
            'data' => $medications,
        ]);
    }

    /**
     * Add a new medication to user's list
     *
     * @param StoreUserMedicationRequest $request
     * @return JsonResponse
     */
    public function store(StoreUserMedicationRequest $request): JsonResponse
    {
        $result = $this->medicationService->addMedication(
            $request->user(),
            $request->validated()['rxcui']
        );

        if (!$result['success']) {
            $statusCode = match ($result['status']) {
                'duplicate' => 409,
                'invalid' => 422,
                default => 400,
            };

            $response = ['message' => $result['error']];
            
            if ($result['status'] === 'duplicate') {
                $response['data'] = $result['data'];
            } elseif ($result['status'] === 'invalid') {
                $response['errors'] = ['rxcui' => ['The provided RXCUI is not valid.']];
            }

            return response()->json($response, $statusCode);
        }

        return response()->json([
            'message' => 'Medication added successfully',
            'data' => $result['data'],
        ], 201);
    }

    /**
     * Delete a medication from user's list
     *
     * @param Request $request
     * @param string $rxcui
     * @return JsonResponse
     */
    public function destroy(Request $request, string $rxcui): JsonResponse
    {
        $result = $this->medicationService->deleteMedication($request->user(), $rxcui);

        if (!$result['success']) {
            return response()->json([
                'message' => $result['error'],
                'errors' => [
                    'rxcui' => ['The specified medication does not exist in your list.']
                ]
            ], 404);
        }

        return response()->json([
            'message' => 'Medication removed successfully',
        ]);
    }
}
