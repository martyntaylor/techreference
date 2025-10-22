<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Web Services',
                'description' => 'HTTP, HTTPS, and web-related services',
            ],
            [
                'name' => 'Database',
                'description' => 'Database management systems and related services',
            ],
            [
                'name' => 'Email',
                'description' => 'Email protocols including SMTP, POP3, and IMAP',
            ],
            [
                'name' => 'File Transfer',
                'description' => 'FTP, SFTP, and other file transfer protocols',
            ],
            [
                'name' => 'Remote Access',
                'description' => 'SSH, RDP, Telnet, and remote administration',
            ],
            [
                'name' => 'Gaming',
                'description' => 'Gaming servers and related services',
            ],
            [
                'name' => 'Messaging',
                'description' => 'Chat, instant messaging, and communication protocols',
            ],
            [
                'name' => 'DNS',
                'description' => 'Domain Name System and related services',
            ],
            [
                'name' => 'Monitoring',
                'description' => 'System monitoring and management protocols',
            ],
            [
                'name' => 'VPN',
                'description' => 'Virtual Private Network services',
            ],
        ];

        foreach ($categories as $category) {
            Category::create([
                'name' => $category['name'],
                'slug' => Str::slug($category['name']),
                'description' => $category['description'],
            ]);
        }
    }
}
