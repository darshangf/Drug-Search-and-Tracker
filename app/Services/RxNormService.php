<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * RxNorm API Service
 * 
 * Handles all interactions with the National Library of Medicine RxNorm API
 * API Documentation: https://lhncbc.nlm.nih.gov/RxNav/APIs/RxNormAPIs.html
 */
class RxNormService
{
    /**
     * Base URL for RxNorm API
     */
    private const BASE_URL = 'https://rxnav.nlm.nih.gov/REST';

    /**
     * Timeout for API requests in seconds
     */
    private const TIMEOUT = 10;

    /**
     * Search for drugs using the getDrugs endpoint
     *
     * @param string $drugName
     * @param int $limit
     * @return array
     */
    public function searchDrugs(string $drugName, int $limit = 5): array
    {
        try {
            // Step 1: Get drugs by name (tty = SBD - Semantic Branded Drug)
            $drugs = $this->getDrugs($drugName);
            
            if (empty($drugs)) {
                return [];
            }
            
            // Step 2: Enrich each drug with additional information
            $enrichedDrugs = [];
            foreach ($drugs as $drug) {
                $enrichedDrugs[] = $this->enrichDrugData($drug);
            }

            return $enrichedDrugs;
        } catch (\Exception $e) {
            Log::error('RxNorm API Error', [
                'drug_name' => $drugName,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to fetch drug information from RxNorm API');
        }
    }

    /**
     * Get drugs from RxNorm getDrugs endpoint
     *
     * @param string $drugName
     * @return array
     */
    private function getDrugs(string $drugName): array
    {
        $response = Http::timeout(self::TIMEOUT)
            ->get(self::BASE_URL . '/drugs.json', [
                'name' => $drugName
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('RxNorm getDrugs API request failed');
        }

        $data = $response->json();

        $conceptGroups = $data['drugGroup']['conceptGroup'] ?? [];

        $sbdGroup = collect($conceptGroups)
            ->firstWhere('tty', 'SBD');

        $sbdDrugs = collect($sbdGroup['conceptProperties'] ?? [])
            ->take(5)
            ->map(fn ($drug) => [
                'rxcui' => $drug['rxcui'],
                'name'  => $drug['name'],
                'synonym' => $drug['synonym'] ?? '',
            ])
            ->values()
            ->toArray();

        return $sbdDrugs;
    }

    /**
     * Enrich drug data with history status information
     *
     * @param array $drug
     * @return array
     */
    private function enrichDrugData(array $drug): array
    {
        $rxcui = $drug['rxcui'] ?? null;
        $name = $drug['name'] ?? '';

        if (!$rxcui) {
            return [
                'rxcui' => null,
                'name' => $name,
                'ingredient_base_names' => [],
                'dosage_forms' => [],
            ];
        }

        // Get additional information from getRxcuiHistoryStatus
        $historyData = $this->getRxcuiHistoryStatus($rxcui);

        return [
            'rxcui' => $rxcui,
            'name' => $name,
            'ingredient_base_names' => $historyData['ingredient_base_names'],
            'dosage_forms' => $historyData['dosage_forms'],
        ];
    }

    /**
     * Get drug history status including ingredients and dosage forms
     *
     * @param string $rxcui
     * @return array
     */
    private function getRxcuiHistoryStatus(string $rxcui): array
    {
        try {
            $response = Http::timeout(self::TIMEOUT)
                ->get(self::BASE_URL . '/rxcui/' . $rxcui . '/historystatus.json');

            if (!$response->successful()) {
                return [
                    'ingredient_base_names' => [],
                    'dosage_forms' => [],
                ];
            }

            $data = $response->json();
            $attributes = $data['rxcuiStatusHistory']['definitionalFeatures'] ?? [];

            return [
                'ingredient_base_names' => $this->extractIngredientBaseNames($attributes),
                'dosage_forms' => $this->extractDosageForms($attributes),
            ];
        } catch (\Exception $e) {
            Log::warning('RxNorm getRxcuiHistoryStatus failed', [
                'rxcui' => $rxcui,
                'error' => $e->getMessage()
            ]);
            
            return [
                'ingredient_base_names' => [],
                'dosage_forms' => [],
            ];
        }
    }

    /**
     * Extract ingredient base names from attributes
     *
     * @param array $attributes
     * @return array
     */
    private function extractIngredientBaseNames(array $attributes): array
    {
        $baseNames = [];
        
        // Extract from ingredientAndStrength
        $ingredientAndStrength = $attributes['ingredientAndStrength'] ?? [];
        
        foreach ($ingredientAndStrength as $ingredient) {
            $baseName = $ingredient['baseName'] ?? null;
            if ($baseName && !in_array($baseName, $baseNames)) {
                $baseNames[] = $baseName;
            }
        }

        return array_values(array_unique($baseNames));
    }

    /**
     * Extract dosage forms from attributes
     *
     * @param array $attributes
     * @return array
     */
    private function extractDosageForms(array $attributes): array
    {
        $dosageForms = [];
        
        // Extract from doseFormGroupConcept
        $doseFormGroupConcept = $attributes['doseFormGroupConcept'] ?? [];
        
        foreach ($doseFormGroupConcept as $concept) {
            $doseFormGroupName = $concept['doseFormGroupName'] ?? null;
            if ($doseFormGroupName && !in_array($doseFormGroupName, $dosageForms)) {
                $dosageForms[] = $doseFormGroupName;
            }
        }

        return array_values(array_unique($dosageForms));
    }
}
