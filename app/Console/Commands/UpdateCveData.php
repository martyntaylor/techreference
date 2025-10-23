<?php

namespace App\Console\Commands;

use App\Models\Cve;
use App\Models\Port;
use App\Models\PortSecurity;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

        // Group ports by port_number to avoid processing duplicates
        $uniquePortNumbers = $ports->pluck('port_number')->unique()->sort()->values();

        $this->info("Processing {$uniquePortNumbers->count()} unique port numbers...");
        $progressBar = $this->output->createProgressBar($uniquePortNumbers->count());
        $progressBar->start();

        foreach ($uniquePortNumbers as $portNumber) {
            try {
                // Check if cached
                $cacheKey = 'cve:port:' . $portNumber;
                $isCached = Cache::has($cacheKey);

                // Fetch CVE data for this port number (with caching)
                $cveRecords = $this->fetchCveDataForPort($portNumber, $cacheKey);

                // Store CVEs and link to port
                $this->storeCveRecords($portNumber, $cveRecords);

                $this->updated++;
                $this->processed++;
                $progressBar->advance();

                // Rate limiting: Only sleep after actual API calls, not cached data
                if (!$isCached && count($cveRecords) > 0) {
                    sleep($this->requestDelay);
                }
            } catch (\Exception $e) {
                $this->errors++;
                $progressBar->advance();
                $this->newLine();
                $this->error("Error processing port {$portNumber}: {$e->getMessage()}");
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
            ['port_number' => $port->port_number],
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
     * Fetch CVE data for a specific port number from NVD API (with caching).
     *
     * @return array<int, array{cve_id: string, description: string, published_date: string, cvss_score: float|null, severity: string|null, weakness_types: array, references: array}>
     */
    private function fetchCveDataForPort(int $portNumber, string $cacheKey): array
    {
        // Return cached data if available
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // Query NVD API for port-specific CVEs
        $cveRecords = $this->queryNvdApiByPort($portNumber);

        // Cache result (24 hours)
        Cache::put($cacheKey, $cveRecords, 86400);

        return $cveRecords;
    }

    /**
     * Query NVD API for CVEs mentioning a specific port number.
     *
     * @return array<int, array{cve_id: string, description: string, published_date: string, cvss_score: float|null, severity: string|null, weakness_types: array, references: array}>
     */
    private function queryNvdApiByPort(int $portNumber): array
    {
        $url = $this->nvdEndpoint;
        $cveRecords = [];

        // Try multiple search patterns for better coverage
        $searchPatterns = [
            "TCP port {$portNumber}",
            "port {$portNumber}/tcp",
            "UDP port {$portNumber}",
            "port {$portNumber}/udp",
        ];

        foreach ($searchPatterns as $pattern) {
            try {
                $request = Http::timeout(30)->retry(3, 1000);

                // Add API key if available
                if (!empty($this->nvdApiKey)) {
                    $request->withHeaders(['apiKey' => $this->nvdApiKey]);
                }

                $params = [
                    'keywordSearch' => $pattern,
                    'resultsPerPage' => 100, // Limit per pattern to avoid too much data
                ];

                $response = $request->get($url, $params);

                if ($response->successful()) {
                    $data = $response->json();
                    $vulnerabilities = $data['vulnerabilities'] ?? [];

                    foreach ($vulnerabilities as $vuln) {
                        $cve = $vuln['cve'];
                        $cveId = $cve['id'];

                        // Skip if we already have this CVE
                        if (isset($cveRecords[$cveId])) {
                            continue;
                        }

                        // Skip rejected/disputed CVEs
                        $descriptions = $cve['descriptions'] ?? [];
                        $isRejectedOrDisputed = false;
                        foreach ($descriptions as $desc) {
                            $descUpper = strtoupper($desc['value'] ?? '');
                            if (str_contains($descUpper, 'REJECT') || str_contains($descUpper, 'DISPUTED')) {
                                $isRejectedOrDisputed = true;
                                break;
                            }
                        }

                        if ($isRejectedOrDisputed) {
                            continue;
                        }

                        // Extract CVSS score and severity
                        $cvssScore = null;
                        $severity = null;
                        if (isset($cve['metrics']['cvssMetricV31'][0])) {
                            $cvssScore = $cve['metrics']['cvssMetricV31'][0]['cvssData']['baseScore'] ?? null;
                            $severity = $cve['metrics']['cvssMetricV31'][0]['cvssData']['baseSeverity'] ?? null;
                        } elseif (isset($cve['metrics']['cvssMetricV2'][0])) {
                            $cvssScore = $cve['metrics']['cvssMetricV2'][0]['cvssData']['baseScore'] ?? null;
                            $severity = $cve['metrics']['cvssMetricV2'][0]['baseSeverity'] ?? null;
                        }

                        // Extract weakness types (CWE)
                        $weaknessTypes = [];
                        foreach ($cve['weaknesses'] ?? [] as $weakness) {
                            foreach ($weakness['description'] ?? [] as $desc) {
                                if (isset($desc['value']) && str_starts_with($desc['value'], 'CWE-')) {
                                    $weaknessTypes[] = $desc['value'];
                                }
                            }
                        }

                        // Extract references
                        $references = [];
                        foreach ($cve['references'] ?? [] as $ref) {
                            if (isset($ref['url'])) {
                                $references[] = $ref['url'];
                            }
                        }

                        $cveRecords[$cveId] = [
                            'cve_id' => $cveId,
                            'description' => $cve['descriptions'][0]['value'] ?? '',
                            'published_date' => $cve['published'] ?? null,
                            'last_modified_date' => $cve['lastModified'] ?? null,
                            'cvss_score' => $cvssScore,
                            'severity' => $severity,
                            'weakness_types' => $weaknessTypes,
                            'references' => array_slice($references, 0, 10), // Limit to 10 refs
                        ];
                    }
                }

                // Rate limiting between search patterns
                if (count($searchPatterns) > 1) {
                    sleep(1);
                }
            } catch (\Exception $e) {
                // Continue with other patterns if one fails
                continue;
            }
        }

        // Sort by published date (most recent first)
        usort($cveRecords, function ($a, $b) {
            return strcmp($b['published_date'] ?? '', $a['published_date'] ?? '');
        });

        return $cveRecords;
    }

    /**
     * Store CVE records and link them to the port.
     */
    private function storeCveRecords(int $portNumber, array $cveRecords): void
    {
        if (empty($cveRecords)) {
            return;
        }

        DB::transaction(function () use ($portNumber, $cveRecords) {
            $criticalCount = 0;
            $highCount = 0;
            $mediumCount = 0;
            $lowCount = 0;
            $scores = [];
            $latestCve = null;

            foreach ($cveRecords as $cveData) {
                // Create or update CVE record
                $cve = Cve::updateOrCreate(
                    ['cve_id' => $cveData['cve_id']],
                    [
                        'description' => $cveData['description'],
                        'published_date' => $cveData['published_date'],
                        'last_modified_date' => $cveData['last_modified_date'] ?? null,
                        'cvss_score' => $cveData['cvss_score'],
                        'severity' => $cveData['severity'],
                        'weakness_types' => $cveData['weakness_types'],
                        'references' => $cveData['references'],
                        'source' => 'NVD',
                    ]
                );

                // Link CVE to port (using port_number, not port ID)
                DB::table('cve_port')->updateOrInsert(
                    [
                        'cve_id' => $cve->cve_id,
                        'port_number' => $portNumber,
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                // Track counts for summary
                if (!$latestCve) {
                    $latestCve = $cveData['cve_id'];
                }

                if ($cveData['cvss_score']) {
                    $scores[] = $cveData['cvss_score'];
                }

                switch (strtoupper($cveData['severity'] ?? '')) {
                    case 'CRITICAL':
                        $criticalCount++;
                        break;
                    case 'HIGH':
                        $highCount++;
                        break;
                    case 'MEDIUM':
                        $mediumCount++;
                        break;
                    case 'LOW':
                        $lowCount++;
                        break;
                }
            }

            // Update port_security summary
            PortSecurity::updateOrCreate(
                ['port_number' => $portNumber],
                [
                    'cve_count' => count($cveRecords),
                    'cve_critical_count' => $criticalCount,
                    'cve_high_count' => $highCount,
                    'cve_medium_count' => $mediumCount,
                    'cve_low_count' => $lowCount,
                    'cve_avg_score' => !empty($scores) ? round(array_sum($scores) / count($scores), 1) : null,
                    'latest_cve' => $latestCve,
                    'cve_updated_at' => now(),
                ]
            );
        });
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
