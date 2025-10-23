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

        // Get unique port numbers using type-safe pluck()
        // pluck() returns Collection<int> instead of Collection<Model> for better type safety
        $portNumbers = $this->getPortsQuery()
            ->distinct('port_number')
            ->orderBy('port_number')
            ->pluck('port_number');

        if ($portNumbers->isEmpty()) {
            $this->info('No ports to process.');

            return self::SUCCESS;
        }

        $this->info("Processing {$portNumbers->count()} unique port numbers...");
        $progressBar = $this->output->createProgressBar($portNumbers->count());
        $progressBar->start();

        // Process in chunks of 100 for memory efficiency
        $portNumbers->chunk(100)->each(function ($portNumberChunk) use ($progressBar) {
            foreach ($portNumberChunk as $portNumber) {
                try {
                    // Fetch CVE data for this port number (with caching)
                    $cacheKey = 'cve:port:'.$portNumber;
                    $cveRecords = $this->fetchCveDataForPort($portNumber, $cacheKey);

                    // Store CVEs and link to port
                    $this->storeCveRecords($portNumber, $cveRecords);

                    $this->updated++;
                    $this->processed++;
                    $progressBar->advance();

                    // Note: Rate limiting is handled per-request in queryNvdApiByPort()
                    // No additional port-level delay needed since we sleep between API requests
                } catch (\Exception $e) {
                    $this->errors++;
                    $progressBar->advance();
                    $this->newLine();
                    $this->error("Error processing port {$portNumber}: {$e->getMessage()}");
                }
            }
        });

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
     * Get base query for ports to process (returns query, not collection for memory efficiency).
     *
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\Port>
     */
    private function getPortsQuery()
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

        return $query;
    }

    /**
     * Fetch CVE data for a specific port number from NVD API (with caching).
     *
     * @return array<int, array{cve_id: string, description: string, published_date: string, last_modified_date: string|null, cvss_score: float|null, severity: string|null, weakness_types: array<int, string>, references: array<int, string>}>
     */
    private function fetchCveDataForPort(int $portNumber, string $cacheKey): array
    {
        // Return cached data if available (including empty arrays)
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey, []);
        }

        // Query NVD API for port-specific CVEs
        $cveRecords = $this->queryNvdApiByPort($portNumber);

        // Cache result: 24 hours if we have data, 1 hour for empty (to avoid repeated API calls)
        $cacheDuration = ! empty($cveRecords) ? 86400 : 3600;
        Cache::put($cacheKey, $cveRecords, $cacheDuration);

        return $cveRecords;
    }

    /**
     * Query NVD API for CVEs mentioning a specific port number.
     *
     * @return array<int, array{cve_id: string, description: string, published_date: string, last_modified_date: string|null, cvss_score: float|null, severity: string|null, weakness_types: array<int, string>, references: array<int, string>}>
     */
    private function queryNvdApiByPort(int $portNumber): array
    {
        $url = $this->nvdEndpoint;
        $cveRecords = []; // Initialize as empty array to ensure we always return an array

        // Try multiple search patterns for better coverage
        $searchPatterns = [
            "TCP port {$portNumber}",
            "port {$portNumber}/tcp",
            "UDP port {$portNumber}",
            "port {$portNumber}/udp",
            "port {$portNumber}",  // Generic pattern
            "listening on port {$portNumber}",
            "default port {$portNumber}",
        ];

        $totalPatterns = count($searchPatterns);
        foreach ($searchPatterns as $i => $pattern) {
            try {
                $startIndex = 0;
                $resultsPerPage = 100;
                $patternCompleted = false;

                // Paginate through all results for this pattern
                while (! $patternCompleted) {
                    $request = Http::timeout(30)->retry(3, 1000);

                    // Add API key if available
                    if (! empty($this->nvdApiKey)) {
                        $request->withHeaders(['apiKey' => $this->nvdApiKey]);
                    }

                    $params = [
                        'keywordSearch' => $pattern,
                        'resultsPerPage' => $resultsPerPage,
                        'startIndex' => $startIndex,
                    ];

                    $response = $request->get($url, $params);

                    if ($response->successful()) {
                        $data = $response->json();
                        $totalResults = $data['totalResults'] ?? 0;
                        $vulnerabilities = $data['vulnerabilities'] ?? [];

                        // Log pagination info on first page
                        if ($startIndex === 0 && $totalResults > $resultsPerPage) {
                            $this->warn("Port {$portNumber} pattern '{$pattern}' has {$totalResults} results, paginating...");
                        }

                        foreach ($vulnerabilities as $vuln) {
                            $cve = $vuln['cve'] ?? null;
                            if (! $cve) {
                                continue;
                            }

                            $cveId = $cve['id'] ?? null;
                            if (! $cveId) {
                                continue;
                            }

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
                            } elseif (isset($cve['metrics']['cvssMetricV30'][0])) {
                                $cvssScore = $cve['metrics']['cvssMetricV30'][0]['cvssData']['baseScore'] ?? null;
                                $severity = $cve['metrics']['cvssMetricV30'][0]['cvssData']['baseSeverity'] ?? null;
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
                                'description' => isset($cve['descriptions'][0]['value']) ? $cve['descriptions'][0]['value'] : '',
                                'published_date' => $cve['published'] ?? null,
                                'last_modified_date' => $cve['lastModified'] ?? null,
                                'cvss_score' => $cvssScore,
                                'severity' => $severity,
                                'weakness_types' => $weaknessTypes,
                                'references' => array_slice($references, 0, 10), // Limit to 10 refs
                            ];
                        }

                        // Check if we need to fetch more results
                        $startIndex += $resultsPerPage;
                        if ($startIndex >= $totalResults || empty($vulnerabilities)) {
                            $patternCompleted = true;
                        } else {
                            // Rate limiting between pagination requests
                            sleep($this->requestDelay);
                        }
                    } else {
                        // API request failed, stop pagination for this pattern
                        $patternCompleted = true;
                    }
                }

                // Rate limiting between search patterns (respect configured delay, skip after last pattern)
                if ($i < $totalPatterns - 1) {
                    sleep($this->requestDelay);
                }
            } catch (\Exception $e) {
                // Log error but continue with other patterns
                $this->warn("Error querying pattern '{$pattern}' for port {$portNumber}: {$e->getMessage()}");

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
     *
     * @param  array<int, array{cve_id: string, description: string, published_date: string, last_modified_date: string|null, cvss_score: float|null, severity: string|null, weakness_types: array<int, string>, references: array<int, string>}>  $cveRecords
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
                if (! $latestCve) {
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
                    'cve_avg_score' => ! empty($scores) ? round(array_sum($scores) / count($scores), 1) : null,
                    'latest_cve' => $latestCve,
                    'cve_updated_at' => now(),
                ]
            );

            // Invalidate port page caches
            Cache::forget("port:{$portNumber}:vulnerabilities:v1");

            // Invalidate all category caches that contain CVE data (using cache tags)
            Cache::tags(['category'])->flush();

            // Invalidate home page caches (CVE updates affect security statistics)
            Cache::forget('ports:home:categories');
            Cache::forget('ports:home:top-ports');
            Cache::forget('ports:home:popular');
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
