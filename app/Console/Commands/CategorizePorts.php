<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Port;
use Illuminate\Console\Command;

class CategorizePorts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ports:categorize
                            {--port= : Categorize a specific port number}
                            {--fresh : Remove existing categorizations and start fresh}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically categorize ports based on software, port numbers, and service names';

    private array $stats = [
        'by_software' => 0,
        'by_port_number' => 0,
        'by_service_name' => 0,
        'skipped' => 0,
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting port categorization...');

        if ($this->option('fresh')) {
            $this->warn('Removing existing port categorizations...');
            \DB::table('port_categories')->delete();
            $this->info('Cleared existing categorizations.');
        }

        // Get ports to categorize
        $query = Port::query();
        if ($portNumber = $this->option('port')) {
            $query->where('port_number', $portNumber);
        }

        // Get count for progress bar
        $totalCount = $query->count();
        $this->info("Processing {$totalCount} ports...");

        $progressBar = $this->output->createProgressBar($totalCount);

        // Use lazy() for memory-efficient iteration over large datasets
        // lazy() fetches records in chunks (default 1000) without loading all into memory
        foreach ($query->lazy() as $port) {
            $this->categorizePort($port);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Display statistics
        $this->info('Categorization complete!');
        $this->table(
            ['Method', 'Count'],
            [
                ['By Software', $this->stats['by_software']],
                ['By Port Number', $this->stats['by_port_number']],
                ['By Service Name', $this->stats['by_service_name']],
                ['Skipped (already categorized)', $this->stats['skipped']],
            ]
        );

        return Command::SUCCESS;
    }

    /**
     * Categorize a single port using multiple strategies.
     */
    private function categorizePort(Port $port): void
    {
        // Check if already categorized (unless --fresh)
        if (! $this->option('fresh') && $port->categories()->exists()) {
            $this->stats['skipped']++;

            return;
        }

        $categorized = false;

        // Strategy 1: Categorize by linked software
        if ($this->categorizeBySoftware($port)) {
            $this->stats['by_software']++;
            $categorized = true;
        }

        // Strategy 2: Categorize by well-known port numbers
        if ($this->categorizeByPortNumber($port)) {
            $this->stats['by_port_number']++;
            $categorized = true;
        }

        // Strategy 3: Categorize by service name keywords
        if ($this->categorizeByServiceName($port)) {
            $this->stats['by_service_name']++;
            $categorized = true;
        }

        if (! $categorized) {
            $this->stats['skipped']++;
        }
    }

    /**
     * Categorize port based on linked software categories.
     */
    private function categorizeBySoftware(Port $port): bool
    {
        $categorized = false;

        // Get all software linked to this port
        $software = $port->software()->whereNotNull('category_id')->get();

        foreach ($software as $sw) {
            // Create port-category link if it doesn't exist
            if (! $port->categories()->where('categories.id', $sw->category_id)->exists()) {
                $port->categories()->attach($sw->category_id, [
                    'is_primary' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $categorized = true;
        }

        return $categorized;
    }

    /**
     * Categorize port based on well-known port number patterns.
     */
    private function categorizeByPortNumber(Port $port): bool
    {
        $portNumber = $port->port_number;
        $categorySlug = null;

        // Web Services (HTTP/HTTPS and related)
        if (in_array($portNumber, [80, 443, 8080, 8443, 8000, 8888, 3000, 5000, 9000])) {
            $categorySlug = 'web-services';
        }

        // Database
        elseif (in_array($portNumber, [3306, 5432, 1433, 1521, 27017, 6379, 5984, 9042, 7000, 7001])) {
            $categorySlug = 'database';
        }

        // Email
        elseif (in_array($portNumber, [25, 587, 465, 110, 995, 143, 993])) {
            $categorySlug = 'email';
        }

        // File Transfer
        elseif (in_array($portNumber, [20, 21, 22, 69, 989, 990, 115])) {
            $categorySlug = 'file-transfer';
        }

        // Remote Access
        elseif (in_array($portNumber, [22, 23, 3389, 5900, 5901, 5902, 5903])) {
            $categorySlug = 'remote-access';
        }

        // Gaming
        elseif (in_array($portNumber, [25565, 27015, 27016, 7777, 19132, 19133])) {
            $categorySlug = 'gaming';
        }

        // VoIP
        elseif (in_array($portNumber, [5060, 5061, 5038, 4569])) {
            $categorySlug = 'voip';
        }

        // Messaging
        elseif (in_array($portNumber, [5222, 5269, 6667, 6697, 194])) {
            $categorySlug = 'messaging';
        }

        // DNS
        elseif (in_array($portNumber, [53, 853, 5353])) {
            $categorySlug = 'dns';
        }

        // Network Infrastructure
        elseif (in_array($portNumber, [161, 162, 514, 67, 68, 123, 520, 179])) {
            $categorySlug = 'network-infrastructure';
        }

        // Monitoring
        elseif (in_array($portNumber, [9090, 9091, 9093, 3000, 8086, 8888, 4317, 4318])) {
            $categorySlug = 'monitoring';
        }

        // Media Streaming
        elseif (in_array($portNumber, [554, 1935, 8554])) {
            $categorySlug = 'media-streaming';
        }

        // VPN
        elseif (in_array($portNumber, [1194, 1723, 500, 4500])) {
            $categorySlug = 'vpn';
        }

        // Blockchain
        elseif (in_array($portNumber, [8545, 8546, 30303, 9650, 26656, 26657])) {
            $categorySlug = 'blockchain';
        }

        // IoT
        elseif (in_array($portNumber, [1883, 8883, 5683, 5684])) {
            $categorySlug = 'iot';
        }

        if ($categorySlug) {
            $category = Category::where('slug', $categorySlug)->first();
            if ($category && ! $port->categories()->where('categories.id', $category->id)->exists()) {
                $port->categories()->attach($category->id, [
                    'is_primary' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return true;
            }
        }

        return false;
    }

    /**
     * Categorize port based on service name keywords.
     */
    private function categorizeByServiceName(Port $port): bool
    {
        $serviceName = strtolower($port->service_name ?? '');
        $description = strtolower($port->description ?? '');
        $text = $serviceName.' '.$description;

        $categorySlug = null;

        // Web Services keywords
        if (preg_match('/\b(http|https|web|www|apache|nginx|iis|tomcat|jetty)\b/', $text)) {
            $categorySlug = 'web-services';
        }

        // Database keywords
        elseif (preg_match('/\b(mysql|postgres|mssql|oracle|mongodb|redis|cassandra|couchdb|database|db)\b/', $text)) {
            $categorySlug = 'database';
        }

        // Email keywords
        elseif (preg_match('/\b(smtp|pop3|imap|mail|email)\b/', $text)) {
            $categorySlug = 'email';
        }

        // File Transfer keywords
        elseif (preg_match('/\b(ftp|sftp|tftp|scp|file transfer)\b/', $text)) {
            $categorySlug = 'file-transfer';
        }

        // Remote Access keywords
        elseif (preg_match('/\b(ssh|telnet|rdp|vnc|remote desktop|remote access)\b/', $text)) {
            $categorySlug = 'remote-access';
        }

        // Gaming keywords
        elseif (preg_match('/\b(minecraft|game|gaming|steam|valve)\b/', $text)) {
            $categorySlug = 'gaming';
        }

        // VoIP keywords
        elseif (preg_match('/\b(sip|voip|asterisk|voice|telephony)\b/', $text)) {
            $categorySlug = 'voip';
        }

        // Messaging keywords
        elseif (preg_match('/\b(xmpp|irc|jabber|chat|messaging|slack|discord)\b/', $text)) {
            $categorySlug = 'messaging';
        }

        // DNS keywords
        elseif (preg_match('/\b(dns|domain name|bind)\b/', $text)) {
            $categorySlug = 'dns';
        }

        // Network Infrastructure keywords
        elseif (preg_match('/\b(snmp|syslog|dhcp|ntp|bgp|router|switch|network)\b/', $text)) {
            $categorySlug = 'network-infrastructure';
        }

        // Monitoring keywords
        elseif (preg_match('/\b(prometheus|grafana|monitoring|metrics|observability|telegraf)\b/', $text)) {
            $categorySlug = 'monitoring';
        }

        // Media Streaming keywords
        elseif (preg_match('/\b(rtsp|rtmp|streaming|media|video|audio)\b/', $text)) {
            $categorySlug = 'media-streaming';
        }

        // VPN keywords
        elseif (preg_match('/\b(vpn|openvpn|ipsec|l2tp|pptp|wireguard)\b/', $text)) {
            $categorySlug = 'vpn';
        }

        // Blockchain keywords
        elseif (preg_match('/\b(ethereum|bitcoin|blockchain|crypto|web3|geth)\b/', $text)) {
            $categorySlug = 'blockchain';
        }

        // IoT keywords
        elseif (preg_match('/\b(mqtt|coap|iot|sensor|device|smart home)\b/', $text)) {
            $categorySlug = 'iot';
        }

        // Security keywords
        elseif (preg_match('/\b(ipsec|ssl|tls|certificate|security|firewall)\b/', $text)) {
            $categorySlug = 'security';
        }

        // DevOps keywords
        elseif (preg_match('/\b(docker|kubernetes|k8s|jenkins|ci\/cd|ansible|terraform)\b/', $text)) {
            $categorySlug = 'devops';
        }

        if ($categorySlug) {
            $category = Category::where('slug', $categorySlug)->first();
            if ($category && ! $port->categories()->where('categories.id', $category->id)->exists()) {
                $port->categories()->attach($category->id, [
                    'is_primary' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return true;
            }
        }

        return false;
    }
}
