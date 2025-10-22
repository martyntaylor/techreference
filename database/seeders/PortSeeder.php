<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Port;
use App\Models\PortConfig;
use App\Models\PortIssue;
use App\Models\PortSecurity;
use App\Models\Software;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PortSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get categories
        $webServices = Category::where('slug', 'web-services')->first();
        $database = Category::where('slug', 'database')->first();
        $remoteAccess = Category::where('slug', 'remote-access')->first();
        $email = Category::where('slug', 'email')->first();
        $fileTransfer = Category::where('slug', 'file-transfer')->first();
        $dns = Category::where('slug', 'dns')->first();

        // Create software
        $apache = Software::create([
            'name' => 'Apache HTTP Server',
            'slug' => 'apache',
            'website_url' => 'https://httpd.apache.org',
            'description' => 'Open-source HTTP server',
            'category' => 'Web Server',
        ]);

        $nginx = Software::create([
            'name' => 'Nginx',
            'slug' => 'nginx',
            'website_url' => 'https://nginx.org',
            'description' => 'High-performance web server and reverse proxy',
            'category' => 'Web Server',
        ]);

        $mysql = Software::create([
            'name' => 'MySQL',
            'slug' => 'mysql',
            'website_url' => 'https://www.mysql.com',
            'description' => 'Open-source relational database',
            'category' => 'Database',
        ]);

        $postgresql = Software::create([
            'name' => 'PostgreSQL',
            'slug' => 'postgresql',
            'website_url' => 'https://www.postgresql.org',
            'description' => 'Advanced open-source relational database',
            'category' => 'Database',
        ]);

        $openssh = Software::create([
            'name' => 'OpenSSH',
            'slug' => 'openssh',
            'website_url' => 'https://www.openssh.com',
            'description' => 'Secure shell implementation',
            'category' => 'Remote Access',
        ]);

        $bind = Software::create([
            'name' => 'BIND',
            'slug' => 'bind',
            'website_url' => 'https://www.isc.org/bind/',
            'description' => 'Berkeley Internet Name Domain - Popular DNS server software',
            'category' => 'DNS',
        ]);

        $dnsmasq = Software::create([
            'name' => 'dnsmasq',
            'slug' => 'dnsmasq',
            'website_url' => 'https://thekelleys.org.uk/dnsmasq/doc.html',
            'description' => 'Lightweight DNS forwarder and DHCP server',
            'category' => 'DNS',
        ]);

        // Port 80 - HTTP
        $port80 = Port::create([
            'port_number' => 80,
            'protocol' => 'TCP',
            'service_name' => 'HTTP',
            'description' => 'Hypertext Transfer Protocol - The foundation of data communication for the World Wide Web. Port 80 is the default port for unencrypted web traffic.',
            'iana_status' => 'Official',
            'risk_level' => 'Medium',
            'encrypted_default' => false,
        ]);

        $port80->categories()->attach([$webServices->id]);
        $port80->software()->attach([$apache->id, $nginx->id]);

        PortSecurity::create([
            'port_id' => $port80->id,
            'shodan_exposed_count' => 45678900,
            'censys_exposed_count' => 43200000,
            'cve_count' => 127,
            'latest_cve' => 'CVE-2024-1234',
            'security_recommendations' => 'Use HTTPS (port 443) instead. If HTTP must be used, implement additional security layers.',
            'shodan_updated_at' => now(),
            'censys_updated_at' => now(),
        ]);

        PortConfig::create([
            'port_id' => $port80->id,
            'platform' => 'Apache',
            'config_type' => 'Virtual Host',
            'title' => 'Basic Apache Virtual Host',
            'code_snippet' => '<VirtualHost *:80>
    ServerName example.com
    DocumentRoot /var/www/html

    <Directory /var/www/html>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>',
            'language' => 'apache',
            'explanation' => 'Basic Apache configuration for serving HTTP traffic on port 80.',
            'verified' => true,
        ]);

        PortConfig::create([
            'port_id' => $port80->id,
            'platform' => 'Nginx',
            'config_type' => 'Server Block',
            'title' => 'Basic Nginx Server Block',
            'code_snippet' => 'server {
    listen 80;
    server_name example.com;
    root /var/www/html;

    location / {
        try_files $uri $uri/ =404;
    }
}',
            'language' => 'nginx',
            'explanation' => 'Basic Nginx configuration for serving HTTP traffic on port 80.',
            'verified' => true,
        ]);

        PortConfig::create([
            'port_id' => $port80->id,
            'platform' => 'Docker',
            'config_type' => 'Container',
            'title' => 'Docker Port Mapping',
            'code_snippet' => 'docker run -d \
  --name webserver \
  -p 80:80 \
  nginx:latest',
            'language' => 'bash',
            'explanation' => 'Run an Nginx container with port 80 exposed.',
            'verified' => true,
        ]);

        PortIssue::create([
            'port_id' => $port80->id,
            'issue_title' => 'Port 80 Already in Use',
            'symptoms' => 'Cannot start web server, error: "Address already in use"',
            'solution' => 'Check what process is using port 80 with: `sudo lsof -i :80` or `sudo netstat -tulpn | grep :80`. Stop the conflicting service or change your server to use a different port.',
            'verified' => true,
            'upvotes' => 42,
        ]);

        // Port 443 - HTTPS
        $port443 = Port::create([
            'port_number' => 443,
            'protocol' => 'TCP',
            'service_name' => 'HTTPS',
            'description' => 'HTTP Secure - HTTP over TLS/SSL. Port 443 is the standard port for secure, encrypted web traffic using TLS certificates.',
            'iana_status' => 'Official',
            'risk_level' => 'Low',
            'encrypted_default' => true,
        ]);

        $port443->categories()->attach([$webServices->id]);
        $port443->software()->attach([$apache->id, $nginx->id]);

        PortSecurity::create([
            'port_id' => $port443->id,
            'shodan_exposed_count' => 52341200,
            'censys_exposed_count' => 49800000,
            'cve_count' => 89,
            'latest_cve' => 'CVE-2024-5678',
            'security_recommendations' => 'Keep TLS certificates up to date. Use TLS 1.3 or higher. Disable older protocols like SSLv3 and TLS 1.0.',
            'shodan_updated_at' => now(),
            'censys_updated_at' => now(),
        ]);

        PortConfig::create([
            'port_id' => $port443->id,
            'platform' => 'Apache',
            'config_type' => 'SSL Virtual Host',
            'title' => 'Apache SSL Configuration',
            'code_snippet' => '<VirtualHost *:443>
    ServerName example.com
    DocumentRoot /var/www/html

    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/example.com.crt
    SSLCertificateKeyFile /etc/ssl/private/example.com.key
    SSLCertificateChainFile /etc/ssl/certs/ca-bundle.crt
</VirtualHost>',
            'language' => 'apache',
            'explanation' => 'Apache SSL configuration for HTTPS on port 443.',
            'verified' => true,
        ]);

        PortConfig::create([
            'port_id' => $port443->id,
            'platform' => 'Nginx',
            'config_type' => 'SSL Server Block',
            'title' => 'Nginx SSL Configuration',
            'code_snippet' => 'server {
    listen 443 ssl http2;
    server_name example.com;

    ssl_certificate /etc/ssl/certs/example.com.crt;
    ssl_certificate_key /etc/ssl/private/example.com.key;
    ssl_protocols TLSv1.2 TLSv1.3;

    root /var/www/html;
}',
            'language' => 'nginx',
            'explanation' => 'Nginx SSL configuration with HTTP/2 support.',
            'verified' => true,
        ]);

        // Port 22 - SSH
        $port22 = Port::create([
            'port_number' => 22,
            'protocol' => 'TCP',
            'service_name' => 'SSH',
            'description' => 'Secure Shell - Cryptographic network protocol for secure remote login and command execution. SSH provides encrypted communication between client and server.',
            'iana_status' => 'Official',
            'risk_level' => 'Medium',
            'encrypted_default' => true,
        ]);

        $port22->categories()->attach([$remoteAccess->id]);
        $port22->software()->attach([$openssh->id]);

        PortSecurity::create([
            'port_id' => $port22->id,
            'shodan_exposed_count' => 28456700,
            'censys_exposed_count' => 26100000,
            'cve_count' => 156,
            'latest_cve' => 'CVE-2024-9101',
            'security_recommendations' => 'Disable root login. Use key-based authentication. Change default port. Implement fail2ban.',
            'shodan_updated_at' => now(),
            'censys_updated_at' => now(),
        ]);

        PortConfig::create([
            'port_id' => $port22->id,
            'platform' => 'Linux',
            'config_type' => 'SSH Server',
            'title' => 'Secure SSH Configuration',
            'code_snippet' => '# /etc/ssh/sshd_config
Port 22
PermitRootLogin no
PasswordAuthentication no
PubkeyAuthentication yes
AllowUsers admin deploy',
            'language' => 'bash',
            'explanation' => 'Hardened SSH server configuration.',
            'verified' => true,
        ]);

        PortIssue::create([
            'port_id' => $port22->id,
            'issue_title' => 'SSH Connection Refused',
            'symptoms' => 'Cannot connect via SSH, getting "Connection refused" error',
            'solution' => 'Verify SSH service is running: `sudo systemctl status sshd`. Check firewall rules: `sudo ufw status`. Verify port 22 is listening: `sudo netstat -tulpn | grep :22`',
            'verified' => true,
            'upvotes' => 67,
        ]);

        // Port 3306 - MySQL
        $port3306 = Port::create([
            'port_number' => 3306,
            'protocol' => 'TCP',
            'service_name' => 'MySQL',
            'description' => 'MySQL Database Server - Default port for MySQL database connections. Used for client-server communication in MySQL and MariaDB.',
            'iana_status' => 'Official',
            'risk_level' => 'High',
            'encrypted_default' => false,
        ]);

        $port3306->categories()->attach([$database->id]);
        $port3306->software()->attach([$mysql->id]);

        PortSecurity::create([
            'port_id' => $port3306->id,
            'shodan_exposed_count' => 4567800,
            'censys_exposed_count' => 4200000,
            'cve_count' => 234,
            'latest_cve' => 'CVE-2024-3456',
            'security_recommendations' => 'Never expose to the internet. Bind to localhost only. Use strong passwords. Enable SSL/TLS connections.',
            'shodan_updated_at' => now(),
            'censys_updated_at' => now(),
        ]);

        PortConfig::create([
            'port_id' => $port3306->id,
            'platform' => 'MySQL',
            'config_type' => 'Server Configuration',
            'title' => 'Secure MySQL Binding',
            'code_snippet' => '# /etc/mysql/mysql.conf.d/mysqld.cnf
[mysqld]
bind-address = 127.0.0.1
port = 3306
require_secure_transport = ON',
            'language' => 'ini',
            'explanation' => 'Configure MySQL to only accept local connections and require SSL.',
            'verified' => true,
        ]);

        PortConfig::create([
            'port_id' => $port3306->id,
            'platform' => 'Docker',
            'config_type' => 'Container',
            'title' => 'MySQL Docker Container',
            'code_snippet' => 'docker run -d \
  --name mysql \
  -e MYSQL_ROOT_PASSWORD=secretpassword \
  -p 3306:3306 \
  mysql:8.0',
            'language' => 'bash',
            'explanation' => 'Run MySQL in a Docker container.',
            'verified' => true,
        ]);

        // Port 5432 - PostgreSQL
        $port5432 = Port::create([
            'port_number' => 5432,
            'protocol' => 'TCP',
            'service_name' => 'PostgreSQL',
            'description' => 'PostgreSQL Database Server - Default port for PostgreSQL database connections. Advanced open-source relational database system.',
            'iana_status' => 'Official',
            'risk_level' => 'High',
            'encrypted_default' => false,
        ]);

        $port5432->categories()->attach([$database->id]);
        $port5432->software()->attach([$postgresql->id]);

        PortSecurity::create([
            'port_id' => $port5432->id,
            'shodan_exposed_count' => 2345600,
            'censys_exposed_count' => 2100000,
            'cve_count' => 178,
            'latest_cve' => 'CVE-2024-7890',
            'security_recommendations' => 'Restrict access via pg_hba.conf. Use SSL connections. Never expose directly to internet.',
            'shodan_updated_at' => now(),
            'censys_updated_at' => now(),
        ]);

        PortConfig::create([
            'port_id' => $port5432->id,
            'platform' => 'PostgreSQL',
            'config_type' => 'Server Configuration',
            'title' => 'PostgreSQL Listen Configuration',
            'code_snippet' => "# postgresql.conf
listen_addresses = 'localhost'
port = 5432
ssl = on

# pg_hba.conf
hostssl all all 0.0.0.0/0 scram-sha-256",
            'language' => 'bash',
            'explanation' => 'Configure PostgreSQL to use SSL and restrict access.',
            'verified' => true,
        ]);

        // Port 53 - DNS (TCP)
        $port53Tcp = Port::create([
            'port_number' => 53,
            'protocol' => 'TCP',
            'service_name' => 'DNS',
            'description' => 'Domain Name System (TCP) - Used for DNS zone transfers (AXFR/IXFR) and responses larger than 512 bytes. TCP provides reliable delivery for large DNS messages.',
            'iana_official' => true,
            'iana_status' => 'Official',
            'risk_level' => 'Medium',
            'encrypted_default' => false,
            'common_uses' => 'Zone transfers between DNS servers, large DNS responses, DNS over TCP fallback',
            'historical_context' => 'Originally, DNS used UDP exclusively. TCP support was added for zone transfers and to handle responses exceeding UDP packet size limits.',
        ]);

        $port53Tcp->categories()->attach([$dns->id]);
        $port53Tcp->software()->attach([$bind->id, $dnsmasq->id]);

        PortSecurity::create([
            'port_id' => $port53Tcp->id,
            'shodan_exposed_count' => 5678900,
            'censys_exposed_count' => 5400000,
            'cve_count' => 45,
            'latest_cve' => 'CVE-2024-2222',
            'security_recommendations' => 'Restrict zone transfers to authorized secondary servers only. Use TSIG authentication. Block port 53 TCP from untrusted networks if zone transfers are not needed.',
            'shodan_updated_at' => now(),
            'censys_updated_at' => now(),
        ]);

        PortConfig::create([
            'port_id' => $port53Tcp->id,
            'platform' => 'BIND',
            'config_type' => 'Zone Transfer',
            'title' => 'Restrict Zone Transfers',
            'code_snippet' => '// named.conf
zone "example.com" {
    type master;
    file "/etc/bind/zones/example.com.zone";
    allow-transfer {
        192.168.1.2;  // Secondary DNS server
        192.168.1.3;  // Another secondary
    };
};',
            'language' => 'bash',
            'explanation' => 'Configure BIND to only allow zone transfers to specific secondary DNS servers.',
            'verified' => true,
        ]);

        PortConfig::create([
            'port_id' => $port53Tcp->id,
            'platform' => 'Firewall',
            'config_type' => 'iptables',
            'title' => 'Allow DNS TCP from Specific IPs',
            'code_snippet' => '# Allow DNS TCP from secondary servers
iptables -A INPUT -p tcp --dport 53 -s 192.168.1.2 -j ACCEPT
iptables -A INPUT -p tcp --dport 53 -s 192.168.1.3 -j ACCEPT
# Block all other DNS TCP
iptables -A INPUT -p tcp --dport 53 -j DROP',
            'language' => 'bash',
            'explanation' => 'Firewall rules to restrict DNS zone transfers.',
            'verified' => true,
        ]);

        // Port 53 - DNS (UDP)
        $port53Udp = Port::create([
            'port_number' => 53,
            'protocol' => 'UDP',
            'service_name' => 'DNS',
            'description' => 'Domain Name System (UDP) - The primary protocol for DNS queries and responses. UDP is used for standard DNS lookups due to its low overhead and fast response time.',
            'iana_official' => true,
            'iana_status' => 'Official',
            'risk_level' => 'Medium',
            'encrypted_default' => false,
            'common_uses' => 'Standard DNS queries, recursive lookups, DNS caching, client-server DNS resolution',
            'historical_context' => 'UDP has been the primary DNS protocol since its creation in 1983. Its connectionless nature makes it ideal for the request-response pattern of DNS queries.',
        ]);

        $port53Udp->categories()->attach([$dns->id]);
        $port53Udp->software()->attach([$bind->id, $dnsmasq->id]);

        PortSecurity::create([
            'port_id' => $port53Udp->id,
            'shodan_exposed_count' => 12456789,
            'censys_exposed_count' => 11900000,
            'cve_count' => 52,
            'latest_cve' => 'CVE-2024-1111',
            'security_recommendations' => 'Implement rate limiting to prevent DNS amplification attacks. Use DNS response rate limiting (RRL). Enable DNSSEC for query validation. Restrict recursive queries to trusted networks only.',
            'shodan_updated_at' => now(),
            'censys_updated_at' => now(),
        ]);

        PortConfig::create([
            'port_id' => $port53Udp->id,
            'platform' => 'BIND',
            'config_type' => 'Server Configuration',
            'title' => 'Secure DNS Server Configuration',
            'code_snippet' => '// named.conf
options {
    directory "/var/named";

    // Only allow recursion from local network
    recursion yes;
    allow-recursion { 192.168.1.0/24; localhost; };

    // Disable zone transfers by default
    allow-transfer { none; };

    // Enable response rate limiting
    rate-limit {
        responses-per-second 10;
        window 5;
    };
};',
            'language' => 'bash',
            'explanation' => 'Secure BIND configuration to prevent DNS amplification attacks.',
            'verified' => true,
        ]);

        PortConfig::create([
            'port_id' => $port53Udp->id,
            'platform' => 'dnsmasq',
            'config_type' => 'Server Configuration',
            'title' => 'dnsmasq DNS Configuration',
            'code_snippet' => '# /etc/dnsmasq.conf
# Listen on specific interface
interface=eth0

# Restrict to local network
listen-address=192.168.1.1

# Cache size
cache-size=1000

# Upstream DNS servers
server=8.8.8.8
server=1.1.1.1',
            'language' => 'bash',
            'explanation' => 'Basic dnsmasq configuration for a local DNS server.',
            'verified' => true,
        ]);

        PortConfig::create([
            'port_id' => $port53Udp->id,
            'platform' => 'Docker',
            'config_type' => 'Container',
            'title' => 'BIND DNS Server in Docker',
            'code_snippet' => 'docker run -d \
  --name bind9 \
  -p 53:53/udp \
  -p 53:53/tcp \
  -v /etc/bind:/etc/bind \
  ubuntu/bind9:latest',
            'language' => 'bash',
            'explanation' => 'Run BIND DNS server in Docker with both UDP and TCP ports exposed.',
            'verified' => true,
        ]);

        PortIssue::create([
            'port_id' => $port53Udp->id,
            'issue_title' => 'DNS Server Not Responding',
            'symptoms' => 'DNS queries timeout, nslookup or dig commands fail',
            'solution' => 'Check if DNS service is running: `sudo systemctl status named` or `sudo systemctl status dnsmasq`. Verify port 53 is listening: `sudo netstat -tulpn | grep :53`. Check firewall: `sudo ufw status` or `sudo iptables -L -n`. Test with: `dig @localhost example.com`',
            'platform' => 'Linux',
            'verified' => true,
            'upvotes' => 89,
        ]);

        PortIssue::create([
            'port_id' => $port53Udp->id,
            'issue_title' => 'DNS Amplification Attack Protection',
            'symptoms' => 'High DNS query volume, network bandwidth saturation, server overload',
            'solution' => 'Disable open recursion: only allow recursive queries from trusted networks. Enable response rate limiting (RRL) in BIND. Use iptables to limit query rates per IP. Monitor with: `sudo tcpdump -i any port 53 -n`',
            'platform' => 'All',
            'verified' => true,
            'upvotes' => 56,
        ]);

        // Create some port relations
        $port80->relatedPorts()->attach($port443->id, ['relation_type' => 'alternative']);
        $port443->relatedPorts()->attach($port80->id, ['relation_type' => 'alternative']);
        $port3306->relatedPorts()->attach($port5432->id, ['relation_type' => 'alternative']);
        $port5432->relatedPorts()->attach($port3306->id, ['relation_type' => 'alternative']);

        // DNS TCP and UDP are complementary
        $port53Tcp->relatedPorts()->attach($port53Udp->id, ['relation_type' => 'complementary', 'description' => 'DNS uses both TCP and UDP protocols']);
        $port53Udp->relatedPorts()->attach($port53Tcp->id, ['relation_type' => 'complementary', 'description' => 'DNS uses both TCP and UDP protocols']);
    }
}
