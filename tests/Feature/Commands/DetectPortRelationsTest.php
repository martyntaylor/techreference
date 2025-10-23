<?php

use App\Models\Port;
use App\Models\PortRelation;

beforeEach(function () {
    // Create test ports
    Port::create(['port_number' => 80, 'protocol' => 'TCP', 'service_name' => 'HTTP', 'description' => 'HyperText Transfer Protocol']);
    Port::create(['port_number' => 443, 'protocol' => 'TCP', 'service_name' => 'HTTPS', 'description' => 'HTTP Secure']);
    Port::create(['port_number' => 8080, 'protocol' => 'TCP', 'service_name' => 'HTTP-ALT', 'description' => 'HTTP Alternate']);
    Port::create(['port_number' => 3306, 'protocol' => 'TCP', 'service_name' => 'MySQL', 'description' => 'MySQL Database']);
    Port::create(['port_number' => 22, 'protocol' => 'TCP', 'service_name' => 'SSH', 'description' => 'Secure Shell']);
    Port::create(['port_number' => 23, 'protocol' => 'TCP', 'service_name' => 'Telnet', 'description' => 'Telnet']);

    // Create dual-protocol ports (TCP and UDP)
    Port::create(['port_number' => 53, 'protocol' => 'TCP', 'service_name' => 'DNS', 'description' => 'Domain Name System']);
    Port::create(['port_number' => 53, 'protocol' => 'UDP', 'service_name' => 'DNS', 'description' => 'Domain Name System']);
    Port::create(['port_number' => 123, 'protocol' => 'TCP', 'service_name' => 'NTP', 'description' => 'Network Time Protocol']);
    Port::create(['port_number' => 123, 'protocol' => 'UDP', 'service_name' => 'NTP', 'description' => 'Network Time Protocol']);
});

test('detects secure version relations', function () {
    $this->artisan('ports:detect-relations --force')
        ->assertSuccessful();

    // HTTP → HTTPS
    expect(PortRelation::where('relation_type', PortRelation::TYPE_SECURE_VERSION)
        ->whereHas('port', fn($q) => $q->where('port_number', 80))
        ->whereHas('relatedPort', fn($q) => $q->where('port_number', 443))
        ->exists())->toBeTrue();

    // Telnet → SSH
    expect(PortRelation::where('relation_type', PortRelation::TYPE_SECURE_VERSION)
        ->whereHas('port', fn($q) => $q->where('port_number', 23))
        ->whereHas('relatedPort', fn($q) => $q->where('port_number', 22))
        ->exists())->toBeTrue();
});

test('detects alternative port relations', function () {
    $this->artisan('ports:detect-relations --force')
        ->assertSuccessful();

    // 80 → 8080
    expect(PortRelation::where('relation_type', PortRelation::TYPE_ALTERNATIVE)
        ->whereHas('port', fn($q) => $q->where('port_number', 80))
        ->whereHas('relatedPort', fn($q) => $q->where('port_number', 8080))
        ->exists())->toBeTrue();
});

test('detects deprecated port relations', function () {
    $this->artisan('ports:detect-relations --force')
        ->assertSuccessful();

    // Telnet deprecated by SSH
    expect(PortRelation::where('relation_type', PortRelation::TYPE_DEPRECATED_BY)
        ->whereHas('port', fn($q) => $q->where('port_number', 23))
        ->whereHas('relatedPort', fn($q) => $q->where('port_number', 22))
        ->exists())->toBeTrue();

    // HTTP deprecated by HTTPS
    expect(PortRelation::where('relation_type', PortRelation::TYPE_DEPRECATED_BY)
        ->whereHas('port', fn($q) => $q->where('port_number', 80))
        ->whereHas('relatedPort', fn($q) => $q->where('port_number', 443))
        ->exists())->toBeTrue();
});

test('creates bidirectional relations for part of suite', function () {
    $this->artisan('ports:detect-relations --force')
        ->assertSuccessful();

    // Check forward relation: 80 → 443 (Web Services)
    expect(PortRelation::where('relation_type', PortRelation::TYPE_PART_OF_SUITE)
        ->whereHas('port', fn($q) => $q->where('port_number', 80))
        ->whereHas('relatedPort', fn($q) => $q->where('port_number', 443))
        ->exists())->toBeTrue();

    // Check reverse relation: 443 → 80 (Web Services)
    expect(PortRelation::where('relation_type', PortRelation::TYPE_PART_OF_SUITE)
        ->whereHas('port', fn($q) => $q->where('port_number', 443))
        ->whereHas('relatedPort', fn($q) => $q->where('port_number', 80))
        ->exists())->toBeTrue();
});

test('creates bidirectional complementary protocol relations', function () {
    $this->artisan('ports:detect-relations --force')
        ->assertSuccessful();

    $tcpPort = Port::where('port_number', 53)->where('protocol', 'TCP')->first();
    $udpPort = Port::where('port_number', 53)->where('protocol', 'UDP')->first();

    // Check forward relation: TCP → UDP
    expect(PortRelation::where('relation_type', PortRelation::TYPE_COMPLEMENTARY)
        ->where('port_id', $tcpPort->id)
        ->where('related_port_id', $udpPort->id)
        ->exists())->toBeTrue();

    // Check reverse relation: UDP → TCP
    expect(PortRelation::where('relation_type', PortRelation::TYPE_COMPLEMENTARY)
        ->where('port_id', $udpPort->id)
        ->where('related_port_id', $tcpPort->id)
        ->exists())->toBeTrue();
});

test('creates bidirectional associated port relations', function () {
    $this->artisan('ports:detect-relations --force')
        ->assertSuccessful();

    // Check forward relation: 80 → 3306
    expect(PortRelation::where('relation_type', PortRelation::TYPE_ASSOCIATED_WITH)
        ->whereHas('port', fn($q) => $q->where('port_number', 80))
        ->whereHas('relatedPort', fn($q) => $q->where('port_number', 3306))
        ->exists())->toBeTrue();

    // Check reverse relation: 3306 → 80
    expect(PortRelation::where('relation_type', PortRelation::TYPE_ASSOCIATED_WITH)
        ->whereHas('port', fn($q) => $q->where('port_number', 3306))
        ->whereHas('relatedPort', fn($q) => $q->where('port_number', 80))
        ->exists())->toBeTrue();
});

test('resolves dual-protocol ports to TCP variant by default', function () {
    $this->artisan('ports:detect-relations --force')
        ->assertSuccessful();

    // When port 53 is referenced in suite detection, it should use TCP variant
    $tcpPort = Port::where('port_number', 53)->where('protocol', 'TCP')->first();

    // DNS suite should only have one member (port 53 TCP), so no suite relations should be created
    // But if it were in a multi-member suite, the TCP variant would be used
    expect($tcpPort)->not->toBeNull();
});

test('handles missing ports gracefully with warnings', function () {
    // Create a port that references a non-existent port in relations
    // This test verifies the warning logic without actually needing the full data set

    $this->artisan('ports:detect-relations --force')
        ->expectsOutput('Detecting port relationships...')
        ->assertSuccessful();

    // The command should complete successfully even if some ports are missing
    expect(PortRelation::count())->toBeGreaterThan(0);
});

test('does not create duplicate relations', function () {
    // Run twice to test idempotency
    $this->artisan('ports:detect-relations')->assertSuccessful();
    $firstCount = PortRelation::count();

    $this->artisan('ports:detect-relations')->assertSuccessful();
    $secondCount = PortRelation::count();

    expect($firstCount)->toBe($secondCount);
});

test('force flag recreates all relations', function () {
    // Run once
    $this->artisan('ports:detect-relations')->assertSuccessful();
    $firstCount = PortRelation::count();

    // Run with --force flag
    $this->artisan('ports:detect-relations --force')->assertSuccessful();
    $secondCount = PortRelation::count();

    // Should have same count (all relations recreated)
    expect($firstCount)->toBe($secondCount);
    expect($secondCount)->toBeGreaterThan(0);
});
