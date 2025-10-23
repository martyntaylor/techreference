<?php

namespace App\Console\Commands;

use App\Models\Port;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportIanaPorts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ports:import-iana {file? : Path to IANA CSV file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import or update ports from IANA service-names-port-numbers CSV';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $filePath = $this->argument('file') ?? storage_path('app/iana-ports.csv');

        if (! file_exists($filePath)) {
            $this->error("CSV file not found at: {$filePath}");
            $this->info('You can download the latest IANA registry from:');
            $this->info('https://www.iana.org/assignments/service-names-port-numbers/service-names-port-numbers.csv');

            return self::FAILURE;
        }

        $this->info('Importing IANA ports from: ' . $filePath);

        // Open CSV file
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            $this->error('Failed to open CSV file');

            return self::FAILURE;
        }

        // Read header row
        $header = fgetcsv($handle);
        if ($header === false) {
            $this->error('Failed to read CSV header');
            fclose($handle);

            return self::FAILURE;
        }

        // Map header columns to indices
        $columnMap = $this->mapColumns($header);

        // Validate required headers exist
        $requiredHeaders = [
            'service_name' => 'Service Name',
            'port_number' => 'Port Number',
            'protocol' => 'Transport Protocol',
            'description' => 'Description',
        ];

        $missingHeaders = [];
        foreach ($requiredHeaders as $key => $headerName) {
            if (! array_key_exists($key, $columnMap) || $columnMap[$key] === false) {
                $missingHeaders[] = $headerName;
            }
        }

        if (! empty($missingHeaders)) {
            $this->error('Required CSV columns are missing: '.implode(', ', $missingHeaders));
            $this->info('Expected columns: '.implode(', ', array_values($requiredHeaders)));
            fclose($handle);

            return self::FAILURE;
        }

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = 0;

        $this->info('Processing ports...');
        $progressBar = $this->output->createProgressBar();
        $progressBar->start();

        // Process each row
        while (($row = fgetcsv($handle)) !== false) {
            $progressBar->advance();

            try {
                $data = $this->parseRow($row, $columnMap);

                if (! $this->isValidPort($data)) {
                    $skipped++;

                    continue;
                }

                // Upsert port (insert or update if exists)
                $portData = $this->preparePortData($data);

                $port = Port::updateOrCreate(
                    [
                        'port_number' => $portData['port_number'],
                        'protocol' => $portData['protocol'],
                    ],
                    $portData
                );

                if ($port->wasRecentlyCreated) {
                    $imported++;
                } else {
                    $updated++;
                }
            } catch (\Exception $e) {
                $errors++;
                $this->newLine();
                $this->error("Error processing row: {$e->getMessage()}");
            }
        }

        $progressBar->finish();
        fclose($handle);

        $this->newLine(2);
        $this->info('Import completed!');
        $this->table(
            ['Status', 'Count'],
            [
                ['Imported', $imported],
                ['Updated', $updated],
                ['Skipped', $skipped],
                ['Errors', $errors],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * Map CSV column names to array indices.
     */
    private function mapColumns(array $header): array
    {
        return [
            'service_name' => array_search('Service Name', $header),
            'port_number' => array_search('Port Number', $header),
            'protocol' => array_search('Transport Protocol', $header),
            'description' => array_search('Description', $header),
            'assignee' => array_search('Assignee', $header),
            'contact' => array_search('Contact', $header),
            'registration_date' => array_search('Registration Date', $header),
            'modification_date' => array_search('Modification Date', $header),
            'reference' => array_search('Reference', $header),
            'service_code' => array_search('Service Code', $header),
            'unauthorized_use' => array_search('Unauthorized Use Reported', $header),
            'assignment_notes' => array_search('Assignment Notes', $header),
        ];
    }

    /**
     * Parse a CSV row into structured data.
     */
    private function parseRow(array $row, array $columnMap): array
    {
        return [
            'service_name' => $this->getColumnValue($row, $columnMap['service_name']),
            'port_number' => $this->getColumnValue($row, $columnMap['port_number']),
            'protocol' => $this->getColumnValue($row, $columnMap['protocol']),
            'description' => $this->getColumnValue($row, $columnMap['description']),
            'assignee' => $this->getColumnValue($row, $columnMap['assignee']),
            'contact' => $this->getColumnValue($row, $columnMap['contact']),
            'registration_date' => $this->getColumnValue($row, $columnMap['registration_date']),
            'modification_date' => $this->getColumnValue($row, $columnMap['modification_date']),
            'reference' => $this->getColumnValue($row, $columnMap['reference']),
            'service_code' => $this->getColumnValue($row, $columnMap['service_code']),
            'unauthorized_use' => $this->getColumnValue($row, $columnMap['unauthorized_use']),
            'assignment_notes' => $this->getColumnValue($row, $columnMap['assignment_notes']),
        ];
    }

    /**
     * Get column value safely.
     */
    private function getColumnValue(array $row, int|false $index): ?string
    {
        if ($index === false || ! isset($row[$index])) {
            return null;
        }

        $value = trim($row[$index]);

        return $value === '' ? null : $value;
    }

    /**
     * Check if port data is valid for import.
     */
    private function isValidPort(array $data): bool
    {
        // Must have port number and protocol
        if (! isset($data['port_number']) || $data['port_number'] === '') {
            return false;
        }

        if (empty($data['protocol'])) {
            return false;
        }

        // Port number must be numeric and in valid range
        if (! is_numeric($data['port_number'])) {
            return false;
        }

        $portNumber = (int) $data['port_number'];
        if ($portNumber < 0 || $portNumber > 65535) {
            return false;
        }

        // Protocol must be valid
        $protocol = strtolower($data['protocol']);
        if (! in_array($protocol, ['tcp', 'udp', 'sctp', 'dccp'])) {
            return false;
        }

        return true;
    }

    /**
     * Prepare port data for database insertion/update.
     */
    private function preparePortData(array $data): array
    {
        $portData = [
            'port_number' => (int) $data['port_number'],
            'protocol' => strtoupper($data['protocol']),
            'service_name' => $data['service_name'],
            'description' => $data['description'],
            'iana_status' => $this->determineIanaStatus($data),
            'iana_official' => $this->isOfficialPort($data),
            'encrypted_default' => $this->isEncryptedByDefault($data),
            'risk_level' => $this->determineRiskLevel($data),
        ];

        // Set iana_updated_at if modification date is available
        if (! empty($data['modification_date'])) {
            try {
                $portData['iana_updated_at'] = \Carbon\Carbon::parse($data['modification_date']);
            } catch (\Exception $e) {
                // Invalid date format, skip
            }
        }

        // Build historical context from metadata
        $historicalContext = $this->buildHistoricalContext($data);
        if ($historicalContext) {
            $portData['historical_context'] = $historicalContext;
        }

        // Build security notes from unauthorized use
        if (! empty($data['unauthorized_use'])) {
            $portData['security_notes'] = "Unauthorized use reported: {$data['unauthorized_use']}";
        }

        return $portData;
    }

    /**
     * Determine IANA status from port data.
     */
    private function determineIanaStatus(array $data): string
    {
        $description = strtolower($data['description'] ?? '');
        $serviceName = strtolower($data['service_name'] ?? '');

        // Check description for status indicators
        if (str_contains($description, 'reserved')) {
            return 'Reserved';
        }

        if (str_contains($description, 'unassigned')) {
            return 'Unassigned';
        }

        if (str_contains($serviceName, 'reserved') || empty($serviceName)) {
            if (str_contains($description, 'de-assigned')) {
                return 'De-assigned';
            }

            return empty($serviceName) && empty($description) ? 'Unassigned' : 'Reserved';
        }

        return 'Official';
    }

    /**
     * Check if port is officially assigned by IANA.
     */
    private function isOfficialPort(array $data): bool
    {
        $status = $this->determineIanaStatus($data);

        return $status === 'Official';
    }

    /**
     * Check if service uses encryption by default.
     */
    private function isEncryptedByDefault(array $data): bool
    {
        $serviceName = strtolower($data['service_name'] ?? '');
        $description = strtolower($data['description'] ?? '');

        $encryptionKeywords = ['https', 'ftps', 'ssl', 'tls', 'ssh', 'secure', 'encrypted'];

        foreach ($encryptionKeywords as $keyword) {
            if (str_contains($serviceName, $keyword) || str_contains($description, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build historical context from IANA metadata.
     */
    private function buildHistoricalContext(array $data): ?string
    {
        $parts = [];

        // Add assignee info
        if (! empty($data['assignee'])) {
            $parts[] = "Assignee: {$data['assignee']}";
        }

        // Add contact info
        if (! empty($data['contact']) && $data['contact'] !== $data['assignee']) {
            $parts[] = "Contact: {$data['contact']}";
        }

        // Add registration date
        if (! empty($data['registration_date'])) {
            $parts[] = "Registered: {$data['registration_date']}";
        }

        // Add reference (RFC, etc.)
        if (! empty($data['reference'])) {
            $parts[] = "Reference: {$data['reference']}";
        }

        // Add service code
        if (! empty($data['service_code'])) {
            $parts[] = "Service Code: {$data['service_code']}";
        }

        // Add assignment notes (important context)
        if (! empty($data['assignment_notes'])) {
            $parts[] = "Notes: {$data['assignment_notes']}";
        }

        return empty($parts) ? null : implode("\n", $parts);
    }

    /**
     * Determine risk level based on port characteristics.
     * This provides an initial assessment that can be refined with CVE data later.
     */
    private function determineRiskLevel(array $data): string
    {
        $portNumber = (int) $data['port_number'];
        $serviceName = strtolower($data['service_name'] ?? '');
        $description = strtolower($data['description'] ?? '');
        $isEncrypted = $this->isEncryptedByDefault($data);

        // High risk: Known insecure protocols that transmit credentials in plaintext
        $highRiskServices = [
            'ftp', 'telnet', 'rlogin', 'rsh', 'rexec', 'tftp', 'snmp',
            'finger', 'pop2', 'imap2', 'nntp', 'http', 'ldap', 'smb',
        ];

        foreach ($highRiskServices as $service) {
            if (str_contains($serviceName, $service) && ! $isEncrypted) {
                return 'High';
            }
        }

        // High risk: Ports commonly used in attacks or amplification
        $highRiskPorts = [
            7,      // echo (DDoS amplification)
            9,      // discard (DDoS amplification)
            11,     // systat (information disclosure)
            13,     // daytime (DDoS amplification)
            17,     // qotd (DDoS amplification)
            19,     // chargen (DDoS amplification)
            69,     // TFTP (unauthenticated file transfer)
            111,    // RPC (numerous vulnerabilities)
            135,    // MSRPC (Windows vulnerabilities)
            137,    // NetBIOS (SMB vulnerabilities)
            138,    // NetBIOS
            139,    // NetBIOS over TCP
            161,    // SNMP (default community strings)
            445,    // SMB (ransomware, EternalBlue)
            512,    // rexec (no encryption)
            513,    // rlogin (no encryption)
            514,    // rsh (no encryption)
            1433,   // MS SQL (frequently attacked)
            1900,   // UPnP (security issues)
            3389,   // RDP (brute force target)
            5900,   // VNC (weak authentication)
            6379,   // Redis (often misconfigured)
        ];

        if (in_array($portNumber, $highRiskPorts)) {
            return 'High';
        }

        // Medium risk: Encrypted services but still externally accessible
        if ($isEncrypted) {
            return 'Medium';
        }

        // Medium risk: Database ports (should not be publicly exposed)
        $databasePorts = [3306, 5432, 1521, 27017, 5984, 9200, 9300];
        if (in_array($portNumber, $databasePorts)) {
            return 'Medium';
        }

        // Medium risk: Administrative/management ports
        $managementKeywords = ['admin', 'manage', 'control', 'monitor', 'debug', 'console'];
        foreach ($managementKeywords as $keyword) {
            if (str_contains($serviceName, $keyword) || str_contains($description, $keyword)) {
                return 'Medium';
            }
        }

        // Low risk: Reserved/Unassigned ports
        $status = $this->determineIanaStatus($data);
        if (in_array($status, ['Reserved', 'Unassigned', 'De-assigned'])) {
            return 'Low';
        }

        // Low risk: Well-known encrypted services on standard ports
        $lowRiskEncryptedPorts = [22, 443, 993, 995, 465, 636, 990];
        if (in_array($portNumber, $lowRiskEncryptedPorts)) {
            return 'Low';
        }

        // Default to Low for everything else
        return 'Low';
    }
}
