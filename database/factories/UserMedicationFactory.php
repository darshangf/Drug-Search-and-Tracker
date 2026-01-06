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
}
