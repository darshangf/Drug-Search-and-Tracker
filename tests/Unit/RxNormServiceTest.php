<?php

namespace Tests\Unit;

use App\Services\RxNormService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Unit Tests for RxNormService
 * 
 * Tests the business logic of RxNorm API integration
 */
class RxNormServiceTest extends TestCase
{
    protected RxNormService $rxNormService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rxNormService = new RxNormService();
    }

    /**
     * Test successful drug search returns enriched data
     */
    public function test_search_drugs_returns_enriched_data(): void
    {
        // Mock getDrugs API response
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
                                ],
                                [
                                    'rxcui' => '198440',
                                    'name' => 'Aspirin 325 MG Oral Tablet'
                                ]
                            ]
                        ]
                    ]
                ]
            ], 200),
            '*/rxcui/213269/historystatus.json' => Http::response([
                'rxcuiStatusHistory' => [
                    'attributes' => [
                        'ingredientAndStrength' => [
                            ['baseName' => 'Aspirin']
                        ],
                        'doseFormGroupConcept' => [
                            ['doseFormGroupName' => 'Oral Tablet']
                        ]
                    ]
                ]
            ], 200),
            '*/rxcui/198440/historystatus.json' => Http::response([
                'rxcuiStatusHistory' => [
                    'attributes' => [
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

        $result = $this->rxNormService->searchDrugs('aspirin');

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertArrayHasKey('rxcui', $result[0]);
        $this->assertArrayHasKey('name', $result[0]);
        $this->assertArrayHasKey('ingredient_base_names', $result[0]);
        $this->assertArrayHasKey('dosage_forms', $result[0]);
        $this->assertEquals('213269', $result[0]['rxcui']);
        $this->assertEquals('Aspirin 81 MG Oral Tablet', $result[0]['name']);
    }

    /**
     * Test search returns empty array when no SBD drugs found
     */
    public function test_search_drugs_returns_empty_when_no_sbd_found(): void
    {
        Http::fake([
            '*/drugs.json*' => Http::response([
                'drugGroup' => [
                    'conceptGroup' => [
                        [
                            'tty' => 'IN', // Not SBD
                            'conceptProperties' => [
                                ['rxcui' => '1191', 'name' => 'Aspirin']
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        $result = $this->rxNormService->searchDrugs('aspirin');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test search limits results to specified number
     */
    public function test_search_drugs_limits_results(): void
    {
        Http::fake([
            '*/drugs.json*' => Http::response([
                'drugGroup' => [
                    'conceptGroup' => [
                        [
                            'tty' => 'SBD',
                            'conceptProperties' => array_map(fn($i) => [
                                'rxcui' => (string)$i,
                                'name' => "Drug $i"
                            ], range(1, 10))
                        ]
                    ]
                ]
            ], 200),
            '*' => Http::response([
                'rxcuiStatusHistory' => [
                    'attributes' => [
                        'ingredientAndStrength' => [],
                        'doseFormGroupConcept' => []
                    ]
                ]
            ], 200),
        ]);

        $result = $this->rxNormService->searchDrugs('test', 3);

        $this->assertCount(3, $result);
    }

    /**
     * Test search handles API failure gracefully
     */
    public function test_search_drugs_throws_exception_on_api_failure(): void
    {
        Http::fake([
            '*/drugs.json*' => Http::response([], 500),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to fetch drug information from RxNorm API');

        $this->rxNormService->searchDrugs('aspirin');
    }

    /**
     * Test ingredient base names extraction
     */
    public function test_extracts_ingredient_base_names_correctly(): void
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
            '*/rxcui/123/historystatus.json' => Http::response([
                'rxcuiStatusHistory' => [
                    'attributes' => [
                        'ingredientAndStrength' => [
                            ['baseName' => 'Ingredient A'],
                            ['baseName' => 'Ingredient B'],
                            ['baseName' => 'Ingredient A'], // Duplicate
                        ],
                        'doseFormGroupConcept' => []
                    ]
                ]
            ], 200),
        ]);

        $result = $this->rxNormService->searchDrugs('test');

        $this->assertCount(2, $result[0]['ingredient_base_names']);
        $this->assertContains('Ingredient A', $result[0]['ingredient_base_names']);
        $this->assertContains('Ingredient B', $result[0]['ingredient_base_names']);
    }

    /**
     * Test dosage forms extraction
     */
    public function test_extracts_dosage_forms_correctly(): void
    {
        Http::fake([
            '*/drugs.json*' => Http::response([
                'drugGroup' => [
                    'conceptGroup' => [
                        [
                            'tty' => 'SBD',
                            'conceptProperties' => [
                                ['rxcui' => '456', 'name' => 'Test Drug']
                            ]
                        ]
                    ]
                ]
            ], 200),
            '*/rxcui/456/historystatus.json' => Http::response([
                'rxcuiStatusHistory' => [
                    'attributes' => [
                        'ingredientAndStrength' => [],
                        'doseFormGroupConcept' => [
                            ['doseFormGroupName' => 'Oral Tablet'],
                            ['doseFormGroupName' => 'Injectable'],
                            ['doseFormGroupName' => 'Oral Tablet'], // Duplicate
                        ]
                    ]
                ]
            ], 200),
        ]);

        $result = $this->rxNormService->searchDrugs('test');

        $this->assertCount(2, $result[0]['dosage_forms']);
        $this->assertContains('Oral Tablet', $result[0]['dosage_forms']);
        $this->assertContains('Injectable', $result[0]['dosage_forms']);
    }
}
