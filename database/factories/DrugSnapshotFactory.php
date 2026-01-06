<?php

namespace Database\Factories;

use App\Models\DrugSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DrugSnapshot>
 */
class DrugSnapshotFactory extends Factory
{
    protected $model = DrugSnapshot::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $drugTypes = ['Tablet', 'Capsule', 'Solution', 'Injection', 'Oral Tablet', 'Oral Capsule'];
        $ingredients = ['Aspirin', 'Ibuprofen', 'Acetaminophen', 'Lisinopril', 'Metformin', 'Atorvastatin', 'Amlodipine', 'Omeprazole'];
        $dosageForms = ['Oral Tablet', 'Oral Capsule', 'Injectable Solution', 'Topical Cream', 'Oral Solution', 'Extended Release Tablet'];
        
        $ingredient = fake()->randomElement($ingredients);
        $strength = fake()->randomElement([5, 10, 20, 25, 50, 81, 100, 200, 325, 500]);
        $drugType = fake()->randomElement($drugTypes);

        return [
            'rxcui' => fake()->unique()->numerify('######'),
            'drug_name' => "{$ingredient} {$strength} MG {$drugType}",
            'ingredient_base_names' => [$ingredient],
            'dosage_forms' => [fake()->randomElement($dosageForms)],
            'last_synced_at' => now(),
        ];
    }

    /**
     * Indicate that the snapshot is stale (older than 10 days).
     */
    public function stale(int $days = 11): static
    {
        return $this->state(fn (array $attributes) => [
            'last_synced_at' => now()->subDays($days),
        ]);
    }

    /**
     * Indicate that the snapshot is very stale (older than 30 days).
     */
    public function veryStale(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_synced_at' => now()->subDays(31),
        ]);
    }

    /**
     * Indicate that the snapshot is fresh (recently synced).
     */
    public function fresh(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_synced_at' => now(),
        ]);
    }

    /**
     * Create a snapshot for Aspirin 81 MG
     */
    public function aspirin81(): static
    {
        return $this->state(fn (array $attributes) => [
            'rxcui' => '213269',
            'drug_name' => 'Aspirin 81 MG Oral Tablet',
            'ingredient_base_names' => ['Aspirin'],
            'dosage_forms' => ['Oral Tablet'],
        ]);
    }

    /**
     * Create a snapshot for Aspirin 325 MG
     */
    public function aspirin325(): static
    {
        return $this->state(fn (array $attributes) => [
            'rxcui' => '198440',
            'drug_name' => 'Aspirin 325 MG Oral Tablet',
            'ingredient_base_names' => ['Aspirin'],
            'dosage_forms' => ['Oral Tablet'],
        ]);
    }

    /**
     * Create a snapshot for Ibuprofen 200 MG
     */
    public function ibuprofen200(): static
    {
        return $this->state(fn (array $attributes) => [
            'rxcui' => '310964',
            'drug_name' => 'Ibuprofen 200 MG Oral Tablet',
            'ingredient_base_names' => ['Ibuprofen'],
            'dosage_forms' => ['Oral Tablet'],
        ]);
    }

    /**
     * Create a snapshot with specific RXCUI
     */
    public function withRxcui(string $rxcui): static
    {
        return $this->state(fn (array $attributes) => [
            'rxcui' => $rxcui,
        ]);
    }

    /**
     * Create a snapshot with specific drug name
     */
    public function withName(string $name): static
    {
        return $this->state(fn (array $attributes) => [
            'drug_name' => $name,
        ]);
    }

    /**
     * Create a snapshot with multiple ingredients
     */
    public function multipleIngredients(array $ingredients): static
    {
        return $this->state(fn (array $attributes) => [
            'ingredient_base_names' => $ingredients,
            'drug_name' => implode(' / ', $ingredients) . ' ' . fake()->randomElement([5, 10, 20]) . ' MG Tablet',
        ]);
    }
}
