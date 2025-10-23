<?php

namespace App\Console\Commands;

use App\Models\Port;
use App\Models\PortSecurity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class UpdateShodanData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ports:update-shodan
                            {--port= : Update specific port number}
                            {--limit=1000 : Number of top ports to fetch from facets}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update port security data from Shodan API (uses free facets endpoint)';

    /**
     * Statistics for the command execution.
     */
    private int $updated = 0;
    private int $created = 0;
    private int $skipped = 0;
    private int $errors = 0;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Validate API key is configured
        if (! config('services.shodan.api_key')) {
            $this->error('SHODAN_API_KEY is not configured in .env file');
            $this->info('Please add: SHODAN_API_KEY=your_api_key_here');
            return self::FAILURE;
        }

        $this->info('Fetching Shodan port exposure data...');

        // Handle specific port update
        if ($port = $this->option('port')) {
            return $this->updateSpecificPort((int) $port);
        }

        // Fetch top ports via facets (free, no credits used)
        return $this->updateTopPorts();
    }

    /**
     * Update a specific port using the count endpoint.
     */
    private function updateSpecificPort(int $portNumber): int
    {
        $this->info("Querying Shodan for port {$portNumber}...");

        try {
            $data = $this->fetchPortCount($portNumber);
            $this->updateOrCreatePortSecurity($portNumber, $data);

            $this->newLine();
            $this->info("✓ Successfully updated port {$portNumber}");
            $this->info("  Exposed devices: " . number_format($data['total']));

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to update port {$portNumber}: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    /**
     * Update top N ports using facets (free endpoint).
     */
    private function updateTopPorts(): int
    {
        $limit = (int) $this->option('limit');

        try {
            // Fetch port facets from Shodan (free, no credits used)
            $facetData = $this->fetchPortFacets($limit);

            if (empty($facetData)) {
                $this->warn('No port data returned from Shodan facets');
                return self::FAILURE;
            }

            $this->info("Processing {$limit} ports from Shodan facets...");
            $progressBar = $this->output->createProgressBar(count($facetData));
            $progressBar->start();

            // Process each port from facets
            foreach ($facetData as $portData) {
                $portNumber = (int) $portData['value'];
                $exposedCount = (int) $portData['count'];

                try {
                    $this->updateOrCreatePortSecurity($portNumber, [
                        'total' => $exposedCount,
                    ]);

                    $this->updated++;
                } catch (\Exception $e) {
                    $this->errors++;
                    $this->newLine();
                    $this->error("Error updating port {$portNumber}: {$e->getMessage()}");
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine(2);

            // Display summary
            $this->displaySummary();

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to fetch Shodan data: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    /**
     * Fetch port facets from Shodan (free endpoint, no credits used).
     */
    private function fetchPortFacets(int $limit): array
    {
        $response = Http::timeout(30)
            ->get(config('services.shodan.base_url') . '/shodan/host/count', [
                'key' => config('services.shodan.api_key'),
                'query' => '*', // Query all devices to get comprehensive port distribution
                'facets' => "port:{$limit}",
            ]);

        if ($response->failed()) {
            throw new \Exception(
                "Shodan API request failed: " . $response->status() . " - " . $response->body()
            );
        }

        $data = $response->json();

        if (! isset($data['facets']['port'])) {
            throw new \Exception('Invalid response from Shodan API: missing facets.port');
        }

        return $data['facets']['port'];
    }

    /**
     * Fetch count for a specific port.
     */
    private function fetchPortCount(int $portNumber): array
    {
        $response = Http::timeout(30)
            ->get(config('services.shodan.base_url') . '/shodan/host/count', [
                'key' => config('services.shodan.api_key'),
                'query' => "port:{$portNumber}",
                'facets' => 'country:5', // Get top 5 countries
            ]);

        if ($response->failed()) {
            throw new \Exception(
                "Shodan API request failed: " . $response->status() . " - " . $response->body()
            );
        }

        return $response->json();
    }

    /**
     * Update or create port_security record.
     */
    private function updateOrCreatePortSecurity(int $portNumber, array $shodanData): void
    {
        // Find port in database (we need the port_id)
        $port = Port::where('port_number', $portNumber)->first();

        if (! $port) {
            $this->skipped++;
            $this->newLine();
            $this->warn("Port {$portNumber} not found in database, skipping...");
            return;
        }

        // Extract data
        $exposedCount = $shodanData['total'] ?? 0;
        $topCountries = isset($shodanData['facets']['country'])
            ? array_slice($shodanData['facets']['country'], 0, 5)
            : null;

        // Generate security recommendations based on exposure
        $securityRecommendations = $this->generateSecurityRecommendations($port, $exposedCount);

        // Update or create port_security record
        PortSecurity::updateOrCreate(
            ['port_id' => $port->id],
            [
                'shodan_exposed_count' => $exposedCount,
                'shodan_updated_at' => now(),
                'top_countries' => $topCountries,
                'security_recommendations' => $securityRecommendations,
            ]
        );
    }

    /**
     * Generate security recommendations based on exposure count and port characteristics.
     */
    private function generateSecurityRecommendations(Port $port, int $exposedCount): ?string
    {
        $recommendations = [];

        // High exposure warning
        if ($exposedCount > 1000000) {
            $recommendations[] = "⚠️ Extremely high exposure ({$exposedCount} devices) - prime target for attackers";
        } elseif ($exposedCount > 100000) {
            $recommendations[] = "⚠️ High exposure ({$exposedCount} devices) - implement strong security measures";
        } elseif ($exposedCount > 10000) {
            $recommendations[] = "Moderate exposure ({$exposedCount} devices) - follow security best practices";
        }

        // Encryption recommendation
        if (! $port->encrypted_default && in_array($port->port_number, [21, 23, 80, 8080, 3306, 5432])) {
            $recommendations[] = "🔒 Consider using encrypted alternative (HTTPS, SFTP, SSH, TLS)";
        }

        // Firewall recommendation
        if ($exposedCount > 10000) {
            $recommendations[] = "🛡️ Restrict access via firewall - only allow trusted IPs";
        }

        // High-risk ports
        if (in_array($port->port_number, [3389, 22, 23, 445, 139, 135])) {
            $recommendations[] = "🚨 High-risk port - disable if not needed, use VPN for remote access";
        }

        // Database ports
        if (in_array($port->port_number, [3306, 5432, 1433, 27017, 6379, 9200])) {
            $recommendations[] = "🗄️ Database port - never expose to internet, use private network only";
        }

        return empty($recommendations) ? null : implode("\n", $recommendations);
    }

    /**
     * Display execution summary.
     */
    private function displaySummary(): void
    {
        $this->info('Shodan data update completed!');
        $this->table(
            ['Status', 'Count'],
            [
                ['Updated', $this->updated],
                ['Created', $this->created],
                ['Skipped', $this->skipped],
                ['Errors', $this->errors],
            ]
        );
    }
}
