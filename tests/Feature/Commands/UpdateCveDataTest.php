<?php

use App\Models\Port;
use App\Models\PortSecurity;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

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
    $security = PortSecurity::where('port_id', $port->id)->first();
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

    Http::fake([
        'services.nvd.nist.gov/*' => Http::response([
            'totalResults' => 2,
            'vulnerabilities' => [
                [
                    'cve' => [
                        'id' => 'CVE-2024-12345',
                        'published' => '2024-01-16T22:15:00.000',
                        'descriptions' => [
                            ['lang' => 'en', 'value' => 'Valid vulnerability...'],
                        ],
                        'metrics' => [
                            'cvssMetricV31' => [
                                ['cvssData' => ['baseScore' => 7.5]],
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
                                ['cvssData' => ['baseScore' => 9.8]],
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $this->artisan('ports:update-cve')->assertExitCode(0);

    $security = PortSecurity::where('port_id', $port->id)->first();
    expect($security->cve_count)->toBe(1); // Only 1, rejected CVE filtered out
    expect($security->latest_cve)->toBe('CVE-2024-12345');
});

test('command caches CVE data by service name', function () {
    // Create two ports with same service
    $port1 = Port::factory()->create([
        'port_number' => 3306,
        'protocol' => 'TCP',
        'service_name' => 'mysql',
    ]);

    $port2 = Port::factory()->create([
        'port_number' => 33060,
        'protocol' => 'TCP',
        'service_name' => 'mysql',
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
                            ['lang' => 'en', 'value' => 'MySQL vulnerability...'],
                        ],
                        'metrics' => [
                            'cvssMetricV31' => [
                                ['cvssData' => ['baseScore' => 4.9]],
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $this->artisan('ports:update-cve')->assertExitCode(0);

    // Both ports should have same CVE data
    $security1 = PortSecurity::where('port_id', $port1->id)->first();
    $security2 = PortSecurity::where('port_id', $port2->id)->first();

    expect($security1->cve_count)->toBe(1);
    expect($security2->cve_count)->toBe(1);
    expect($security1->latest_cve)->toBe($security2->latest_cve);

    // Only 1 HTTP request should have been made (due to caching)
    Http::assertSentCount(1);
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
                                ['cvssData' => ['baseScore' => 5.0]],
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
    expect(PortSecurity::where('port_id', $port1->id)->exists())->toBeTrue();
    expect(PortSecurity::where('port_id', $port2->id)->exists())->toBeFalse();
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
                            ['lang' => 'en', 'value' => 'Vulnerability...'],
                        ],
                        'metrics' => [
                            'cvssMetricV31' => [
                                ['cvssData' => ['baseScore' => 5.0]],
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $this->artisan('ports:update-cve', ['--service' => 'mysql'])
        ->assertExitCode(0);

    // Only MySQL service request should be made
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'keywordSearch=mysql');
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
    expect(PortSecurity::where('port_id', $port->id)->exists())->toBeFalse();
});

test('command skips recently updated ports unless --force is used', function () {
    $port = Port::factory()->create([
        'port_number' => 3306,
        'protocol' => 'TCP',
        'service_name' => 'mysql',
    ]);

    // Create recent port_security record
    PortSecurity::create([
        'port_id' => $port->id,
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
                        'descriptions' => [['lang' => 'en', 'value' => 'New vulnerability...']],
                        'metrics' => [
                            'cvssMetricV31' => [['cvssData' => ['baseScore' => 7.5]]],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $this->artisan('ports:update-cve', ['--force' => true])
        ->assertExitCode(0);

    Http::assertSentCount(1);
});
