# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**TechReference** (techreference.io) is a comprehensive technical reference platform for developers and IT professionals, covering network ports, error codes, file extensions, HTTP status codes, MIME types, and configuration files.

### Content Modules
- **Network Ports**: 65,535 port reference pages with security data, configuration examples, and live exposure metrics
- **Error Codes**: Windows, macOS, Linux, gaming platforms, and application errors with solutions
- **File Extensions**: 8,000+ file formats with associated programs and conversion paths
- **HTTP Status Codes**: RFC standard codes plus CDN/proxy-specific codes
- **MIME Types**: 1,000+ IANA types with browser compatibility
- **Configuration Files**: Future phase

### Platform Goals
- SEO dominance for technical searches (rank #1-3)
- 100% schema-valid rich snippets
- Cross-module content interconnections (ports ↔ errors ↔ file types ↔ HTTP codes)
- Public API for developers
- Interactive tools (port checker, error decoder, file identifier)

**Tech Stack:**
- Backend: Laravel 12 (PHP 8.2+)
- Frontend: Blade templates + AlpineJS 3.x
- CSS: Tailwind CSS 4.x
- Build: Vite 6.x
- Testing: Pest 4.x
- Database: PostgreSQL 13+ (production, recommended PostgreSQL 16) / SQLite (development)
- Cache: Redis
- Search: PostgreSQL Full-Text + Meilisearch
- Queue: Laravel Horizon

**Database Requirements:**
- PostgreSQL 9.0+ minimum supported (basic functionality with fallback indexes)
- PostgreSQL 11+ recommended (adds INCLUDE clause for covering indexes)
- PostgreSQL 13+ recommended for optimal performance (DESC in index expressions)
- Note: B-tree indexes with ORDER BY DESC have been supported since PostgreSQL 8.3
- Migrations automatically detect PostgreSQL version and use appropriate index syntax

## Development Commands

### Initial Setup
```bash
composer install
npm install
cp .env.example .env
php artisan key:generate

# For SQLite (development)
touch database/database.sqlite

# For PostgreSQL (production)
# Configure DB_CONNECTION=pgsql in .env

php artisan migrate
php artisan db:seed  # Seed initial data when available
```

### Development Server
```bash
# Run all services concurrently (server, queue, logs, vite)
composer dev

# Or run individually:
php artisan serve          # Start development server
npm run dev                # Start Vite dev server
php artisan queue:listen   # Start queue worker
php artisan pail           # Stream application logs
```

### Testing
```bash
# Run all tests
composer test
# Or: php artisan test

# Run specific test file
php artisan test tests/Feature/Ports/PortPageTest.php

# Run tests with Pest directly
vendor/bin/pest
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

### Data Management
```bash
# Import IANA port registry
php artisan ports:import-iana

# Update Shodan security data
php artisan ports:update-shodan

# Update CVE data for ports
php artisan ports:update-cve

# Generate sitemap
php artisan sitemap:generate
```

## Architecture Overview

### URL Structure
```
techreference.io/
├── /port/{number}              # Individual port pages (65,535 pages)
├── /ports/{category}           # Category listings (web, database, gaming, etc.)
├── /ports/range/{start}-{end}  # Range views (e.g., ephemeral ports)
├── /error/{platform}/{code}    # Error code pages
├── /extension/{ext}            # File extension pages
├── /http/{code}                # HTTP status code pages
├── /mime/{type}                # MIME type pages
├── /search                     # Unified search across all modules
├── /api/v1/*                   # Public API endpoints
└── /tools/*                    # Interactive tools
    ├── /tools/port-checker
    ├── /tools/error-decoder
    ├── /tools/file-identifier
    └── /tools/firewall-generator
```

### Route Organization
- **web.php**: Public-facing content pages (ports, errors, extensions, HTTP codes, MIME types)
- **api.php**: API endpoints for developers
- **auth.php**: Authentication routes (admin dashboard access)
- All routes use named routes for cross-linking between modules

### Controller Organization
```
app/Http/Controllers/
├── Auth/                    # Authentication controllers
├── Settings/                # Admin settings controllers
├── Ports/
│   ├── PortController       # Individual port pages
│   ├── CategoryController   # Port category listings
│   └── RangeController      # Port range views
├── Errors/
│   └── ErrorCodeController  # Error code pages
├── Extensions/
│   └── FileExtensionController
├── Http/
│   └── HttpStatusController
├── Mime/
│   └── MimeTypeController
├── SearchController         # Unified search
├── Tools/
│   ├── PortCheckerController
│   ├── ErrorDecoderController
│   └── FirewallGeneratorController
└── Api/
    └── V1/
        ├── PortApiController
        └── SearchApiController
```

### Database Schema (Key Tables)

**Ports Module:**
- `ports` - Core port data (port_number, protocol, service_name, IANA status)
- `software` - Software applications
- `port_software` - Many-to-many relationship
- `port_security` - Shodan/Censys data, CVE counts
- `port_configs` - Configuration snippets (Docker, K8s, firewall rules)
- `port_issues` - Community-sourced issues/solutions
- `port_relations` - Related ports (alternatives, secure versions)
- `categories` - Port categories (web, database, gaming, etc.)
- `port_categories` - Many-to-many relationship

**Other Modules:**
- `error_codes` - Platform-specific error codes
- `file_extensions` - File format data
- `http_status_codes` - HTTP status codes
- `mime_types` - MIME type registry

### View Structure

**Layouts:**
- `resources/views/components/layouts/app.blade.php` - Public-facing layout
- `resources/views/components/layouts/dashboard-layout.blade.php` - Admin layout
- `resources/views/components/layouts/auth.blade.php` - Auth layout

**Module Pages:**
- `resources/views/ports/show.blade.php` - Individual port page
- `resources/views/ports/category.blade.php` - Category listing
- `resources/views/ports/range.blade.php` - Range view
- `resources/views/errors/show.blade.php` - Error code page
- `resources/views/extensions/show.blade.php` - File extension page
- `resources/views/http/show.blade.php` - HTTP status page
- `resources/views/mime/show.blade.php` - MIME type page
- `resources/views/search/index.blade.php` - Search results
- `resources/views/tools/*` - Interactive tools

**Reusable Components:**
- `resources/views/components/port-card.blade.php` - Port preview card
- `resources/views/components/security-badge.blade.php` - Risk level indicator
- `resources/views/components/code-snippet.blade.php` - Syntax-highlighted code
- `resources/views/components/schema-json.blade.php` - Structured data output
- `resources/views/components/related-content.blade.php` - Cross-module links

### Frontend Assets
- **JavaScript**: `resources/js/bootstrap.js` initializes Axios and AlpineJS globally
- **CSS**: Tailwind CSS configuration in `resources/css/app.css`
- **Icons**: Uses Blade FontAwesome for icons via `@fa()` directive
- **Interactive Tools**: AlpineJS components for port checker, search, filters

### SEO & Structured Data
Every page includes:
- Schema.org markup (TechArticle, FAQPage)
- Open Graph tags for social sharing
- Canonical URLs
- Auto-generated meta descriptions
- Optimized heading hierarchy (H1 → H2 → H3)
- Internal cross-linking to related content

### API Structure
```
GET /api/v1/ports/{number}                    # Port details
GET /api/v1/ports/{number}/software           # Software using port
GET /api/v1/ports/{number}/issues             # Common issues
GET /api/v1/ports/search?q=mysql              # Search ports
GET /api/v1/ports/category/{slug}             # Category ports
GET /api/v1/errors/{platform}/{code}          # Error details
GET /api/v1/extensions/{ext}                  # Extension details
GET /api/v1/http/{code}                       # HTTP status details
GET /api/v1/mime/{type}                       # MIME type details
GET /api/v1/search?q={query}&type={module}    # Unified search
```

### Testing Strategy
- **Feature Tests**: Test each module's page rendering, data display, cross-linking
- **Unit Tests**: Test data models, search algorithms, import scripts
- **API Tests**: Validate API responses, rate limiting, authentication
- Uses Pest framework with RefreshDatabase trait
- PostgreSQL testing database (or SQLite in-memory for CI)

## Key Development Patterns

### Adding a New Port Page
1. Import data via `php artisan ports:import-iana` or manual seeding
2. Route: `Route::get('/port/{number}', [PortController::class, 'show'])->name('port.show')`
3. Controller fetches port with relationships (software, security, configs, issues)
4. View renders structured data, configuration snippets, security warnings
5. Schema.org JSON-LD added to `<head>`
6. Related ports/errors/files cross-linked at bottom

### Cross-Module Linking Pattern
When displaying content, automatically link to related modules:
- Port 3306 → links to MySQL errors, .sql extension, database config files
- Error "Connection refused" → links to relevant ports (80, 443, 3306)
- .zip extension → links to MIME type application/zip, compression tools

### Data Import Scripts
Located in `app/Console/Commands/`:
- `ImportIanaPorts.php` - Weekly IANA registry sync
- `UpdateShodanData.php` - Daily Shodan exposure counts
- `UpdateCveData.php` - Daily CVE database sync
- `ImportErrorCodes.php` - Platform error code imports
- `ImportFileExtensions.php` - File extension registry import

### Working with AlpineJS
AlpineJS is globally available via `window.Alpine`. Use for:
- Interactive search/filter components
- Port checker tool (WebRTC local network scanning)
- Collapsible configuration snippets
- Copy-to-clipboard functionality
- Tab switching for multi-service ports

### Styling with Tailwind
- Tailwind CSS 4.x with Vite plugin
- Prettier plugin automatically sorts classes
- Custom theme colors for risk levels (high=red, medium=yellow, low=green)
- Responsive design for mobile/tablet/desktop

### Performance Optimization
- Eager load relationships to avoid N+1 queries
- Cache port pages (1 hour TTL, clear on data update)
- Redis for search result caching
- PostgreSQL indexes on port_number, service_name, category_id
- Full-text search with tsvector for fast queries
- CDN (Cloudflare) for static assets

### Content Update Workflow
1. Automated jobs run daily/weekly (IANA, Shodan, CVE imports)
2. Data normalized and stored in PostgreSQL
3. Cache invalidated for affected pages
4. Sitemap regenerated
5. Search index updated (Meilisearch)

## Development Phases

**Phase 1: Ports Module (Current)**
- Port page template with all sections
- IANA import automation
- Shodan/Censys integration
- Basic search functionality
- 1,000 priority ports seeded

**Phase 2: Error Codes Module**
- Error page template
- Windows/Mac/Linux error imports
- Video solution embedding
- User-contributed solutions

**Phase 3: File Extensions Module**
- Extension pages
- Software association system
- Conversion path recommendations
- Security warnings for dangerous formats

**Phase 4: HTTP & Integration**
- HTTP status code pages
- MIME type pages
- Cross-module linking system
- Public API launch
- Interactive tools (port checker, firewall generator)

## Performance Targets
- Page load: <1s (cached), <3s (uncached)
- API response: <100ms
- Search results: <200ms
- Database queries: <50ms (with proper indexes)
- 100% schema validation (Google Rich Results Test)

## Resources
- Project spec: `docs/techreference-project-spec.md`
- Database schema: See spec section 4.3
- Port page template: See spec section 4.2
- API documentation: (to be created)
