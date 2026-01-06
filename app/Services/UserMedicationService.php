<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserMedication;
use Illuminate\Support\Collection;

class UserMedicationService
{
    public function __construct(
        protected RxNormService $rxNormService
    ) {}

    /**
     * Get all medications for a user with automatic snapshot refresh
     *
     * @param User $user
     * @return Collection
     */
    public function getUserMedications(User $user): Collection
    {
        $medications = UserMedication::with('drugSnapshot')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return $medications->map(function ($medication) {
            $snapshot = $medication->drugSnapshot;

            // Check if snapshot is stale and refresh if needed
            if ($snapshot->isStale()) {
                $refreshedSnapshot = $this->rxNormService->getOrCreateDrugSnapshot($snapshot->rxcui);

                // If refresh failed, use existing snapshot (graceful degradation)
                if ($refreshedSnapshot) {
                    $snapshot = $refreshedSnapshot;
                }
            }

            return [
                'id' => $medication->id,
                'rxcui' => $medication->rxcui,
                'drug_name' => $snapshot->drug_name,
                'ingredient_base_names' => $snapshot->ingredient_base_names ?? [],
                'dosage_forms' => $snapshot->dosage_forms ?? [],
            ];
        });
    }

    /**
     * Add a medication to user's list
     *
     * @param User $user
     * @param string $rxcui
     * @return array Returns ['success' => bool, 'data' => array, 'error' => ?string, 'status' => ?string]
     */
    public function addMedication(User $user, string $rxcui): array
    {
        // Check if user already has this medication
        $existing = UserMedication::where('user_id', $user->id)
            ->where('rxcui', $rxcui)
            ->first();

        if ($existing) {
            $existing->load('drugSnapshot');
            return [
                'success' => false,
                'data' => [
                    'id' => $existing->id,
                    'rxcui' => $existing->rxcui,
                    'drug_name' => $existing->drugSnapshot->drug_name,
                ],
                'error' => 'This medication is already in your list',
                'status' => 'duplicate',
            ];
        }

        // Get or create drug snapshot (validates RXCUI and fetches/updates drug info)
        $snapshot = $this->rxNormService->getOrCreateDrugSnapshot($rxcui);

        if (!$snapshot) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'Invalid RXCUI. Drug not found in RxNorm database.',
                'status' => 'invalid',
            ];
        }

        // Create medication record
        $medication = UserMedication::create([
            'user_id' => $user->id,
            'rxcui' => $snapshot->rxcui,
        ]);

        return [
            'success' => true,
            'data' => [
                'id' => $medication->id,
                'rxcui' => $medication->rxcui,
                'drug_name' => $snapshot->drug_name,
                'ingredient_base_names' => $snapshot->ingredient_base_names ?? [],
                'dosage_forms' => $snapshot->dosage_forms ?? [],
            ],
            'error' => null,
            'status' => 'created',
        ];
    }

    /**
     * Delete a medication from user's list
     *
     * @param User $user
     * @param string $rxcui
     * @return array Returns ['success' => bool, 'error' => ?string]
     */
    public function deleteMedication(User $user, string $rxcui): array
    {
        $medication = UserMedication::where('user_id', $user->id)
            ->where('rxcui', $rxcui)
            ->first();

        if (!$medication) {
            return [
                'success' => false,
                'error' => 'Medication not found in your list',
            ];
        }

        $medication->delete();

        return [
            'success' => true,
            'error' => null,
        ];
    }

    /**
     * Check if user has a specific medication
     *
     * @param User $user
     * @param string $rxcui
     * @return bool
     */
    public function userHasMedication(User $user, string $rxcui): bool
    {
        return UserMedication::where('user_id', $user->id)
            ->where('rxcui', $rxcui)
            ->exists();
    }

    /**
     * Get count of user's medications
     *
     * @param User $user
     * @return int
     */
    public function getMedicationCount(User $user): int
    {
        return UserMedication::where('user_id', $user->id)->count();
    }
}
