<?php

namespace App\Console\Commands;

use App\Models\Port;
use App\Models\PortSecurity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class UpdateCveData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ports:update-cve
                            {--port= : Update specific port number}
                            {--service= : Update all ports for specific service name}
                            {--force : Ignore last update timestamp and force update}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update CVE (vulnerability) data for ports from NVD API';

    /**
     * NVD API configuration.
     */
    private string $nvdEndpoint;

    private ?string $nvdApiKey;

    private int $requestDelay;

    private int $batchSize;

    /**
     * Statistics tracking.
     */
    private int $processed = 0;

    private int $updated = 0;

    private int $skipped = 0;

    private int $errors = 0;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->loadConfiguration();
        $this->displayConfiguration();

        // Get ports to process
        $ports = $this->getPortsToProcess();

        if ($ports->isEmpty()) {
            $this->info('No ports to process.');

            return self::SUCCESS;
        }

        $this->info("Processing {$ports->count()} ports...");
        $progressBar = $this->output->createProgressBar($ports->count());
        $progressBar->start();

        // Group ports by service name to minimize API calls
        $serviceGroups = $this->groupPortsByService($ports);
        $this->info("\nGrouped into {$serviceGroups->count()} unique services");
        $this->newLine();

        foreach ($serviceGroups as $serviceName => $servicePorts) {
            $progressBar->advance($servicePorts->count());

            try {
                // Fetch CVE data for this service (with caching)
                $cveData = $this->fetchCveDataForService($serviceName);

                // Update all ports using this service
                foreach ($servicePorts as $port) {
                    $this->updatePortSecurity($port, $cveData);
                    $this->updated++;
                }

                $this->processed += $servicePorts->count();

                // Rate limiting: Sleep between API requests
                sleep($this->requestDelay);
            } catch (\Exception $e) {
                $this->errors += $servicePorts->count();
                $this->newLine();
                $this->error("Error processing service '{$serviceName}': {$e->getMessage()}");
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->displaySummary();

        return self::SUCCESS;
    }

    /**
     * Load configuration from environment.
     */
    private function loadConfiguration(): void
    {
        $this->nvdEndpoint = config('services.nvd.endpoint', 'https://services.nvd.nist.gov/rest/json/cves/2.0');
        $this->nvdApiKey = config('services.nvd.api_key');
        $this->batchSize = config('services.nvd.batch_size', 5);

        // Auto-detect rate limit based on API key presence
        if (! empty($this->nvdApiKey)) {
            // With API key: 50 requests per 30 seconds (0.6s delay)
            $this->requestDelay = config('services.nvd.delay_seconds', 1);
        } else {
            // Without API key: 5 requests per 30 seconds (6s delay)
            $this->requestDelay = config('services.nvd.delay_seconds', 6);
        }
    }

    /**
     * Display current configuration.
     */
    private function displayConfiguration(): void
    {
        $this->info('=== CVE Update Configuration ===');
        $this->info("Endpoint: {$this->nvdEndpoint}");
        $this->info('API Key: '.(! empty($this->nvdApiKey) ? 'Present (faster rate limit)' : 'Not set (public rate limit)'));
        $this->info("Request Delay: {$this->requestDelay} seconds");
        $this->info("Batch Size: {$this->batchSize}");
        $this->newLine();
    }

    /**
     * Get ports to process based on command options.
     */
    private function getPortsToProcess()
    {
        $query = Port::query();

        // Filter by specific port number
        if ($portNumber = $this->option('port')) {
            $query->where('port_number', $portNumber);
        }

        // Filter by service name
        if ($serviceName = $this->option('service')) {
            $query->where('service_name', 'LIKE', "%{$serviceName}%");
        }

        // Only process ports with a service name
        $query->whereNotNull('service_name')
            ->where('service_name', '!=', '');

        // Optionally skip recently updated ports (unless --force)
        if (! $this->option('force')) {
            // Skip ports updated in last 24 hours
            $query->whereDoesntHave('security', function ($q) {
                $q->where('cve_updated_at', '>', now()->subHours(24));
            });
        }

        return $query->get();
    }

    /**
     * Group ports by service name to minimize API calls.
     */
    private function groupPortsByService($ports)
    {
        return $ports->groupBy(function ($port) {
            // Normalize service name for grouping
            return strtolower(trim($port->service_name));
        });
    }

    /**
     * Fetch CVE data for a service from NVD API (with caching).
     */
    private function fetchCveDataForService(string $serviceName): array
    {
        // Cache key based on service name
        $cacheKey = "cve:service:".md5($serviceName);

        // Check cache (1 hour TTL)
        return Cache::remember($cacheKey, 3600, function () use ($serviceName) {
            return $this->queryNvdApi($serviceName);
        });
    }

    /**
     * Query NVD API for CVE data.
     */
    private function queryNvdApi(string $serviceName): array
    {
        $url = $this->nvdEndpoint;

        // Build request
        $request = Http::timeout(30)
            ->retry(3, 1000); // Retry 3 times with 1s delay

        // Add API key if available
        if (! empty($this->nvdApiKey)) {
            $request->withHeaders([
                'apiKey' => $this->nvdApiKey,
            ]);
        }

        // Query parameters
        $params = [
            'keywordSearch' => $serviceName,
            'resultsPerPage' => 2000, // Max results
        ];

        // Note: NVD API has a 120-day max range limit, so we'll query without date filters
        // to get all CVEs, then filter by date in code if needed

        $response = $request->get($url, $params);

        if (! $response->successful()) {
            throw new \Exception("NVD API request failed: {$response->status()}");
        }

        $data = $response->json();

        // Handle null response
        if ($data === null) {
            return [
                'count' => 0,
                'latest_cve' => null,
                'latest_date' => null,
            ];
        }

        return $this->parseCveResponse($data);
    }

    /**
     * Parse NVD API response and extract relevant CVE data.
     */
    private function parseCveResponse(array $data): array
    {
        $totalResults = $data['totalResults'] ?? 0;
        $vulnerabilities = $data['vulnerabilities'] ?? [];

        if ($totalResults === 0 || empty($vulnerabilities)) {
            return [
                'count' => 0,
                'latest_cve' => null,
                'latest_date' => null,
                'critical_count' => 0,
                'high_count' => 0,
                'medium_count' => 0,
                'low_count' => 0,
                'avg_score' => null,
                'critical_recent' => [],
                'weakness_types' => [],
            ];
        }

        $validCves = [];
        $severityCounts = ['CRITICAL' => 0, 'HIGH' => 0, 'MEDIUM' => 0, 'LOW' => 0];
        $cvssScores = [];
        $weaknessTypes = [];
        $criticalRecent = [];

        foreach ($vulnerabilities as $vuln) {
            $cve = $vuln['cve'] ?? null;
            if (! $cve) {
                continue;
            }

            $cveId = $cve['id'] ?? null;
            $published = $cve['published'] ?? null;

            // Skip rejected/disputed CVEs
            $descriptions = $cve['descriptions'] ?? [];
            $isRejected = false;
            $description = '';
            foreach ($descriptions as $desc) {
                if (isset($desc['lang']) && $desc['lang'] === 'en') {
                    $description = $desc['value'] ?? '';
                }
                if (isset($desc['value']) && str_contains(strtoupper($desc['value']), 'REJECT')) {
                    $isRejected = true;
                    break;
                }
            }

            if ($isRejected || ! $cveId || ! $published) {
                continue;
            }

            // Extract CVSS score and severity
            $cvssData = $this->extractCvssData($cve);
            $cvssScore = $cvssData['score'];
            $severity = $cvssData['severity'];

            // Only count CVEs with valid CVSS scores
            if ($cvssScore > 0) {
                $validCves[] = [
                    'id' => $cveId,
                    'published' => $published,
                    'cvss' => $cvssScore,
                    'severity' => $severity,
                    'description' => $description,
                ];

                $cvssScores[] = $cvssScore;

                // Count by severity
                if (isset($severityCounts[$severity])) {
                    $severityCounts[$severity]++;
                } else {
                    // Unknown severity - log it for debugging
                    $this->warn("Unknown severity '{$severity}' for {$cveId}");
                }

                // Track critical/high CVEs for detailed storage
                if (in_array($severity, ['CRITICAL', 'HIGH']) && count($criticalRecent) < 10) {
                    $criticalRecent[] = [
                        'id' => $cveId,
                        'published' => $published,
                        'cvss' => $cvssScore,
                        'severity' => $severity,
                        'description' => mb_substr($description, 0, 200), // Truncate for storage
                    ];
                }

                // Extract weakness types (CWE)
                $weaknesses = $cve['weaknesses'] ?? [];
                foreach ($weaknesses as $weakness) {
                    $weaknessDescs = $weakness['description'] ?? [];
                    foreach ($weaknessDescs as $weaknessDesc) {
                        if (isset($weaknessDesc['value'])) {
                            $cweValue = $weaknessDesc['value'];
                            if (! isset($weaknessTypes[$cweValue])) {
                                $weaknessTypes[$cweValue] = 0;
                            }
                            $weaknessTypes[$cweValue]++;
                        }
                    }
                }
            }
        }

        // Sort by published date (newest first)
        usort($validCves, function ($a, $b) {
            return strcmp($b['published'], $a['published']);
        });

        // Calculate average CVSS score
        $avgScore = ! empty($cvssScores) ? round(array_sum($cvssScores) / count($cvssScores), 1) : null;

        // Get top 10 weakness types
        arsort($weaknessTypes);
        $topWeaknesses = array_slice($weaknessTypes, 0, 10, true);

        return [
            'count' => count($validCves),
            'latest_cve' => $validCves[0]['id'] ?? null,
            'latest_date' => $validCves[0]['published'] ?? null,
            'critical_count' => $severityCounts['CRITICAL'],
            'high_count' => $severityCounts['HIGH'],
            'medium_count' => $severityCounts['MEDIUM'],
            'low_count' => $severityCounts['LOW'],
            'avg_score' => $avgScore,
            'critical_recent' => $criticalRecent,
            'weakness_types' => $topWeaknesses,
        ];
    }

    /**
     * Extract CVSS score and severity from CVE metrics.
     */
    private function extractCvssData(array $cve): array
    {
        $metrics = $cve['metrics'] ?? [];

        // Try CVSS v3.1
        if (isset($metrics['cvssMetricV31'][0]['cvssData']['baseScore'])) {
            return [
                'score' => (float) $metrics['cvssMetricV31'][0]['cvssData']['baseScore'],
                'severity' => $metrics['cvssMetricV31'][0]['cvssData']['baseSeverity'] ?? 'UNKNOWN',
            ];
        }

        // Try CVSS v3.0
        if (isset($metrics['cvssMetricV30'][0]['cvssData']['baseScore'])) {
            return [
                'score' => (float) $metrics['cvssMetricV30'][0]['cvssData']['baseScore'],
                'severity' => $metrics['cvssMetricV30'][0]['cvssData']['baseSeverity'] ?? 'UNKNOWN',
            ];
        }

        // Try CVSS v2.0
        if (isset($metrics['cvssMetricV2'][0]['cvssData']['baseScore'])) {
            $score = (float) $metrics['cvssMetricV2'][0]['cvssData']['baseScore'];
            // Use baseSeverity if available, otherwise map based on score
            $severity = $metrics['cvssMetricV2'][0]['baseSeverity'] ?? $this->mapCvssV2Severity($score);

            return [
                'score' => $score,
                'severity' => strtoupper($severity),
            ];
        }

        return ['score' => 0.0, 'severity' => 'UNKNOWN'];
    }

    /**
     * Map CVSS v2.0 score to severity level.
     */
    private function mapCvssV2Severity(float $score): string
    {
        if ($score >= 7.0) {
            return 'HIGH';
        }
        if ($score >= 4.0) {
            return 'MEDIUM';
        }

        return 'LOW';
    }

    /**
     * Update port_security table with CVE data.
     */
    private function updatePortSecurity(Port $port, array $cveData): void
    {
        PortSecurity::updateOrCreate(
            ['port_id' => $port->id],
            [
                'cve_count' => $cveData['count'],
                'cve_critical_count' => $cveData['critical_count'],
                'cve_high_count' => $cveData['high_count'],
                'cve_medium_count' => $cveData['medium_count'],
                'cve_low_count' => $cveData['low_count'],
                'cve_avg_score' => $cveData['avg_score'],
                'latest_cve' => $cveData['latest_cve'],
                'cve_critical_recent' => ! empty($cveData['critical_recent']) ? $cveData['critical_recent'] : null,
                'cve_weakness_types' => ! empty($cveData['weakness_types']) ? $cveData['weakness_types'] : null,
                'cve_updated_at' => now(),
            ]
        );
    }

    /**
     * Display summary statistics.
     */
    private function displaySummary(): void
    {
        $this->info('=== CVE Update Summary ===');
        $this->table(
            ['Status', 'Count'],
            [
                ['Processed', $this->processed],
                ['Updated', $this->updated],
                ['Skipped', $this->skipped],
                ['Errors', $this->errors],
            ]
        );

        if ($this->errors > 0) {
            $this->warn("Warning: {$this->errors} ports encountered errors during processing.");
        }
    }
}
