<?php

namespace Tests\Feature;

use App\Models\DrugSnapshot;
use App\Models\User;
use App\Models\UserMedication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Feature Tests for Medication Endpoints
 * 
 * Tests the complete HTTP flow for user medication management
 */
class MedicationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Create and authenticate user
        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    /**
     * Test authenticated user can get their medications
     */
    public function test_user_can_get_their_medications(): void
    {
        // Create drug snapshots and medications
        $snapshot1 = DrugSnapshot::factory()->create();
        $snapshot2 = DrugSnapshot::factory()->create();
        $snapshot3 = DrugSnapshot::factory()->create();

        UserMedication::create(['user_id' => $this->user->id, 'rxcui' => $snapshot1->rxcui]);
        UserMedication::create(['user_id' => $this->user->id, 'rxcui' => $snapshot2->rxcui]);
        UserMedication::create(['user_id' => $this->user->id, 'rxcui' => $snapshot3->rxcui]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/medications');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'count',
                'data' => [
                    '*' => [
                        'id',
                        'rxcui',
                        'drug_name',
                        'ingredient_base_names',
                        'dosage_forms',
                        'added_at',
                    ]
                ]
            ])
            ->assertJson([
                'count' => 3,
            ]);
    }

    /**
     * Test unauthenticated user cannot access medications
     */
    public function test_unauthenticated_user_cannot_get_medications(): void
    {
        $response = $this->getJson('/api/medications');

        $response->assertStatus(401);
    }

    /**
     * Test user can add valid medication
     */
    public function test_user_can_add_valid_medication(): void
    {
        Http::fake([
            '*/rxcui/213269/properties.json' => Http::response([
                'properties' => [
                    'rxcui' => '213269',
                    'name' => 'Aspirin 81 MG Oral Tablet',
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

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/medications', [
                'rxcui' => '213269'
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'rxcui',
                    'drug_name',
                    'ingredient_base_names',
                    'dosage_forms',
                    'added_at',
                ]
            ])
            ->assertJson([
                'message' => 'Medication added successfully',
                'data' => [
                    'rxcui' => '213269',
                    'drug_name' => 'Aspirin 81 MG Oral Tablet',
                ]
            ]);

        $this->assertDatabaseHas('user_medications', [
            'user_id' => $this->user->id,
            'rxcui' => '213269',
        ]);

        // Verify drug snapshot was created
        $this->assertDatabaseHas('drug_snapshots', [
            'rxcui' => '213269',
            'drug_name' => 'Aspirin 81 MG Oral Tablet',
        ]);
    }

    /**
     * Test adding medication fails with invalid RXCUI
     */
    public function test_adding_medication_fails_with_invalid_rxcui(): void
    {
        Http::fake([
            '*/rxcui/invalid/properties.json' => Http::response([], 404),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/medications', [
                'rxcui' => 'invalid'
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Invalid RXCUI. Drug not found in RxNorm database.',
            ]);

        $this->assertDatabaseMissing('user_medications', [
            'user_id' => $this->user->id,
            'rxcui' => 'invalid',
        ]);
    }

    /**
     * Test adding duplicate medication returns conflict
     */
    public function test_adding_duplicate_medication_returns_conflict(): void
    {
        // Create drug snapshot and existing medication
        $snapshot = DrugSnapshot::create([
            'rxcui' => '213269',
            'drug_name' => 'Aspirin 81 MG Oral Tablet',
            'ingredient_base_names' => ['Aspirin'],
            'dosage_forms' => ['Oral Tablet'],
            'last_synced_at' => now(),
        ]);

        UserMedication::create([
            'user_id' => $this->user->id,
            'rxcui' => '213269',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/medications', [
                'rxcui' => '213269'
            ]);

        $response->assertStatus(409)
            ->assertJson([
                'message' => 'This medication is already in your list',
            ]);
    }

    /**
     * Test adding medication requires RXCUI
     */
    public function test_adding_medication_requires_rxcui(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/medications', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rxcui']);
    }

    /**
     * Test user can delete their medication
     */
    public function test_user_can_delete_their_medication(): void
    {
        $snapshot = DrugSnapshot::create([
            'rxcui' => '213269',
            'drug_name' => 'Aspirin 81 MG Oral Tablet',
            'ingredient_base_names' => ['Aspirin'],
            'dosage_forms' => ['Oral Tablet'],
            'last_synced_at' => now(),
        ]);

        $medication = UserMedication::create([
            'user_id' => $this->user->id,
            'rxcui' => '213269',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson('/api/medications/213269');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Medication removed successfully',
            ]);

        $this->assertDatabaseMissing('user_medications', [
            'id' => $medication->id,
        ]);
    }

    /**
     * Test deleting non-existent medication returns 404
     */
    public function test_deleting_non_existent_medication_returns_404(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson('/api/medications/999999');

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Medication not found in your list',
            ]);
    }

    /**
     * Test user cannot delete another user's medication
     */
    public function test_user_cannot_delete_another_users_medication(): void
    {
        $otherUser = User::factory()->create();
        
        $snapshot = DrugSnapshot::create([
            'rxcui' => '213269',
            'drug_name' => 'Aspirin 81 MG Oral Tablet',
            'ingredient_base_names' => ['Aspirin'],
            'dosage_forms' => ['Oral Tablet'],
            'last_synced_at' => now(),
        ]);

        $medication = UserMedication::create([
            'user_id' => $otherUser->id,
            'rxcui' => '213269',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson('/api/medications/213269');

        $response->assertStatus(404);

        // Medication should still exist
        $this->assertDatabaseHas('user_medications', [
            'id' => $medication->id,
        ]);
    }

    /**
     * Test unauthenticated user cannot add medication
     */
    public function test_unauthenticated_user_cannot_add_medication(): void
    {
        $response = $this->postJson('/api/medications', [
            'rxcui' => '213269'
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test unauthenticated user cannot delete medication
     */
    public function test_unauthenticated_user_cannot_delete_medication(): void
    {
        $response = $this->deleteJson('/api/medications/213269');

        $response->assertStatus(401);
    }

    /**
     * Test snapshot is reused if fresh
     */
    public function test_snapshot_is_reused_if_fresh(): void
    {
        // Create a fresh snapshot
        $snapshot = DrugSnapshot::create([
            'rxcui' => '213269',
            'drug_name' => 'Aspirin 81 MG Oral Tablet',
            'ingredient_base_names' => ['Aspirin'],
            'dosage_forms' => ['Oral Tablet'],
            'last_synced_at' => now(),
        ]);

        // Add medication - should use existing snapshot without API call
        Http::fake();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/medications', [
                'rxcui' => '213269'
            ]);

        $response->assertStatus(201);

        // No HTTP calls should have been made
        Http::assertNothingSent();
    }

    /**
     * Test snapshot is refreshed if stale
     */
    public function test_snapshot_is_refreshed_if_stale(): void
    {
        // Create a stale snapshot (older than 30 days)
        $snapshot = DrugSnapshot::create([
            'rxcui' => '213269',
            'drug_name' => 'Aspirin 81 MG Oral Tablet OLD',
            'ingredient_base_names' => ['Old Ingredient'],
            'dosage_forms' => ['Old Form'],
            'last_synced_at' => now()->subDays(31),
        ]);

        Http::fake([
            '*/rxcui/213269/properties.json' => Http::response([
                'properties' => [
                    'rxcui' => '213269',
                    'name' => 'Aspirin 81 MG Oral Tablet NEW',
                ]
            ], 200),
            '*/rxcui/213269/historystatus.json' => Http::response([
                'rxcuiStatusHistory' => [
                    'definitionalFeatures' => [
                        'ingredientAndStrength' => [
                            ['baseName' => 'New Aspirin']
                        ],
                        'doseFormGroupConcept' => [
                            ['doseFormGroupName' => 'New Oral Tablet']
                        ]
                    ]
                ]
            ], 200),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/medications', [
                'rxcui' => '213269'
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'drug_name' => 'Aspirin 81 MG Oral Tablet NEW',
                ]
            ]);

        // Verify snapshot was updated
        $this->assertDatabaseHas('drug_snapshots', [
            'rxcui' => '213269',
            'drug_name' => 'Aspirin 81 MG Oral Tablet NEW',
        ]);
    }
}
