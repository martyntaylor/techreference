<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Port;
use App\Models\PortSecurity;
use App\Models\Software;
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
                'facets' => 'country:10,product:10,org:10,os:10,asn:10', // Get all useful facets
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
        // Find or create port in database
        $port = Port::where('port_number', $portNumber)->first();

        if (! $port) {
            // Create a minimal port record for non-IANA ports found in Shodan
            $port = $this->createNonIanaPort($portNumber, $shodanData);
            $this->created++;
        }

        // Extract data
        $exposedCount = $shodanData['total'] ?? 0;
        $topCountries = isset($shodanData['facets']['country'])
            ? array_slice($shodanData['facets']['country'], 0, 10)
            : null;
        $topProducts = isset($shodanData['facets']['product'])
            ? array_slice($shodanData['facets']['product'], 0, 10)
            : null;
        $topOrganizations = isset($shodanData['facets']['org'])
            ? array_slice($shodanData['facets']['org'], 0, 10)
            : null;
        $topOperatingSystems = isset($shodanData['facets']['os'])
            ? array_slice($shodanData['facets']['os'], 0, 10)
            : null;
        $topAsns = isset($shodanData['facets']['asn'])
            ? array_slice($shodanData['facets']['asn'], 0, 10)
            : null;

        // Generate security recommendations based on exposure
        $securityRecommendations = $this->generateSecurityRecommendations($port, $exposedCount);

        // Build update data - always update count and timestamp
        $updateData = [
            'shodan_exposed_count' => $exposedCount,
            'shodan_updated_at' => now(),
            'security_recommendations' => $securityRecommendations,
        ];

        // Only update facet data if we have it (to avoid overwriting with null in bulk mode)
        if ($topCountries !== null) {
            $updateData['top_countries'] = $topCountries;
        }
        if ($topProducts !== null) {
            $updateData['top_products'] = $topProducts;
        }
        if ($topOrganizations !== null) {
            $updateData['top_organizations'] = $topOrganizations;
        }
        if ($topOperatingSystems !== null) {
            $updateData['top_operating_systems'] = $topOperatingSystems;
        }
        if ($topAsns !== null) {
            $updateData['top_asns'] = $topAsns;
        }

        // Update or create port_security record
        PortSecurity::updateOrCreate(
            ['port_id' => $port->id],
            $updateData
        );

        // Create software records from product data and link to port
        if ($topProducts) {
            $this->createAndLinkSoftware($port, $topProducts);
        }
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
     * Create software records and link to port.
     */
    private function createAndLinkSoftware(Port $port, array $products): void
    {
        foreach ($products as $productData) {
            $productName = $productData['value'] ?? null;
            $exposureCount = $productData['count'] ?? 0;

            if (! $productName || $exposureCount < 100) {
                continue; // Skip products with very low exposure
            }

            // Determine category from product name
            $categoryId = $this->determineSoftwareCategoryId($productName, $port->port_number);

            // Find or create software (using slug as unique identifier)
            $slug = \Illuminate\Support\Str::slug($productName);
            $software = Software::where('slug', $slug)->first();

            if (! $software) {
                $software = Software::create([
                    'name' => $productName,
                    'slug' => $slug,
                    'category_id' => $categoryId,
                    'is_active' => true,
                ]);
            }

            // Link software to port if not already linked
            if (! $port->software()->where('software_id', $software->id)->exists()) {
                $port->software()->attach($software->id, [
                    'is_default' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Determine software category ID from product name and port.
     */
    private function determineSoftwareCategoryId(string $productName, int $portNumber): ?int
    {
        $productLower = strtolower($productName);
        $categorySlug = null;

        // Web servers
        if (in_array($portNumber, [80, 443, 8080, 8443, 8888]) ||
            str_contains($productLower, 'nginx') ||
            str_contains($productLower, 'apache') ||
            str_contains($productLower, 'iis') ||
            str_contains($productLower, 'httpd') ||
            str_contains($productLower, 'cloudflare') ||
            str_contains($productLower, 'akamai') ||
            str_contains($productLower, 'cloudfront') ||
            str_contains($productLower, 'elb')) {
            $categorySlug = 'web-services';
        }
        // Databases
        elseif (in_array($portNumber, [3306, 5432, 1433, 27017, 6379, 5984, 9200]) ||
            str_contains($productLower, 'mysql') ||
            str_contains($productLower, 'postgres') ||
            str_contains($productLower, 'mariadb') ||
            str_contains($productLower, 'mongodb') ||
            str_contains($productLower, 'redis') ||
            str_contains($productLower, 'elasticsearch')) {
            $categorySlug = 'database';
        }
        // SSH/Remote Access
        elseif (in_array($portNumber, [22, 3389, 5900]) ||
            str_contains($productLower, 'ssh') ||
            str_contains($productLower, 'openssh') ||
            str_contains($productLower, 'dropbear')) {
            $categorySlug = 'remote-access';
        }
        // FTP
        elseif (in_array($portNumber, [21, 20, 990]) ||
            str_contains($productLower, 'ftp') ||
            str_contains($productLower, 'vsftpd') ||
            str_contains($productLower, 'proftpd')) {
            $categorySlug = 'file-transfer';
        }
        // Email
        elseif (in_array($portNumber, [25, 587, 465, 110, 995, 143, 993]) ||
            str_contains($productLower, 'smtp') ||
            str_contains($productLower, 'postfix') ||
            str_contains($productLower, 'exim') ||
            str_contains($productLower, 'dovecot')) {
            $categorySlug = 'email';
        }
        // DNS
        elseif ($portNumber == 53 || str_contains($productLower, 'bind') || str_contains($productLower, 'dns')) {
            $categorySlug = 'dns';
        }

        // Look up category by slug
        if ($categorySlug) {
            $category = Category::where('slug', $categorySlug)->first();
            return $category ? $category->id : null;
        }

        return null;
    }

    /**
     * Create a minimal port record for non-IANA ports discovered in Shodan.
     */
    private function createNonIanaPort(int $portNumber, array $shodanData): Port
    {
        // Determine protocol (default to TCP, but check for common UDP ports)
        $protocol = $this->guessProtocol($portNumber);

        // Try to infer service name from products if available
        $serviceName = $this->inferServiceName($portNumber, $shodanData);

        // Create port record
        $port = Port::create([
            'port_number' => $portNumber,
            'protocol' => $protocol,
            'transport_protocol' => strtolower($protocol),
            'service_name' => $serviceName,
            'description' => "Port discovered in Shodan data with " . number_format($shodanData['total'] ?? 0) . " exposed devices. Not officially registered with IANA.",
            'iana_status' => 'Unregistered',
            'iana_official' => false,
            'encrypted_default' => false,
            'risk_level' => $this->determineRiskLevelFromExposure($shodanData['total'] ?? 0),
            'data_source' => 'Shodan',
        ]);

        return $port;
    }

    /**
     * Guess the protocol (TCP/UDP) for a port.
     */
    private function guessProtocol(int $portNumber): string
    {
        // Common UDP ports
        $commonUdpPorts = [53, 67, 68, 69, 123, 161, 162, 500, 514, 1900, 4500];

        return in_array($portNumber, $commonUdpPorts) ? 'UDP' : 'TCP';
    }

    /**
     * Infer service name from Shodan product data or port number.
     */
    private function inferServiceName(int $portNumber, array $shodanData): string
    {
        // If we have product facets, use the most common product as service name
        if (isset($shodanData['facets']['product'][0]['value'])) {
            $topProduct = $shodanData['facets']['product'][0]['value'];
            return ucfirst(strtolower($topProduct));
        }

        // Otherwise, return generic name based on port range
        if ($portNumber < 1024) {
            return "System Port {$portNumber}";
        } elseif ($portNumber < 49152) {
            return "Registered Port {$portNumber}";
        } else {
            return "Dynamic Port {$portNumber}";
        }
    }

    /**
     * Determine risk level based on exposure count alone.
     */
    private function determineRiskLevelFromExposure(int $exposedCount): string
    {
        if ($exposedCount > 1000000) {
            return 'High';
        } elseif ($exposedCount > 10000) {
            return 'Medium';
        } else {
            return 'Low';
        }
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
