<?php

use App\Models\Port;
use App\Models\PortSecurity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Clear cache before each test
    Cache::flush();
});

test('command updates CVE data for ports', function () {
    // Create test port with service name
    $port = Port::factory()->create([
        'port_number' => 3306,
        'protocol' => 'TCP',
        'service_name' => 'mysql',
    ]);

    // Mock NVD API response
    Http::fake([
        'services.nvd.nist.gov/*' => Http::response([
            'totalResults' => 2,
            'vulnerabilities' => [
                [
                    'cve' => [
                        'id' => 'CVE-2024-20994',
                        'published' => '2024-01-16T22:15:00.000',
                        'descriptions' => [
                            ['lang' => 'en', 'value' => 'Vulnerability in Oracle MySQL Server...'],
                        ],
                        'metrics' => [
                            'cvssMetricV31' => [
                                ['cvssData' => ['baseScore' => 4.9, 'baseSeverity' => 'MEDIUM']],
                            ],
                        ],
                    ],
                ],
                [
                    'cve' => [
                        'id' => 'CVE-2023-21980',
                        'published' => '2023-04-18T20:15:00.000',
                        'descriptions' => [
                            ['lang' => 'en', 'value' => 'Another MySQL vulnerability...'],
                        ],
                        'metrics' => [
                            'cvssMetricV31' => [
                                ['cvssData' => ['baseScore' => 6.5, 'baseSeverity' => 'MEDIUM']],
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    // Run command
    $this->artisan('ports:update-cve')
        ->assertExitCode(0);

    // Verify port_security was updated
    $security = PortSecurity::where('port_number', $port->port_number)->first();
    expect($security)->not->toBeNull();
    expect($security->cve_count)->toBe(2);
    expect($security->latest_cve)->toBe('CVE-2024-20994');
    expect($security->cve_updated_at)->not->toBeNull();
});

test('command skips ports without service names', function () {
    // Create port without service name
    Port::factory()->create([
        'port_number' => 12345,
        'protocol' => 'TCP',
        'service_name' => null,
    ]);

    Http::fake();

    $this->artisan('ports:update-cve')
        ->expectsOutput('No ports to process.')
        ->assertExitCode(0);

    // Verify no HTTP requests were made
    Http::assertNothingSent();
});

test('command filters CVEs with rejected status', function () {
    $port = Port::factory()->create([
        'port_number' => 80,
        'protocol' => 'TCP',
        'service_name' => 'http',
    ]);

    // Mock 4 API calls (one per search pattern: "TCP port 80", "port 80/tcp", "UDP port 80", "port 80/udp")
    Http::fake([
        'services.nvd.nist.gov/*' => Http::response([
            'totalResults' => 2,
            'vulnerabilities' => [
                [
                    'cve' => [
                        'id' => 'CVE-2024-12345',
                        'published' => '2024-01-16T22:15:00.000',
                        'descriptions' => [
                            ['lang' => 'en', 'value' => 'Valid vulnerability affecting TCP port 80...'],
                        ],
                        'metrics' => [
                            'cvssMetricV31' => [
                                ['cvssData' => ['baseScore' => 7.5, 'baseSeverity' => 'HIGH']],
                            ],
                        ],
                    ],
                ],
                [
                    'cve' => [
                        'id' => 'CVE-2024-99999',
                        'published' => '2024-02-01T10:00:00.000',
                        'descriptions' => [
                            ['lang' => 'en', 'value' => '** REJECT ** This CVE has been rejected...'],
                        ],
                        'metrics' => [
                            'cvssMetricV31' => [
                                ['cvssData' => ['baseScore' => 9.8, 'baseSeverity' => 'CRITICAL']],
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $this->artisan('ports:update-cve')->assertExitCode(0);

    $security = PortSecurity::where('port_number', $port->port_number)->first();
    // CVE-2024-12345 appears in all 4 search pattern results, but stored only once
    // CVE-2024-99999 is filtered out (rejected)
    expect($security->cve_count)->toBe(1);
    expect($security->latest_cve)->toBe('CVE-2024-12345');
});

test('command filters CVEs with disputed status', function () {
    $port = Port::factory()->create([
        'port_number' => 8080,
        'protocol' => 'TCP',
        'service_name' => 'http',
    ]);

    // Mock 4 API calls (one per search pattern)
    Http::fake([
        'services.nvd.nist.gov/*' => Http::response([
            'totalResults' => 2,
            'vulnerabilities' => [
                [
                    'cve' => [
                        'id' => 'CVE-2024-11111',
                        'published' => '2024-03-01T10:00:00.000',
                        'descriptions' => [
                            ['lang' => 'en', 'value' => 'Valid vulnerability affecting port 8080'],
                        ],
                        'metrics' => [
                            'cvssMetricV31' => [
                                ['cvssData' => ['baseScore' => 7.0, 'baseSeverity' => 'HIGH']],
                            ],
                        ],
                    ],
                ],
                [
                    'cve' => [
                        'id' => 'CVE-2024-22222',
                        'published' => '2024-02-01T10:00:00.000',
                        'descriptions' => [
                            ['lang' => 'en', 'value' => '** DISPUTED ** This CVE is disputed by vendor...'],
                        ],
                        'metrics' => [
                            'cvssMetricV31' => [
                                ['cvssData' => ['baseScore' => 9.8, 'baseSeverity' => 'CRITICAL']],
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $this->artisan('ports:update-cve')->assertExitCode(0);

    $security = PortSecurity::where('port_number', $port->port_number)->first();
    // CVE-2024-11111 stored once (appears in multiple search patterns)
    // CVE-2024-22222 filtered out (disputed)
    expect($security->cve_count)->toBe(1);
    expect($security->latest_cve)->toBe('CVE-2024-11111');
});

test('command caches CVE data by port number', function () {
    // Create two different ports
    $port1 = Port::factory()->create([
        'port_number' => 3306,
        'protocol' => 'TCP',
        'service_name' => 'mysql',
    ]);

    $port2 = Port::factory()->create([
        'port_number' => 33060,
        'protocol' => 'TCP',
        'service_name' => 'mysqlx',
    ]);

    Http::fake([
        'services.nvd.nist.gov/*' => Http::response([
            'totalResults' => 1,
            'vulnerabilities' => [
                [
                    'cve' => [
                        'id' => 'CVE-2024-20994',
                        'published' => '2024-01-16T22:15:00.000',
                        'descriptions' => [
                            ['lang' => 'en', 'value' => 'MySQL vulnerability affecting port 3306...'],
                        ],
                        'metrics' => [
                            'cvssMetricV31' => [
                                ['cvssData' => ['baseScore' => 4.9, 'baseSeverity' => 'MEDIUM']],
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $this->artisan('ports:update-cve')->assertExitCode(0);

    // Each port gets its own CVE data
    $security1 = PortSecurity::where('port_number', $port1->port_number)->first();
    $security2 = PortSecurity::where('port_number', $port2->port_number)->first();

    expect($security1)->not->toBeNull();
    expect($security2)->not->toBeNull();

    // 4 search patterns per port = 8 total requests
    Http::assertSentCount(8);

    // Run again with --force to bypass "recently updated" filter
    // Cache should prevent additional HTTP requests (cached by port number)
    $this->artisan('ports:update-cve', ['--force' => true])->assertExitCode(0);

    // Still only 8 requests total (cached by port number)
    Http::assertSentCount(8);
});

test('command can update specific port with --port option', function () {
    $port1 = Port::factory()->create([
        'port_number' => 3306,
        'protocol' => 'TCP',
        'service_name' => 'mysql',
    ]);

    $port2 = Port::factory()->create([
        'port_number' => 5432,
        'protocol' => 'TCP',
        'service_name' => 'postgresql',
    ]);

    Http::fake([
        'services.nvd.nist.gov/*' => Http::response([
            'totalResults' => 1,
            'vulnerabilities' => [
                [
                    'cve' => [
                        'id' => 'CVE-2024-12345',
                        'published' => '2024-01-16T22:15:00.000',
                        'descriptions' => [
                            ['lang' => 'en', 'value' => 'Vulnerability...'],
                        ],
                        'metrics' => [
                            'cvssMetricV31' => [
                                ['cvssData' => ['baseScore' => 5.0, 'baseSeverity' => 'MEDIUM']],
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $this->artisan('ports:update-cve', ['--port' => 3306])
        ->assertExitCode(0);

    // Only port 3306 should be updated
    expect(PortSecurity::where('port_number', $port1->port_number)->exists())->toBeTrue();
    expect(PortSecurity::where('port_number', $port2->port_number)->exists())->toBeFalse();
});

test('command can filter by service name with --service option', function () {
    Port::factory()->create([
        'port_number' => 3306,
        'protocol' => 'TCP',
        'service_name' => 'mysql',
    ]);

    Port::factory()->create([
        'port_number' => 5432,
        'protocol' => 'TCP',
        'service_name' => 'postgresql',
    ]);

    Http::fake([
        'services.nvd.nist.gov/*' => Http::response([
            'totalResults' => 1,
            'vulnerabilities' => [
                [
                    'cve' => [
                        'id' => 'CVE-2024-12345',
                        'published' => '2024-01-16T22:15:00.000',
                        'descriptions' => [
                            ['lang' => 'en', 'value' => 'Vulnerability affecting TCP port 3306...'],
                        ],
                        'metrics' => [
                            'cvssMetricV31' => [
                                ['cvssData' => ['baseScore' => 5.0, 'baseSeverity' => 'MEDIUM']],
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $this->artisan('ports:update-cve', ['--service' => 'mysql'])
        ->assertExitCode(0);

    // Only MySQL port should be processed (4 search patterns for port 3306)
    Http::assertSentCount(4);

    // Verify requests contain port number patterns
    Http::assertSent(function ($request) {
        $query = $request->data()['keywordSearch'] ?? '';
        return str_contains($query, 'port 3306') || str_contains($query, '3306');
    });
});

test('command handles API errors gracefully', function () {
    $port = Port::factory()->create([
        'port_number' => 3306,
        'protocol' => 'TCP',
        'service_name' => 'mysql',
    ]);

    Http::fake([
        'services.nvd.nist.gov/*' => Http::response(null, 500),
    ]);

    $this->artisan('ports:update-cve')
        ->assertExitCode(0);

    // Port security should not be created due to error
    expect(PortSecurity::where('port_number', $port->port_number)->exists())->toBeFalse();
});

test('command skips recently updated ports unless --force is used', function () {
    $port = Port::factory()->create([
        'port_number' => 3306,
        'protocol' => 'TCP',
        'service_name' => 'mysql',
    ]);

    // Create recent port_security record
    PortSecurity::create([
        'port_number' => $port->port_number,
        'cve_count' => 5,
        'latest_cve' => 'CVE-2024-12345',
        'cve_updated_at' => now()->subHours(1), // Updated 1 hour ago
    ]);

    Http::fake();

    // Without --force, should skip
    $this->artisan('ports:update-cve')
        ->expectsOutput('No ports to process.')
        ->assertExitCode(0);

    Http::assertNothingSent();

    // With --force, should process
    Http::fake([
        'services.nvd.nist.gov/*' => Http::response([
            'totalResults' => 1,
            'vulnerabilities' => [
                [
                    'cve' => [
                        'id' => 'CVE-2024-99999',
                        'published' => '2024-02-01T10:00:00.000',
                        'descriptions' => [['lang' => 'en', 'value' => 'New vulnerability affecting TCP port 3306...']],
                        'metrics' => [
                            'cvssMetricV31' => [['cvssData' => ['baseScore' => 7.5, 'baseSeverity' => 'HIGH']]],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $this->artisan('ports:update-cve', ['--force' => true])
        ->assertExitCode(0);

    // 4 search patterns for port 3306
    Http::assertSentCount(4);
});
