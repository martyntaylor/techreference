<?php

namespace App\Console\Commands;

use App\Models\Port;
use App\Models\PortSecurity;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
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
            try {
                // Check if cached to avoid unnecessary rate limiting
                $cacheKey = 'cve:service:'.md5($serviceName);
                $isCached = Cache::has($cacheKey);

                // Fetch CVE data for this service (with caching)
                $cveData = $this->fetchCveDataForService($serviceName, $cacheKey);

                // Update all ports using this service
                foreach ($servicePorts as $port) {
                    $this->updatePortSecurity($port, $cveData);
                    $this->updated++;
                    $progressBar->advance();
                }

                $this->processed += $servicePorts->count();

                // Rate limiting: Only sleep after actual API calls, not cached data
                if (! $isCached) {
                    sleep($this->requestDelay);
                }
            } catch (\Exception $e) {
                $this->errors += $servicePorts->count();
                $progressBar->advance($servicePorts->count());
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
     *
     * @return Collection<int, Port>
     */
    private function getPortsToProcess(): Collection
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
     *
     * @param  Collection<int, Port>  $ports
     * @return Collection<string, Collection<int, Port>>
     */
    private function groupPortsByService(Collection $ports): Collection
    {
        return $ports->groupBy(function (Port $port) {
            // Normalize service name for grouping
            return strtolower(trim($port->service_name));
        });
    }

    /**
     * Fetch CVE data for a service from NVD API (with caching).
     *
     * @return array{count: int, latest_cve: string|null, latest_date: string|null, critical_count: int, high_count: int, medium_count: int, low_count: int, avg_score: float|null, critical_recent: array<int, array{id: string, published: string, cvss: float, severity: string, description: string}>, weakness_types: array<string, int>}
     */
    private function fetchCveDataForService(string $serviceName, string $cacheKey): array
    {
        // Return cached data if available
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // Query API and cache result (1 hour TTL)
        $data = $this->queryNvdApi($serviceName);
        Cache::put($cacheKey, $data, 3600);

        return $data;
    }

    /**
     * Query NVD API for CVE data with pagination support.
     *
     * @return array{count: int, latest_cve: string|null, latest_date: string|null, critical_count: int, high_count: int, medium_count: int, low_count: int, avg_score: float|null, critical_recent: array<int, array{id: string, published: string, cvss: float, severity: string, description: string}>, weakness_types: array<string, int>}
     */
    private function queryNvdApi(string $serviceName): array
    {
        $url = $this->nvdEndpoint;
        $resultsPerPage = 2000; // NVD API max per page
        $startIndex = 0;
        $accumulated = ['totalResults' => 0, 'vulnerabilities' => []];

        do {
            // Build request for each page
            $request = Http::timeout(30)
                ->retry(3, 1000); // Retry 3 times with 1s delay

            // Add API key and headers if available
            if (! empty($this->nvdApiKey)) {
                $request->withHeaders([
                    'apiKey' => $this->nvdApiKey,
                    'User-Agent' => 'techreference/ports-update-cve',
                    'Accept' => 'application/json',
                ]);
            } else {
                $request->withHeaders([
                    'User-Agent' => 'techreference/ports-update-cve',
                    'Accept' => 'application/json',
                ]);
            }

            // Query parameters with pagination
            $params = [
                'keywordSearch' => $serviceName,
                'resultsPerPage' => $resultsPerPage,
                'startIndex' => $startIndex,
            ];

            $response = $request->get($url, $params);

            if (! $response->successful()) {
                throw new \Exception("NVD API request failed: {$response->status()}");
            }

            $page = $response->json() ?? ['totalResults' => 0, 'vulnerabilities' => []];

            // Update total results count
            $accumulated['totalResults'] = max($accumulated['totalResults'], (int) ($page['totalResults'] ?? 0));

            // Merge vulnerabilities from this page
            $pageVulnerabilities = $page['vulnerabilities'] ?? [];
            if (! empty($pageVulnerabilities)) {
                $accumulated['vulnerabilities'] = array_merge($accumulated['vulnerabilities'], $pageVulnerabilities);
            }

            $startIndex += $resultsPerPage;

            // Respect rate limits between pages (except for last iteration)
            if ($startIndex < $accumulated['totalResults']) {
                sleep($this->requestDelay);
            }
        } while ($startIndex < $accumulated['totalResults']);

        return $this->parseCveResponse($accumulated);
    }

    /**
     * Parse NVD API response and extract relevant CVE data.
     *
     * @param  array<string, mixed>  $data
     * @return array{count: int, latest_cve: string|null, latest_date: string|null, critical_count: int, high_count: int, medium_count: int, low_count: int, avg_score: float|null, critical_recent: array<int, array{id: string, published: string, cvss: float, severity: string, description: string}>, weakness_types: array<string, int>}
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
            $isRejectedOrDisputed = false;
            $description = '';
            foreach ($descriptions as $desc) {
                if (isset($desc['lang']) && $desc['lang'] === 'en') {
                    $description = $desc['value'] ?? '';
                }
                $descUpper = strtoupper($desc['value'] ?? '');
                if (str_contains($descUpper, 'REJECT') || str_contains($descUpper, 'DISPUTED')) {
                    $isRejectedOrDisputed = true;
                    break;
                }
            }

            if ($isRejectedOrDisputed || ! $cveId || ! $published) {
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
     *
     * @param  array<string, mixed>  $cve
     * @return array{score: float, severity: string}
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
     *
     * @param  array{count: int, latest_cve: string|null, latest_date: string|null, critical_count: int, high_count: int, medium_count: int, low_count: int, avg_score: float|null, critical_recent: array<int, array{id: string, published: string, cvss: float, severity: string, description: string}>, weakness_types: array<string, int>}  $cveData
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
                ['Errors', $this->errors],
            ]
        );

        if ($this->errors > 0) {
            $this->warn("Warning: {$this->errors} ports encountered errors during processing.");
        }
    }
}
