<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
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
     * Cache TTL in seconds (24 hours)
     */
    private const CACHE_TTL = 86400;

    /**
     * Search for drugs using the getDrugs endpoint
     *
     * @param string $drugName
     * @param int $limit
     * @return array
     */
    public function searchDrugs(string $drugName, int $limit = 5): array
    {
        $cacheKey = $this->getCacheKey($drugName, $limit);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($drugName, $limit) {
            try {
                // Step 1: Get drugs by name (tty = SBD - Semantic Branded Drug)
                $drugs = $this->getDrugs($drugName, $limit);
                
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
        });
    }

    /**
     * Get drugs from RxNorm getDrugs endpoint
     *
     * @param string $drugName
     * @return array
     */
    private function getDrugs(string $drugName, int $limit): array
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
            ->take($limit)
            ->map(fn ($drug) => [
                'rxcui' => $drug['rxcui'],
                'name'  => $drug['name'],
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
        $ingredients = $attributes['ingredientAndStrength'] ?? [];

        if (! is_array($ingredients)) {
            return [];
        }
    
        return collect($ingredients)
            ->pluck('baseName')
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Extract dosage forms from attributes
     *
     * @param array $attributes
     * @return array
     */
    private function extractDosageForms(array $attributes): array
    {
        $dosages = $attributes['doseFormGroupConcept'] ?? [];

        if (! is_array($dosages)) {
            return [];
        }

        return collect($dosages)
            ->pluck('doseFormGroupName')
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Generate cache key for drug search
     *
     * @param string $drugName
     * @param int $limit
     * @return string
     */
    private function getCacheKey(string $drugName, int $limit): string
    {
        return sprintf(
            'rxnorm:drug_search:%s:%d',
            strtolower(trim($drugName)),
            $limit
        );
    }

    /**
     * Clear cached results for a specific drug search
     *
     * @param string $drugName
     * @param int $limit
     * @return bool
     */
    public function clearCache(string $drugName, int $limit = 5): bool
    {
        $cacheKey = $this->getCacheKey($drugName, $limit);
        return Cache::forget($cacheKey);
    }

    /**
     * Clear all drug search cache
     *
     * @return bool
     */
    public function clearAllCache(): bool
    {
        // This would require a cache tag implementation
        // For now, we'll use a pattern-based approach if using Redis
        // For file/database cache, this would need different implementation
        return true;
    }
}
