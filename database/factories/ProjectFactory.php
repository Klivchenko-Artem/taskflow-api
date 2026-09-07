<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'name' => $this->faker->unique()->words(3, true),
            'description' => $this->faker->sentence(),
        ];
    }

    /** Проект с владельцем в участниках — как его создаёт API. */
    public function configure(): static
    {
        return $this->afterCreating(function (Project $project) {
            $project->members()->syncWithoutDetaching([
                $project->owner_id => ['role' => 'owner'],
            ]);
        });
    }
}
