<?php

namespace Database\Factories;

use App\Models\Port;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Port>
 */
class PortFactory extends Factory
{
    protected $model = Port::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $protocols = ['TCP', 'UDP', 'SCTP', 'DCCP'];
        $protocol = fake()->randomElement($protocols);

        return [
            'port_number' => fake()->numberBetween(1, 65535),
            'protocol' => $protocol,
            'transport_protocol' => strtolower($protocol),
            'service_name' => fake()->word(),
            'description' => fake()->sentence(),
            'iana_status' => fake()->randomElement(['Official', 'Reserved', 'Unassigned']),
            'iana_official' => fake()->boolean(70),
            'encrypted_default' => fake()->boolean(30),
            'risk_level' => fake()->randomElement(['Low', 'Medium', 'High']),
            'iana_updated_at' => fake()->optional()->dateTimeBetween('-1 year', 'now'),
        ];
    }

    /**
     * Indicate that the port is for MySQL service.
     */
    public function mysql(): static
    {
        return $this->state(fn (array $attributes) => [
            'port_number' => 3306,
            'protocol' => 'TCP',
            'transport_protocol' => 'tcp',
            'service_name' => 'mysql',
            'description' => 'MySQL Database Server',
            'iana_status' => 'Official',
            'iana_official' => true,
            'encrypted_default' => false,
            'risk_level' => 'Medium',
        ]);
    }

    /**
     * Indicate that the port is for PostgreSQL service.
     */
    public function postgresql(): static
    {
        return $this->state(fn (array $attributes) => [
            'port_number' => 5432,
            'protocol' => 'TCP',
            'transport_protocol' => 'tcp',
            'service_name' => 'postgresql',
            'description' => 'PostgreSQL Database',
            'iana_status' => 'Official',
            'iana_official' => true,
            'encrypted_default' => false,
            'risk_level' => 'Medium',
        ]);
    }

    /**
     * Indicate that the port is for HTTPS service.
     */
    public function https(): static
    {
        return $this->state(fn (array $attributes) => [
            'port_number' => 443,
            'protocol' => 'TCP',
            'transport_protocol' => 'tcp',
            'service_name' => 'https',
            'description' => 'HTTP over TLS/SSL',
            'iana_status' => 'Official',
            'iana_official' => true,
            'encrypted_default' => true,
            'risk_level' => 'Low',
        ]);
    }

    /**
     * Indicate that the port is for SSH service.
     */
    public function ssh(): static
    {
        return $this->state(fn (array $attributes) => [
            'port_number' => 22,
            'protocol' => 'TCP',
            'transport_protocol' => 'tcp',
            'service_name' => 'ssh',
            'description' => 'Secure Shell (SSH)',
            'iana_status' => 'Official',
            'iana_official' => true,
            'encrypted_default' => true,
            'risk_level' => 'Low',
        ]);
    }

    /**
     * Indicate that the port has no service name.
     */
    public function unassigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'service_name' => null,
            'description' => null,
            'iana_status' => 'Unassigned',
            'iana_official' => false,
        ]);
    }
}
