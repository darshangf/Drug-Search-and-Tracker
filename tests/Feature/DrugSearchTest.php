<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Feature Tests for Drug Search Endpoint
 * 
 * Tests the complete HTTP flow for drug search
 */
class DrugSearchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test drug search endpoint with valid drug name
     */
    public function test_can_search_drugs_with_valid_name(): void
    {
        Http::fake([
            '*/drugs.json*' => Http::response([
                'drugGroup' => [
                    'conceptGroup' => [
                        [
                            'tty' => 'SBD',
                            'conceptProperties' => [
                                [
                                    'rxcui' => '213269',
                                    'name' => 'Aspirin 81 MG Oral Tablet'
                                ]
                            ]
                        ]
                    ]
                ]
            ], 200),
            '*/rxcui/213269/historystatus.json' => Http::response([
                'rxcuiStatusHistory' => [
                    'definitionalFeatures' => [
                        'ingredientAndStrength' => [
                            ['baseName' => 'Aspirin']
                        ],
                        'doseFormGroupConcept' => [
                            ['doseFormGroupName' => 'Oral Tablet']
                        ]
                    ]
                ]
            ], 200),
        ]);

        $response = $this->getJson('/api/drugs/search?drug_name=aspirin');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'count',
                'data' => [
                    '*' => [
                        'rxcui',
                        'name',
                        'ingredient_base_names',
                        'dosage_forms',
                    ]
                ]
            ])
            ->assertJson([
                'message' => 'Drugs retrieved successfully',
                'count' => 1,
            ]);
    }

    /**
     * Test drug search fails without drug_name parameter
     */
    public function test_search_fails_without_drug_name(): void
    {
        $response = $this->getJson('/api/drugs/search');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['drug_name']);
    }

    /**
     * Test drug search fails with short drug name
     */
    public function test_search_fails_with_short_drug_name(): void
    {
        $response = $this->getJson('/api/drugs/search?drug_name=a');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['drug_name']);
    }

    /**
     * Test drug search returns 404 when no drugs found
     */
    public function test_search_returns_404_when_no_drugs_found(): void
    {
        Http::fake([
            '*/drugs.json*' => Http::response([
                'drugGroup' => [
                    'conceptGroup' => []
                ]
            ], 200),
        ]);

        $response = $this->getJson('/api/drugs/search?drug_name=nonexistentdrug123');

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'No drugs found matching your search',
                'data' => []
            ]);
    }

    /**
     * Test drug search handles API errors gracefully
     */
    public function test_search_handles_api_error_gracefully(): void
    {
        Http::fake([
            '*/drugs.json*' => Http::response([], 500),
        ]);

        $response = $this->getJson('/api/drugs/search?drug_name=aspirin');

        $response->assertStatus(503)
            ->assertJson([
                'message' => 'Failed to fetch drug information',
            ]);
    }

    /**
     * Test drug search endpoint is rate limited
     */
    public function test_search_endpoint_is_rate_limited(): void
    {
        Http::fake([
            '*/drugs.json*' => Http::response([
                'drugGroup' => [
                    'conceptGroup' => [
                        [
                            'tty' => 'SBD',
                            'conceptProperties' => [
                                ['rxcui' => '123', 'name' => 'Test Drug']
                            ]
                        ]
                    ]
                ]
            ], 200),
            '*' => Http::response([
                'rxcuiStatusHistory' => [
                    'definitionalFeatures' => [
                        'ingredientAndStrength' => [],
                        'doseFormGroupConcept' => []
                    ]
                ]
            ], 200),
        ]);

        // Make 61 requests (limit is 60 per minute)
        for ($i = 0; $i < 61; $i++) {
            $response = $this->getJson('/api/drugs/search?drug_name=test' . $i);

            if ($i < 60) {
                // First 60 should succeed
                $response->assertStatus(200);
            } else {
                // 61st request should be rate limited
                $response->assertStatus(429);
            }
        }
    }
}
