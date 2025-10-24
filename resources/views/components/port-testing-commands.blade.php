@props(['port'])

<x-content-block title="Testing & Troubleshooting Commands">
    @php
    // Detect port categories - prioritize specific categories over general ones
    $categoryData = null;
    $categorySlug = null;

    if ($port->categories && $port->categories->isNotEmpty()) {
        // Priority order: database > remote-access > mail > gaming > web-services
        $prioritySlugs = ['database', 'databases', 'remote', 'remote-access', 'mail', 'gaming', 'web', 'web-services'];

        $categorySlug = null;
        foreach ($prioritySlugs as $priority) {
            if ($port->categories->where('slug', $priority)->isNotEmpty()) {
                $categorySlug = $priority;
                break;
            }
        }

        // Fallback to first category if none matched
        if (!$categorySlug) {
            $categorySlug = $port->categories->first()->slug;
        }

        // Category-specific configurations
        $categoryConfigs = [
            'database' => [
                'description' => 'Database ports should never be exposed to the internet',
                'security_warning' => 'CRITICAL: Use SSH tunneling or VPN for remote access',
                'commands' => [
                    'Check active connections' => 'netstat -an | grep ESTABLISHED | grep ' . $port->port_number . ' | wc -l',
                    'SSH Tunnel (secure remote access)' => 'ssh -L ' . $port->port_number . ':localhost:' . $port->port_number . ' user@remote-host',
                ],
            ],
            'databases' => [
                'description' => 'Database ports should never be exposed to the internet',
                'security_warning' => 'CRITICAL: Use SSH tunneling or VPN for remote access',
                'commands' => [
                    'Check active connections' => 'netstat -an | grep ESTABLISHED | grep ' . $port->port_number . ' | wc -l',
                    'SSH Tunnel (secure remote access)' => 'ssh -L ' . $port->port_number . ':localhost:' . $port->port_number . ' user@remote-host',
                ],
            ],
            'web' => [
                'description' => 'Web service ports are typically reverse proxied',
                'security_warning' => 'Use SSL/TLS termination at reverse proxy',
                'commands' => [
                    'Test with cURL' => 'curl -I http://[host]:' . $port->port_number,
                    'Test with wget' => 'wget --spider http://[host]:' . $port->port_number,
                    'Follow redirects' => 'curl -L http://[host]:' . $port->port_number,
                    'Check SSL certificate' => 'openssl s_client -connect [host]:' . $port->port_number . ' -servername [host]',
                ],
                'nginx_proxy' => "location / {\n    proxy_pass http://localhost:" . $port->port_number . ";\n    proxy_set_header Host \$host;\n    proxy_set_header X-Real-IP \$remote_addr;\n}",
                'apache_proxy' => "ProxyPass / http://localhost:" . $port->port_number . "/\nProxyPassReverse / http://localhost:" . $port->port_number . "/",
            ],
            'web-services' => [
                'description' => 'Web service ports are typically reverse proxied',
                'security_warning' => 'Use SSL/TLS termination at reverse proxy',
                'commands' => [
                    'Test with cURL' => 'curl -I http://[host]:' . $port->port_number,
                    'Test with wget' => 'wget --spider http://[host]:' . $port->port_number,
                    'Follow redirects' => 'curl -L http://[host]:' . $port->port_number,
                    'Check SSL certificate' => 'openssl s_client -connect [host]:' . $port->port_number . ' -servername [host]',
                ],
                'nginx_proxy' => "location / {\n    proxy_pass http://localhost:" . $port->port_number . ";\n    proxy_set_header Host \$host;\n    proxy_set_header X-Real-IP \$remote_addr;\n}",
                'apache_proxy' => "ProxyPass / http://localhost:" . $port->port_number . "/\nProxyPassReverse / http://localhost:" . $port->port_number . "/",
            ],
            'mail' => [
                'description' => 'Email service ports',
                'security_warning' => 'Always use TLS/SSL variants when available',
                'commands' => [
                    'Test SMTP connection' => 'telnet [host] ' . $port->port_number,
                    'Test with SSL/TLS' => 'openssl s_client -connect [host]:' . $port->port_number . ' -starttls smtp',
                    'Check mail queue' => 'mailq',
                ],
            ],
            'gaming' => [
                'description' => 'Gaming server ports often need both TCP and UDP',
                'security_warning' => 'Configure port forwarding in router for hosting servers',
                'commands' => [
                    'Test UDP port (Linux)' => 'nc -u -zv [host] ' . $port->port_number,
                    'Allow UDP (iptables)' => 'iptables -A INPUT -p udp --dport ' . $port->port_number . ' -j ACCEPT',
                    'Allow UDP (UFW)' => 'ufw allow ' . $port->port_number . '/udp',
                ],
            ],
            'remote' => [
                'description' => 'Remote access ports are high-risk targets',
                'security_warning' => 'Use key-based authentication, never passwords. Install fail2ban.',
                'commands' => [
                    'Generate SSH key' => 'ssh-keygen -t ed25519 -C "your_email@example.com"',
                    'Copy SSH key to server' => 'ssh-copy-id user@[host]',
                    'Check SSH attempts (fail2ban)' => 'fail2ban-client status sshd',
                    'View authentication logs' => 'tail -f /var/log/auth.log',
                ],
            ],
            'remote-access' => [
                'description' => 'Remote access ports are high-risk targets',
                'security_warning' => 'Use key-based authentication, never passwords. Install fail2ban.',
                'commands' => [
                    'Generate SSH key' => 'ssh-keygen -t ed25519 -C "your_email@example.com"',
                    'Copy SSH key to server' => 'ssh-copy-id user@[host]',
                    'Check SSH attempts (fail2ban)' => 'fail2ban-client status sshd',
                    'View authentication logs' => 'tail -f /var/log/auth.log',
                ],
            ],
        ];

        $categoryData = $categoryConfigs[$categorySlug] ?? null;
    }

    $commandTabs = [
        'connection-test' => [
            'label' => 'Connection Testing',
            'content' => '<div class="space-y-4">' .
                view('components.code-snippet', [
                    'title' => 'Linux/macOS - Test Connection',
                    'code' => 'nc -zv [host] ' . $port->port_number,
                    'language' => 'bash',
                    'explanation' => 'Use netcat to test if port is open and accessible',
                ])->render() .
                view('components.code-snippet', [
                    'title' => 'Windows - Test Connection',
                    'code' => 'Test-NetConnection -ComputerName [host] -Port ' . $port->port_number,
                    'language' => 'powershell',
                    'explanation' => 'PowerShell command to test TCP connectivity',
                ])->render() .
                view('components.code-snippet', [
                    'title' => 'Telnet Test',
                    'code' => 'telnet [host] ' . $port->port_number,
                    'language' => 'bash',
                    'explanation' => 'Classic telnet test (works on Windows, Linux, macOS)',
                ])->render() .
                view('components.code-snippet', [
                    'title' => 'Nmap Port Scan',
                    'code' => 'nmap -p ' . $port->port_number . ' -sV [host]',
                    'language' => 'bash',
                    'explanation' => 'Scan port and detect service version',
                ])->render() .
                '</div>'
        ],
        'local-check' => [
            'label' => 'Local Port Check',
            'content' => '<div class="space-y-4">' .
                view('components.code-snippet', [
                    'title' => 'Linux - Check Listening Ports (lsof)',
                    'code' => 'lsof -i :' . $port->port_number,
                    'language' => 'bash',
                    'explanation' => 'List processes using this port',
                ])->render() .
                view('components.code-snippet', [
                    'title' => 'Linux - Check with netstat',
                    'code' => 'netstat -an | grep ' . $port->port_number,
                    'language' => 'bash',
                    'explanation' => 'Show all connections on this port',
                ])->render() .
                view('components.code-snippet', [
                    'title' => 'Linux - Check with ss (modern)',
                    'code' => 'ss -tunlp | grep ' . $port->port_number,
                    'language' => 'bash',
                    'explanation' => 'Modern replacement for netstat - faster and more detailed',
                ])->render() .
                view('components.code-snippet', [
                    'title' => 'Windows - Check with netstat',
                    'code' => 'netstat -ano | findstr :' . $port->port_number,
                    'language' => 'powershell',
                    'explanation' => 'Show all connections and process IDs using this port',
                ])->render() .
                '</div>'
        ],
        'firewall' => [
            'label' => 'Firewall Rules',
            'content' => '<div class="space-y-4">' .
                view('components.code-snippet', [
                    'title' => 'iptables - Allow Port',
                    'code' => 'iptables -A INPUT -p tcp --dport ' . $port->port_number . ' -j ACCEPT',
                    'language' => 'bash',
                    'explanation' => 'Allow incoming TCP connections',
                ])->render() .
                view('components.code-snippet', [
                    'title' => 'iptables - Allow from Specific Source',
                    'code' => 'iptables -A INPUT -p tcp --dport ' . $port->port_number . ' -s [source_ip] -j ACCEPT',
                    'language' => 'bash',
                    'explanation' => 'Restrict access to specific IP address',
                ])->render() .
                view('components.code-snippet', [
                    'title' => 'iptables - Check Rules',
                    'code' => 'iptables -L -n | grep ' . $port->port_number,
                    'language' => 'bash',
                    'explanation' => 'View current firewall rules for this port',
                ])->render() .
                view('components.code-snippet', [
                    'title' => 'iptables - Remove Rule',
                    'code' => 'iptables -D INPUT -p tcp --dport ' . $port->port_number . ' -j ACCEPT',
                    'language' => 'bash',
                    'explanation' => 'Delete the allow rule',
                ])->render() .
                '</div>'
        ],
        'ufw' => [
            'label' => 'UFW (Ubuntu)',
            'content' => '<div class="space-y-4">' .
                view('components.code-snippet', [
                    'title' => 'UFW - Allow Port',
                    'code' => 'ufw allow ' . $port->port_number . '/tcp',
                    'language' => 'bash',
                    'explanation' => 'Allow TCP traffic on this port',
                ])->render() .
                view('components.code-snippet', [
                    'title' => 'UFW - Allow from Source',
                    'code' => 'ufw allow from [source_ip] to any port ' . $port->port_number,
                    'language' => 'bash',
                    'explanation' => 'Allow access only from specific IP',
                ])->render() .
                view('components.code-snippet', [
                    'title' => 'UFW - Deny Port',
                    'code' => 'ufw deny ' . $port->port_number . '/tcp',
                    'language' => 'bash',
                    'explanation' => 'Block all traffic on this port',
                ])->render() .
                view('components.code-snippet', [
                    'title' => 'UFW - Check Status',
                    'code' => 'ufw status | grep ' . $port->port_number,
                    'language' => 'bash',
                    'explanation' => 'View current UFW rules for this port',
                ])->render() .
                '</div>'
        ],
        'firewalld' => [
            'label' => 'firewalld (RHEL)',
            'content' => '<div class="space-y-4">' .
                view('components.code-snippet', [
                    'title' => 'firewalld - Allow Port',
                    'code' => 'firewall-cmd --add-port=' . $port->port_number . '/tcp --permanent',
                    'language' => 'bash',
                    'explanation' => 'Add permanent firewall rule',
                ])->render() .
                view('components.code-snippet', [
                    'title' => 'firewalld - Reload',
                    'code' => 'firewall-cmd --reload',
                    'language' => 'bash',
                    'explanation' => 'Apply firewall changes',
                ])->render() .
                view('components.code-snippet', [
                    'title' => 'firewalld - Check Ports',
                    'code' => 'firewall-cmd --list-ports',
                    'language' => 'bash',
                    'explanation' => 'List all open ports',
                ])->render() .
                view('components.code-snippet', [
                    'title' => 'firewalld - Remove Port',
                    'code' => 'firewall-cmd --remove-port=' . $port->port_number . '/tcp --permanent',
                    'language' => 'bash',
                    'explanation' => 'Remove port rule and reload',
                ])->render() .
                '</div>'
        ],
        'windows-firewall' => [
            'label' => 'Windows Firewall',
            'content' => '<div class="space-y-4">' .
                view('components.code-snippet', [
                    'title' => 'Windows - Allow Inbound',
                    'code' => 'netsh advfirewall firewall add rule name="Port ' . $port->port_number . '" dir=in action=allow protocol=TCP localport=' . $port->port_number,
                    'language' => 'powershell',
                    'explanation' => 'Create inbound rule to allow TCP traffic',
                ])->render() .
                view('components.code-snippet', [
                    'title' => 'Windows - Allow Outbound',
                    'code' => 'netsh advfirewall firewall add rule name="Port ' . $port->port_number . '" dir=out action=allow protocol=TCP localport=' . $port->port_number,
                    'language' => 'powershell',
                    'explanation' => 'Create outbound rule to allow TCP traffic',
                ])->render() .
                view('components.code-snippet', [
                    'title' => 'Windows - Remove Rule',
                    'code' => 'netsh advfirewall firewall delete rule name="Port ' . $port->port_number . '"',
                    'language' => 'powershell',
                    'explanation' => 'Delete firewall rule by name',
                ])->render() .
                '</div>'
        ],
        'docker' => [
            'label' => 'Docker',
            'content' => '<div class="space-y-4">' .
                view('components.code-snippet', [
                    'title' => 'Docker - Basic Port Mapping',
                    'code' => 'docker run -p ' . $port->port_number . ':' . $port->port_number . ' [image]',
                    'language' => 'bash',
                    'explanation' => 'Map container port to host port',
                ])->render() .
                view('components.code-snippet', [
                    'title' => 'Docker - Localhost Only',
                    'code' => 'docker run -p 127.0.0.1:' . $port->port_number . ':' . $port->port_number . ' [image]',
                    'language' => 'bash',
                    'explanation' => 'Bind to localhost only for security',
                ])->render() .
                view('components.code-snippet', [
                    'title' => 'Docker - Custom Host Port',
                    'code' => 'docker run -p [host_port]:' . $port->port_number . ' [image]',
                    'language' => 'bash',
                    'explanation' => 'Map to different port on host',
                ])->render() .
                view('components.code-snippet', [
                    'title' => 'Docker Compose',
                    'code' => "services:\n  app:\n    ports:\n      - \"" . $port->port_number . ":" . $port->port_number . "\"",
                    'language' => 'yaml',
                    'explanation' => 'Port mapping in docker-compose.yml',
                ])->render() .
                '</div>'
        ],
    ];

    // Add category-specific tab if available
    if ($categoryData) {
        $categoryContent = '<div class="space-y-4">';

        // Add security warning if present
        if (isset($categoryData['security_warning'])) {
            $categoryContent .= '<div class="p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">';
            $categoryContent .= '<div class="flex items-start gap-2">';
            $categoryContent .= '<svg class="w-5 h-5 text-red-600 dark:text-red-400 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
            $categoryContent .= '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />';
            $categoryContent .= '</svg>';
            $categoryContent .= '<div class="text-sm text-red-800 dark:text-red-200"><strong>Security Notice:</strong> ' . $categoryData['security_warning'] . '</div>';
            $categoryContent .= '</div>';
            $categoryContent .= '</div>';
        }

        // Add category-specific commands
        if (isset($categoryData['commands'])) {
            foreach ($categoryData['commands'] as $title => $code) {
                $categoryContent .= view('components.code-snippet', [
                    'title' => $title,
                    'code' => $code,
                    'language' => 'bash',
                    'explanation' => null,
                ])->render();
            }
        }

        // Add reverse proxy configs for web category
        if (in_array($categorySlug, ['web', 'web-services'])) {
            if (isset($categoryData['nginx_proxy'])) {
                $categoryContent .= view('components.code-snippet', [
                    'title' => 'Nginx Reverse Proxy',
                    'code' => $categoryData['nginx_proxy'],
                    'language' => 'nginx',
                    'explanation' => 'Add to your Nginx server block',
                ])->render();
            }
            if (isset($categoryData['apache_proxy'])) {
                $categoryContent .= view('components.code-snippet', [
                    'title' => 'Apache Reverse Proxy',
                    'code' => $categoryData['apache_proxy'],
                    'language' => 'apache',
                    'explanation' => 'Add to your Apache VirtualHost (requires mod_proxy)',
                ])->render();
            }
        }

        $categoryContent .= '</div>';

        // Determine tab label based on category
        $categoryLabel = match($categorySlug) {
            'database', 'databases' => 'Database Specific',
            'web', 'web-services' => 'Web Server',
            'mail' => 'Mail Server',
            'gaming' => 'Gaming',
            'remote', 'remote-access' => 'Remote Access',
            default => 'Category Specific',
        };

        // Insert at the beginning of tabs array
        $commandTabs = array_merge(
            ['category-specific' => ['label' => $categoryLabel, 'content' => $categoryContent]],
            $commandTabs
        );
    }
    @endphp

    <x-tabs
        :tabs="$commandTabs"
        storageKey="port-commands-tab"
    />
</x-content-block>
