<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserMedicationRequest;
use App\Models\UserMedication;
use App\Services\RxNormService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserMedicationController extends Controller
{
    /**
     * Create a new controller instance
     *
     * @param RxNormService $rxNormService
     */
    public function __construct(
        protected RxNormService $rxNormService
    ) {}

    /**
     * Get all medications for authenticated user
     * 
     * Automatically refreshes stale snapshots from RxNorm API
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $medications = UserMedication::with('drugSnapshot')
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message' => 'Medications retrieved successfully',
            'count' => $medications->count(),
            'data' => $medications->map(function ($medication) {
                $snapshot = $medication->drugSnapshot;

                // Check if snapshot is stale and refresh if needed
                if ($snapshot->isStale()) {
                    $snapshot = $this->rxNormService->getOrCreateDrugSnapshot($snapshot->rxcui);

                    // If refresh failed, use existing snapshot
                    if (!$snapshot) {
                        $snapshot = $medication->drugSnapshot;
                    }
                }

                return [
                    'id' => $medication->id,
                    'rxcui' => $medication->rxcui,
                    'drug_name' => $snapshot->drug_name,
                    'ingredient_base_names' => $snapshot->ingredient_base_names ?? [],
                    'dosage_forms' => $snapshot->dosage_forms ?? [],
                    'added_at' => $medication->created_at->toISOString(),
                ];
            }),
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
        $rxcui = $request->validated()['rxcui'];
        $user = $request->user();

        // Check if user already has this medication
        $existing = UserMedication::where('user_id', $user->id)
            ->where('rxcui', $rxcui)
            ->first();

        if ($existing) {
            $existing->load('drugSnapshot');
            return response()->json([
                'message' => 'This medication is already in your list',
                'data' => [
                    'id' => $existing->id,
                    'rxcui' => $existing->rxcui,
                    'drug_name' => $existing->drugSnapshot->drug_name,
                ],
            ], 409);
        }

        // Get or create drug snapshot (validates RXCUI and fetches/updates drug info)
        $snapshot = $this->rxNormService->getOrCreateDrugSnapshot($rxcui);

        if (!$snapshot) {
            return response()->json([
                'message' => 'Invalid RXCUI. Drug not found in RxNorm database.',
                'errors' => [
                    'rxcui' => ['The provided RXCUI is not valid.']
                ]
            ], 422);
        }

        // Create medication record
        $medication = UserMedication::create([
            'user_id' => $user->id,
            'rxcui' => $snapshot->rxcui,
        ]);

        // Load the relationship
        $medication->load('drugSnapshot');

        return response()->json([
            'message' => 'Medication added successfully',
            'data' => [
                'id' => $medication->id,
                'rxcui' => $medication->rxcui,
                'drug_name' => $snapshot->drug_name,
                'ingredient_base_names' => $snapshot->ingredient_base_names ?? [],
                'dosage_forms' => $snapshot->dosage_forms ?? [],
                'added_at' => $medication->created_at->toISOString(),
            ],
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
        $user = $request->user();

        $medication = UserMedication::where('user_id', $user->id)
            ->where('rxcui', $rxcui)
            ->first();

        if (!$medication) {
            return response()->json([
                'message' => 'Medication not found in your list',
                'errors' => [
                    'rxcui' => ['The specified medication does not exist in your list.']
                ]
            ], 404);
        }

        $medication->delete();

        return response()->json([
            'message' => 'Medication removed successfully',
        ]);
    }
}
