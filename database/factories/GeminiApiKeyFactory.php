<?php

namespace Database\Factories;

use App\Models\GeminiApiKey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GeminiApiKey>
 */
class GeminiApiKeyFactory extends Factory
{
    protected $model = GeminiApiKey::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' Key',
            'key' => 'AIzaSy'.fake()->regexify('[A-Za-z0-9_-]{33}'),
            'is_active' => true,
            'is_default' => false,
            'priority' => 0,
            'status' => 'active',
            'rate_limited_until' => null,
            'last_used_at' => null,
            'last_tested_at' => null,
            'last_error_message' => null,
            'total_requests' => 0,
            'successful_requests' => 0,
            'failed_requests' => 0,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function rateLimited(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rate_limited',
            'rate_limited_until' => now()->addMinutes(10),
            'last_error_message' => 'Resource exhausted: quota exceeded',
        ]);
    }

    public function invalid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'invalid',
            'last_error_message' => 'API_KEY_INVALID: API key not valid. Please pass a valid API key.',
        ]);
    }
}
