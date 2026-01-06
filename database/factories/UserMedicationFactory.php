<?php

namespace Database\Factories;

use App\Models\DrugSnapshot;
use App\Models\User;
use App\Models\UserMedication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserMedication>
 */
class UserMedicationFactory extends Factory
{
    protected $model = UserMedication::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'rxcui' => DrugSnapshot::factory(),
        ];
    }

    /**
     * Associate with a specific user
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Associate with a specific drug snapshot
     */
    public function forDrug(DrugSnapshot $drug): static
    {
        return $this->state(fn (array $attributes) => [
            'rxcui' => $drug->rxcui,
        ]);
    }

    /**
     * Create with a specific RXCUI (will create or use existing snapshot)
     */
    public function withRxcui(string $rxcui): static
    {
        return $this->state(fn (array $attributes) => [
            'rxcui' => $rxcui,
        ]);
    }
}
