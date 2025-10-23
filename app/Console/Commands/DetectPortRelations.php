<?php

namespace App\Console\Commands;

use App\Models\Port;
use App\Models\PortRelation;
use Illuminate\Console\Command;

class DetectPortRelations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ports:detect-relations {--force : Force recreate all relations}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically detect and create port relationships based on patterns';

    private int $created = 0;
    private int $skipped = 0;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Detecting port relationships...');

        if ($this->option('force')) {
            $this->warn('Force mode: Deleting existing relations...');
            PortRelation::truncate();
        }

        // Detect different types of relations
        $this->detectSecureVersions();
        $this->detectAlternativePorts();
        $this->detectDeprecatedPorts();
        $this->detectPartOfSuite();
        $this->detectComplementaryProtocols();
        $this->detectAssociatedPorts();

        $this->newLine();
        $this->info('Port relation detection completed!');
        $this->table(
            ['Status', 'Count'],
            [
                ['Created', $this->created],
                ['Skipped (exists)', $this->skipped],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * Detect secure versions of ports (HTTP → HTTPS, etc.).
     */
    private function detectSecureVersions(): void
    {
        $this->info('Detecting secure versions...');

        $secureVersions = [
            80   => [[443, 'HTTPS is the secure (encrypted) version of HTTP']],
            21   => [
                [990, 'FTPS (FTP over SSL/TLS) is the secure version of FTP'],
                [22,  'SFTP (SSH File Transfer Protocol) is a secure alternative to FTP'],
            ],
            110  => [[995, 'POP3S is the secure (encrypted) version of POP3']],
            143  => [[993, 'IMAPS is the secure (encrypted) version of IMAP']],
            25   => [
                [465, 'SMTPS (SMTP over SSL) is a secure version of SMTP'],
                [587, 'SMTP with STARTTLS (submission) is a secure alternative to plain SMTP'],
            ],
            23   => [[22, 'SSH is the secure replacement for Telnet']],
            3306 => [[33060, 'MySQL X Protocol provides secure communication']],
        ];

        foreach ($secureVersions as $insecurePort => $relations) {
            foreach ($relations as [$securePort, $description]) {
                $this->createRelation($insecurePort, $securePort, PortRelation::TYPE_SECURE_VERSION, $description);
            }
        }
    }

    /**
     * Detect alternative ports (8080 for 80, etc.).
     */
    private function detectAlternativePorts(): void
    {
        $this->info('Detecting alternative ports...');

        $alternatives = [
            80 => [
                [8080, 'Common HTTP alternate port'],
                [8008, 'HTTP alternate port'],
                [8000, 'Common development HTTP port'],
                [8888, 'HTTP alternate port'],
                [3000, 'Common Node.js/development HTTP port'],
            ],
            443 => [
                [8443, 'Common HTTPS alternate port'],
                [9443, 'HTTPS alternate port'],
            ],
            22 => [
                [2222, 'Common SSH alternate port'],
            ],
            3306 => [
                [3307, 'MySQL alternate port'],
            ],
            5432 => [
                [5433, 'PostgreSQL alternate port'],
            ],
            27017 => [
                [27018, 'MongoDB alternate/replica port'],
                [27019, 'MongoDB alternate/replica port'],
            ],
            6379 => [
                [6380, 'Redis alternate/replica port'],
            ],
        ];

        foreach ($alternatives as $mainPort => $altPorts) {
            foreach ($altPorts as $data) {
                [$altPort, $description] = $data;
                $this->createRelation($mainPort, $altPort, PortRelation::TYPE_ALTERNATIVE, $description);
            }
        }
    }

    /**
     * Detect deprecated ports.
     */
    private function detectDeprecatedPorts(): void
    {
        $this->info('Detecting deprecated ports...');

        $deprecated = [
            23 => [22, 'Telnet is deprecated in favor of secure SSH'],
            21 => [22, 'FTP is deprecated in favor of SFTP'],
            80 => [443, 'Unencrypted HTTP is being deprecated in favor of HTTPS'],
        ];

        foreach ($deprecated as $oldPort => $data) {
            [$newPort, $description] = $data;
            $this->createRelation($oldPort, $newPort, PortRelation::TYPE_DEPRECATED_BY, $description);
        }
    }

    /**
     * Detect ports that are part of same software suite.
     */
    private function detectPartOfSuite(): void
    {
        $this->info('Detecting suite memberships...');

        $suites = [
            // MongoDB cluster ports
            'MongoDB Cluster' => [27017, 27018, 27019],
            // Redis cluster
            'Redis Cluster' => [6379, 6380, 16379],
            // Elasticsearch
            'Elasticsearch' => [9200, 9300],
            // MySQL Group Replication
            'MySQL Cluster' => [3306, 33060, 33061],
            // PostgreSQL
            'PostgreSQL' => [5432, 5433],
            // Cassandra
            'Cassandra' => [7000, 7001, 9042, 9160],
            // RabbitMQ
            'RabbitMQ' => [5672, 15672, 25672],
            // HTTP/HTTPS
            'Web Services' => [80, 443],
            // DNS
            'DNS' => [53], // Will have TCP/UDP complementary
            // Email Suite (SMTP, POP3, IMAP)
            'Email Services' => [25, 110, 143, 465, 587, 993, 995],
        ];

        foreach ($suites as $suiteName => $ports) {
            for ($i = 0; $i < count($ports); $i++) {
                for ($j = $i + 1; $j < count($ports); $j++) {
                    $this->createRelation(
                        $ports[$i],
                        $ports[$j],
                        PortRelation::TYPE_PART_OF_SUITE,
                        "Both are part of {$suiteName}"
                    );
                }
            }
        }
    }

    /**
     * Detect complementary protocols (same port, different protocol).
     */
    private function detectComplementaryProtocols(): void
    {
        $this->info('Detecting complementary protocols (TCP/UDP)...');

        // Ports that commonly use both TCP and UDP
        $bothProtocols = [53, 123, 161, 162, 500, 514, 1900, 5060, 5353];

        foreach ($bothProtocols as $portNumber) {
            $tcpPort = Port::where('port_number', $portNumber)
                ->where('protocol', 'TCP')
                ->first();

            $udpPort = Port::where('port_number', $portNumber)
                ->where('protocol', 'UDP')
                ->first();

            if ($tcpPort && $udpPort) {
                $this->createRelation(
                    $tcpPort->id,
                    $udpPort->id,
                    PortRelation::TYPE_COMPLEMENTARY,
                    "Port {$portNumber} uses both TCP and UDP protocols",
                    true // Use port IDs directly
                );
            }
        }
    }

    /**
     * Detect associated ports (commonly used together).
     */
    private function detectAssociatedPorts(): void
    {
        $this->info('Detecting commonly associated ports...');

        $associations = [
            // Web stack
            80 => [
                [3306, 'HTTP often used with MySQL database'],
                [5432, 'HTTP often used with PostgreSQL database'],
                [6379, 'HTTP often used with Redis cache'],
                [27017, 'HTTP often used with MongoDB'],
                [9200, 'HTTP often used with Elasticsearch'],
            ],
            443 => [
                [3306, 'HTTPS often used with MySQL database'],
                [5432, 'HTTPS often used with PostgreSQL database'],
                [6379, 'HTTPS often used with Redis cache'],
                [27017, 'HTTPS often used with MongoDB'],
                [9200, 'HTTPS often used with Elasticsearch'],
            ],
            // Database replication
            3306 => [
                [33060, 'MySQL and MySQL X Protocol often used together'],
            ],
            // Email services
            25 => [
                [110, 'SMTP and POP3 commonly used together for email'],
                [143, 'SMTP and IMAP commonly used together for email'],
            ],
            // Monitoring/Management
            22 => [
                [80, 'SSH and HTTP commonly used for server management'],
                [443, 'SSH and HTTPS commonly used for server management'],
            ],
        ];

        foreach ($associations as $port1 => $relatedPorts) {
            foreach ($relatedPorts as $data) {
                [$port2, $description] = $data;
                $this->createRelation($port1, $port2, PortRelation::TYPE_ASSOCIATED_WITH, $description);
            }
        }
    }

    /**
     * Create a port relation if it doesn't exist.
     */
    private function createRelation(
        int|string $portIdentifier1,
        int|string $portIdentifier2,
        string $relationType,
        ?string $description = null,
        bool $useDirectIds = false
    ): void {
        // Get port IDs
        if ($useDirectIds) {
            $portId1 = $portIdentifier1;
            $portId2 = $portIdentifier2;
        } else {
            $port1 = Port::where('port_number', $portIdentifier1)->first();
            $port2 = Port::where('port_number', $portIdentifier2)->first();

            if (! $port1 || ! $port2) {
                return;
            }

            $portId1 = $port1->id;
            $portId2 = $port2->id;
        }

        // Check if relation already exists
        $exists = PortRelation::where('port_id', $portId1)
            ->where('related_port_id', $portId2)
            ->where('relation_type', $relationType)
            ->exists();

        if ($exists) {
            $this->skipped++;
            return;
        }

        // Create relation
        PortRelation::create([
            'port_id' => $portId1,
            'related_port_id' => $portId2,
            'relation_type' => $relationType,
            'description' => $description,
        ]);

        $this->created++;
    }
}
