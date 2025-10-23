# TechReference.io

---

## Introduction

**TechReference.io** is a comprehensive technical reference platform for developers and IT professionals, providing detailed information about:

- **Network Ports** - 65,535+ port reference pages with security data, configuration examples, and live exposure metrics
- **Error Codes** - Windows, macOS, Linux, gaming platforms, and application errors with solutions
- **File Extensions** - 8,000+ file formats with associated programs and conversion paths
- **HTTP Status Codes** - RFC standard codes plus CDN/proxy-specific codes
- **MIME Types** - 1,000+ IANA types with browser compatibility
- **Configuration Files** - (Future phase)

The platform provides SEO-optimized technical reference pages with real-time security data powered by Shodan, making it the go-to resource for developers looking up port information, troubleshooting errors, or understanding file formats.

---

## Tech Stack

- **Backend**: Laravel 12 (PHP 8.2+)
- **Frontend**: Blade templates + AlpineJS 3.x
- **CSS**: Tailwind CSS 4.x
- **Build**: Vite 6.x
- **Testing**: Pest 4.x
- **Database**: PostgreSQL 16 (production) / SQLite (development)
- **Cache**: Redis
- **Search**: PostgreSQL Full-Text + Meilisearch
- **Queue**: Laravel Horizon

---

## Features

### Network Ports Module
- 12,800+ port records (IANA official + Shodan-discovered)
- Real-time exposure statistics from Shodan API
- Security recommendations based on CVE data and exposure counts
- Top products, organizations, and countries for each port
- Automatic software detection and linking
- Configuration examples (Docker, Kubernetes, firewall rules)

### Data Integration
- **IANA Registry**: Official port assignments and service names
- **Shodan API**: Live exposure data, product detection, geographic distribution
- **Automatic Discovery**: Creates port records for non-IANA ports found in Shodan data
- **Software Catalog**: Auto-populated from Shodan product data with intelligent categorization

---

## Installation

### Requirements
- PHP 8.2+
- Composer
- Node.js 18+
- PostgreSQL 13+ (recommended 16) or SQLite
- Redis (optional, for caching)

### Setup

```bash
# Clone the repository
git clone <repository-url>
cd techreference

# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure database in .env
# For development (SQLite):
DB_CONNECTION=sqlite

# For production (PostgreSQL):
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=techreference
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Configure Shodan API
SHODAN_API_KEY=your_shodan_api_key_here

# Run migrations
php artisan migrate

# Seed initial data (optional)
php artisan db:seed

# Build assets
npm run build

# Start development server
php artisan serve
```

### Development Mode

```bash
# Run all services concurrently
composer dev

# Or run individually:
php artisan serve          # Development server
npm run dev                # Vite dev server with HMR
php artisan queue:listen   # Queue worker
php artisan pail           # Stream application logs
```

---

## Data Management Commands

### Port Data Import

**Import IANA Port Registry**
```bash
php artisan ports:import-iana
```
Imports or updates ports from the IANA service-names-port-numbers CSV file.

**Update Shodan Security Data**
```bash
# Update top 1000 ports (FREE - uses Shodan facets endpoint, no credits)
php artisan ports:update-shodan --limit=1000

# Update specific port with detailed facets (uses 1 query credit)
php artisan ports:update-shodan --port=3306

# Update top 100 ports
php artisan ports:update-shodan --limit=100
```

The Shodan command provides two modes:
- **Bulk Mode** (`--limit=N`): Updates exposure counts for top N ports globally (free, uses facets)
- **Detailed Mode** (`--port=N`): Gets complete security data including products, organizations, operating systems, countries, and ASNs (1 credit per port)

Features:
- Auto-creates port records for non-IANA ports found in Shodan data
- Marks discovered ports as "Unregistered" to distinguish from official IANA ports
- Automatically creates Software records and links them to ports
- Preserves detailed facet data during bulk updates
- Captures top products, organizations, operating systems, countries, and ASNs

**Update CVE Vulnerability Data**
```bash
# Update CVE data for all ports with security records
php artisan ports:update-cve

# Update specific port
php artisan ports:update-cve --port=80

# Update all ports for a specific service
php artisan ports:update-cve --service=mysql

# Force update (ignore last update timestamp)
php artisan ports:update-cve --force
```

The CVE command fetches vulnerability data from the NVD (National Vulnerability Database) API:
- Groups ports by service name to minimize API calls
- Caches results for 24 hours to respect rate limits
- Fetches CVE counts by severity (Critical, High, Medium, Low)
- Includes recent critical/high CVEs with descriptions and CVSS scores
- Extracts common weakness types (CWE) for vulnerability analysis
- Automatically calculates average CVSS score

Features:
- Rate limiting: 6-second delay between requests (NVD free tier allows 5 requests per 30 seconds)
- Smart caching: Skips ports updated within the last 24 hours (use `--force` to override)
- Batch processing: Updates multiple ports using the same service with one API call
- No API key required (uses public NVD API endpoint)

**Detect Port Relationships**
```bash
# Automatically detect and create port relationships
php artisan ports:detect-relations

# Force recreate all relations (deletes existing first)
php artisan ports:detect-relations --force
```

This command automatically creates relationships between ports including:
- **Secure Versions**: HTTP→HTTPS, FTP→FTPS/SFTP, etc.
- **Alternative Ports**: 80→8080, 443→8443, etc.
- **Deprecated By**: Telnet→SSH, FTP→SFTP, etc.
- **Part of Suite**: MongoDB cluster ports, Elasticsearch ports, Email services, etc.
- **Complementary**: Same port number, different protocols (TCP/UDP)
- **Associated With**: Commonly used together (HTTP with MySQL/Redis, etc.)

**Scheduling Recommendations:**
```php
// In app/Console/Kernel.php
$schedule->command('ports:update-shodan --limit=1000')->daily();
$schedule->command('ports:update-cve')->daily(); // Run after Shodan updates
$schedule->command('ports:detect-relations')->weekly(); // Run after port updates
```

---

## Security Features

### Rate Limiting
All public routes are protected with IP-based rate limiting to prevent abuse:
- **Standard Routes** (port pages, categories, index): 60 requests per minute per IP
- **Expensive Operations** (search, port ranges): 30 requests per minute per IP
- **Authentication Routes**: 6 requests per minute per IP (login, registration)

### Security Headers
All responses include comprehensive security headers via the `SecurityHeaders` middleware:
- **Content-Security-Policy**: Restricts resource loading to trusted sources
- **X-Frame-Options**: Prevents clickjacking attacks (DENY)
- **X-Content-Type-Options**: Prevents MIME type sniffing (nosniff)
- **Referrer-Policy**: Controls referrer information (strict-origin-when-cross-origin)
- **Permissions-Policy**: Restricts browser features (geolocation, camera, microphone, etc.)
- **Strict-Transport-Security**: Enforces HTTPS in production (HSTS with preload)

### Input Validation & Sanitization
- Form Request validation on all user inputs (PortSearchRequest, RangeRequest, ShowPortRequest)
- Automatic input sanitization (strip_tags, trim, normalize whitespace)
- Port number validation (1-65535 range)
- SQL injection prevention via Eloquent ORM parameterization
- XSS prevention via Blade `{{ }}` escaping
- CSRF protection on all forms (Laravel default)

### Audit Logging
Admin actions are automatically logged for security auditing:
- User authentication events (login, logout, failed attempts, registration, password reset)
- Model changes (created, updated, deleted) via `Auditable` trait
- Tracks user ID, IP address, user agent, old/new values
- Searchable audit logs with indexed timestamps

Add the `Auditable` trait to any model to enable automatic audit logging:
```php
use App\Models\Concerns\Auditable;

class YourModel extends Model
{
    use Auditable;
}
```

---

## Testing

```bash
# Run all tests
composer test
# Or: php artisan test

# Run specific test file
php artisan test tests/Feature/Ports/PortPageTest.php

# Run tests with Pest directly
vendor/bin/pest

# Run tests with filter
vendor/bin/pest --filter=port
```

### Code Quality

```bash
# Format code with Laravel Pint
vendor/bin/pint

# Format Blade templates with Prettier
npx prettier --write "resources/**/*.blade.php"

# Static analysis with Larastan (PHPStan level 7)
vendor/bin/phpstan analyse

# Build assets for production
npm run build
```

---

## Project Structure

```
techreference/
├── app/
│   ├── Console/Commands/       # Artisan commands (data imports, updates)
│   ├── Http/Controllers/       # Controllers organized by module
│   └── Models/                 # Eloquent models (Port, Software, PortSecurity, etc.)
├── database/
│   ├── migrations/             # Database schema migrations
│   └── seeders/                # Data seeders
├── resources/
│   ├── views/                  # Blade templates
│   ├── js/                     # JavaScript (AlpineJS)
│   └── css/                    # Tailwind CSS
├── routes/
│   ├── web.php                 # Public-facing routes
│   ├── api.php                 # API routes
│   └── auth.php                # Authentication routes
└── tests/                      # Pest tests
```

---

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

---

## License

TechReference.io is open-sourced software licensed under the MIT license.

---

## Support

For issues, questions, or contributions, please visit the GitHub repository or contact the maintainers.
