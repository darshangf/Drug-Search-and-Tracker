<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DrugSearchRequest;
use App\Services\RxNormService;
use Illuminate\Http\JsonResponse;

/**
 * Drug Controller
 * 
 * Handles HTTP requests for drug search functionality
 * Delegates business logic to RxNormService
 */
class DrugController extends Controller
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
     * Search for drugs by name (Public endpoint - no authentication required)
     *
     * @param DrugSearchRequest $request
     * @return JsonResponse
     */
    public function search(DrugSearchRequest $request): JsonResponse
    {
        try {
            $drugName = $request->validated()['drug_name'];
            
            $drugs = $this->rxNormService->searchDrugs($drugName);

            if (empty($drugs)) {
                return response()->json([
                    'status' => false,
                    'message' => 'No drugs found matching your search',
                    'data' => []
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Drugs retrieved successfully',
                'data' => [
                    'count' => count($drugs),
                    'drugs' => $drugs,
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch drug information',
                'error' => $e->getMessage()
            ], 503);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An unexpected error occurred',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
