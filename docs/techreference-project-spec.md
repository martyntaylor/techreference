# **TechReference Directory – Complete Project Specification**
*Version: 1.0 – October 2025*  
*A comprehensive technical reference platform for developers and IT professionals*

---

## **1. Project Overview**

**TechReference** is a comprehensive technical directory covering:
- **Network Ports** (65,535 ports)
- **Error Codes** (Windows, Mac, Linux, Gaming, Applications)
- **File Extensions** (8,000+ formats)
- **HTTP Status Codes** (All RFC codes + CDN/proxy codes)
- **MIME Types** (1,000+ types)
- **Configuration Files** (Future phase)
- **Timezones** (https://www.iana.org/time-zones)

### Core Platform Goals
| Goal | Target Metric | Measurement |
|------|--------------|-------------|
| SEO Dominance | Rank #1-3 for technical searches | Google Search Console |
| User Engagement | >2 min avg. time on page | Analytics |
| Rich Snippets | 100% schema-valid | Google Rich Results Test |
| Network Effect | 20% cross-section traffic | Internal analytics |
| API Adoption | 100+ developer signups/month | API dashboard |

---

## **2. Site Architecture Overview**

```
techreference.io/
├── /port/[number]              # 65,535 port pages
├── /error/[platform]/[code]    # Error code pages
├── /extension/[ext]            # File extension pages
├── /http/[code]                # HTTP status pages
├── /mime/[type]                # MIME type pages
├── /search                     # Unified search
├── /api                        # Developer API
├── /timezones                  # Timezones pages
└── /tools/                     # Interactive tools
    ├── port-checker
    ├── error-decoder
    └── file-identifier
```

### Content Interconnections
- Error pages link to → relevant ports, file types
- Port pages link to → common errors, config files
- File extensions link to → MIME types, related errors
- HTTP codes link to → debugging ports, error patterns

---

## **3. Module Overview (Brief)**

### **3.1 Error Codes Module**
- **Scope**: Windows, macOS, Linux, PlayStation, Xbox, Nintendo, Applications
- **Content**: Solutions, causes, prevention, related issues
- **Special Features**: Video solutions, user comments, fix verification
- **Volume**: ~50,000 unique error codes

### **3.2 File Extensions Module**
- **Scope**: All registered + common unregistered extensions
- **Content**: Programs that open, conversion paths, security risks
- **Special Features**: Online viewers/converters, sample files
- **Volume**: 8,000+ extensions

### **3.3 HTTP Status Codes Module**
- **Scope**: RFC standard codes, CDN codes (Cloudflare 5xx), Proxy codes
- **Content**: Meaning, causes, fixes, server configs
- **Special Features**: Framework-specific examples, logs analyzer
- **Volume**: ~100 standard + ~50 vendor-specific

### **3.4 MIME Types Module**
- **Scope**: IANA registered types, common unofficial types
- **Content**: File associations, headers, server configuration
- **Special Features**: Browser compatibility matrix
- **Volume**: 1,000+ types

---

## **4. PORTS MODULE (DETAILED SPECIFICATION)**

### **4.1 Port Page URL Structure**

| URL Pattern | Type | Purpose |
|------------|------|---------|
| `/port/3306` | **Canonical Port Page** | All port data in one place |
| `/port/80` | Same structure | Multiple services in tabs |
| `/ports/database` | Category listing | Links to individual ports |
| `/ports/gaming` | Category listing | Gaming-specific ports |
| `/ports/range/49152-65535` | Range view | Ephemeral ports |

**Critical Rule**: One canonical page per port. Never split by service (no `/port/3306/mysql`).

### **4.2 Port Page Template**

```markdown
# Port {number} ({protocol}) – {primary_service}

## Quick Reference
> **Status**: {IANA status} – `{service_name}` – [IANA Registry]({url})
> **Protocol**: {TCP|UDP|SCTP}
> **Risk Level**: {High|Medium|Low} based on CVE history
> **Live Instances**: {shodan_count} publicly exposed – [Shodan]({url})

## Services Using This Port
| Software | Default | Version | Encrypted | Notes |
|----------|---------|---------|-----------|-------|
| MySQL | Yes | 3.23+ | No (use 3307 for TLS) | Most common |
| MariaDB | Yes | 5.5+ | Optional | Fork of MySQL |
| Percona | Yes | 5.5+ | Optional | Performance focused |

## Security Assessment
- **Encrypted by default?** {yes/no}
- **Common vulnerabilities**: {CVE list with scores}
- **Should be exposed to internet?** {yes/no/conditional}
- **Shodan exposure**: {count} instances found
- **Risk factors**: {list}

## Configuration & Access

### Firewall Rules
```bash
# iptables
iptables -A INPUT -p tcp --dport 3306 -s 10.0.0.0/8 -j ACCEPT

# ufw (Ubuntu)
ufw allow from 10.0.0.0/8 to any port 3306

# firewall-cmd (RHEL/CentOS)
firewall-cmd --add-rich-rule='rule family="ipv4" source address="10.0.0.0/8" port port="3306" protocol="tcp" accept'

# Windows Firewall
New-NetFirewallRule -DisplayName "MySQL" -Direction Inbound -Protocol TCP -LocalPort 3306
```

### Docker Configuration
```yaml
# docker-compose.yml
services:
  mysql:
    ports:
      - "3306:3306"  # Expose to host
      - "127.0.0.1:3306:3306"  # Local only
```

### Kubernetes Service
```yaml
apiVersion: v1
kind: Service
spec:
  ports:
  - port: 3306
    targetPort: 3306
```

## Testing & Monitoring

### Test Connectivity
```bash
# Check if port is open
nc -zv hostname 3306
telnet hostname 3306
nmap -p 3306 hostname

# Service-specific test
mysqladmin -h hostname -P 3306 ping
```

### Monitor Service
```bash
# Prometheus exporter
mysql_exporter --web.listen-address=:9104

# Health check script
#!/bin/bash
mysqladmin ping -h localhost || exit 1
```

## Common Issues & Solutions

| Issue | Symptoms | Solution |
|-------|----------|----------|
| Connection refused | "Can't connect to MySQL server" | Check `bind-address` in my.cnf |
| Port already in use | "Address already in use" | `lsof -i :3306` to find process |
| Firewall blocking | Timeout on connection | Add firewall rule (see above) |
| Wrong protocol | Intermittent failures | Ensure using TCP not UDP |

## Related Ports
- **33060** → MySQL X Protocol (Document Store)
- **33062** → MySQL Group Replication
- **3307** → MySQL with TLS/SSL
- **5432** → PostgreSQL (alternative DB)

## Historical Context
- **First Assignment**: 1994 (MySQL inception)
- **IANA Registration**: Official since 1997
- **Notable Incidents**: {major breaches/issues involving this port}

## Additional Resources
- [MySQL Official Docs](https://dev.mysql.com/doc/)
- [MariaDB Port Configuration](https://mariadb.com/kb/en/port/)
- [Security Best Practices](...)
```

### **4.3 Database Schema (PostgreSQL)**

```sql
-- Core port data
CREATE TABLE ports (
    id INTEGER PRIMARY KEY,
    port_number INTEGER NOT NULL UNIQUE,
    protocol VARCHAR(10) NOT NULL, -- TCP, UDP, SCTP
    iana_status VARCHAR(50), -- Official, Unofficial, Reserved
    service_name VARCHAR(100),
    service_description TEXT,
    iana_url TEXT,
    iana_updated DATE,
    risk_level VARCHAR(20), -- High, Medium, Low
    should_expose BOOLEAN DEFAULT FALSE,
    encrypted_default BOOLEAN DEFAULT FALSE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Software associations
CREATE TABLE software (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    vendor VARCHAR(100),
    category VARCHAR(50),
    homepage_url TEXT,
    documentation_url TEXT,
    logo_url TEXT
);

CREATE TABLE port_software (
    id INTEGER REFERENCES ports(port_id),
    software_id INTEGER REFERENCES software(id),
    is_default BOOLEAN DEFAULT TRUE,
    since_version VARCHAR(20),
    until_version VARCHAR(20),
    notes TEXT,
    PRIMARY KEY (port_id, software_id)
);

-- Security data
CREATE TABLE port_security (
    id INTEGER PRIMARY KEY REFERENCES ports(id),
    shodan_count BIGINT,
    shodan_url TEXT,
    shodan_updated TIMESTAMP,
    censys_count BIGINT,
    censys_updated TIMESTAMP,
    cve_count INTEGER DEFAULT 0,
    latest_cve VARCHAR(20),
    security_notes TEXT
);

-- Configuration examples
CREATE TABLE port_configs (
    id SERIAL PRIMARY KEY,
    port_id INTEGER REFERENCES ports(id),
    platform VARCHAR(50), -- docker, k8s, iptables, ufw, windows
    config_type VARCHAR(50), -- firewall, expose, service
    config_snippet TEXT,
    description TEXT,
    verified BOOLEAN DEFAULT FALSE
);

-- Common issues from community
CREATE TABLE port_issues (
    id SERIAL PRIMARY KEY,
    port_id INTEGER REFERENCES ports(id),
    issue_title VARCHAR(200),
    symptoms TEXT,
    solution TEXT,
    source VARCHAR(50), -- stackoverflow, reddit, github
    source_url TEXT,
    upvotes INTEGER DEFAULT 0,
    verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Port relationships
CREATE TABLE port_relations (
    port_id INTEGER REFERENCES ports(port_id),
    related_port_id INTEGER REFERENCES ports(port_id),
    relation_type VARCHAR(50), -- alternative, companion, secure_version
    description TEXT,
    PRIMARY KEY (port_id, related_port_id)
);

-- Categories for browsing
CREATE TABLE categories (
    id SERIAL PRIMARY KEY,
    slug VARCHAR(50) UNIQUE,
    name VARCHAR(100),
    description TEXT,
    icon_url TEXT
);

CREATE TABLE port_categories (
    port_id INTEGER REFERENCES ports(id),
    category_id INTEGER REFERENCES categories(id),
    PRIMARY KEY (port_id, category_id)
);

-- Search optimization
CREATE INDEX idx_port_number ON ports(port_number);
CREATE INDEX idx_port_protocol ON ports(protocol);
CREATE INDEX idx_port_service ON ports(service_name);
CREATE INDEX idx_port_risk ON ports(risk_level);
CREATE INDEX idx_port_category ON port_categories(category_id);

-- Full-text search
ALTER TABLE ports ADD COLUMN search_vector tsvector;
UPDATE ports SET search_vector = 
    to_tsvector('english', 
        COALESCE(service_name, '') || ' ' || 
        COALESCE(service_description, '') || ' ' || 
        COALESCE(notes, '')
    );
CREATE INDEX idx_port_search ON ports USING GIN(search_vector);
```

### **4.4 Data Collection & Updates**

| Data Source | Update Frequency | Method | Priority |
|------------|-----------------|--------|----------|
| **IANA Registry** | Weekly | CSV download → diff → update | Critical |
| **Shodan API** | Daily | Top 1000 ports count | High |
| **Censys API** | Daily | Cross-verify exposure | Medium |
| **CVE Database** | Daily | NVD API for port mentions | High |
| **Stack Overflow** | Weekly | API search for port issues | Medium |
| **Reddit** | Weekly | PRAW for r/sysadmin, r/networking | Low |
| **GitHub Issues** | Weekly | Search for port problems | Low |
| **Docker Hub** | Weekly | EXPOSE directive scanning | Medium |
| **Vendor Docs** | Manual | RSS feeds + curation | High |

### **4.5 Automation Scripts**

```python
# update_iana_ports.py
import csv
import psycopg2
from datetime import datetime

def update_iana_ports():
    """Weekly IANA registry update"""
    # Download latest CSV
    response = requests.get(IANA_CSV_URL)
    
    # Parse and compare with existing
    new_ports = csv.DictReader(response.text)
    
    with psycopg2.connect(DATABASE_URL) as conn:
        cursor = conn.cursor()
        for port in new_ports:
            cursor.execute("""
                INSERT INTO ports (port_number, protocol, service_name, iana_status)
                VALUES (%s, %s, %s, %s)
                ON CONFLICT (port_number, protocol) 
                DO UPDATE SET 
                    service_name = EXCLUDED.service_name,
                    iana_updated = NOW()
            """, (port['number'], port['protocol'], port['name'], port['status']))

# update_shodan_telemetry.py
def update_shodan_counts():
    """Nightly Shodan exposure update"""
    api = shodan.Shodan(SHODAN_API_KEY)
    
    important_ports = [21, 22, 23, 25, 80, 443, 3306, 3389, 5432, 6379, 27017]
    
    for port in important_ports:
        result = api.search(f'port:{port}')
        update_port_security(port, result['total'])
```

### **4.6 SEO & Structured Data**

```json
{
  "@context": "https://schema.org",
  "@type": "TechArticle",
  "headline": "Port 3306 (TCP) - MySQL Database Server",
  "description": "Complete reference for network port 3306...",
  "keywords": "port 3306, MySQL port, MariaDB port, database port",
  "author": {
    "@type": "Organization",
    "name": "TechReference"
  },
  "datePublished": "2025-01-01",
  "dateModified": "2025-10-22",
  "mainEntity": {
    "@type": "FAQPage",
    "mainEntity": [
      {
        "@type": "Question",
        "name": "What is port 3306 used for?",
        "acceptedAnswer": {
          "@type": "Answer",
          "text": "Port 3306 is the default port for MySQL..."
        }
      }
    ]
  }
}
```

### **4.7 Port Categories & Filters**

| Category | URL Slug | Example Ports |
|----------|----------|---------------|
| Web Services | `/ports/web` | 80, 443, 8080, 8443 |
| Databases | `/ports/database` | 3306, 5432, 27017, 6379 |
| Email | `/ports/email` | 25, 110, 143, 465, 587, 993, 995 |
| Gaming | `/ports/gaming` | 25565, 27015, 3074 |
| Remote Access | `/ports/remote` | 22, 23, 3389, 5900 |
| File Transfer | `/ports/file-transfer` | 20, 21, 69, 445 |
| VoIP/Streaming | `/ports/streaming` | 5060, 1935, 554 |
| Development | `/ports/development` | 3000, 4200, 8000, 9000 |
| System | `/ports/system` | 53, 67, 68, 123, 161 |
| Security | `/ports/security` | 88, 389, 636 |

### **4.8 Interactive Tools**

1. **Port Checker Tool** (`/tools/port-checker`)
   - Check if port is open from browser
   - WebRTC for local network scan
   - No server-side component needed

2. **Port Conflict Resolver** (`/tools/port-conflicts`)
   - Enter port number
   - Shows all software that might use it
   - Platform-specific solutions

3. **Firewall Rule Generator** (`/tools/firewall-generator`)
   - Select port, direction, source
   - Generate rules for all platforms
   - Copy-to-clipboard functionality

### **4.9 API Endpoints**

```javascript
// Public API (rate limited)
GET /api/v1/ports/{number}
GET /api/v1/ports/{number}/software
GET /api/v1/ports/{number}/issues
GET /api/v1/ports/search?q=mysql
GET /api/v1/ports/category/{slug}

// Webhook for updates
POST /api/v1/webhooks/port-updates
{
  "port": 3306,
  "changes": ["shodan_count", "new_cve"]
}
```

---

## **5. Technical Implementation**

### **5.1 Technology Stack**
- **Backend**: Laravel 11 (PHP 8.3)
- **Database**: PostgreSQL 16
- **Cache**: Redis
- **Search**: PostgreSQL Full-Text + Meilisearch
- **Queue**: Laravel Horizon
- **Hosting**: DigitalOcean / Hetzner
- **CDN**: Cloudflare

### **5.2 Performance Requirements**
- Page load: <1s (cached), <3s (uncached)
- API response: <100ms
- Search results: <200ms
- Database queries: <50ms with indexes

---

## **6. Development Phases**

### **Phase 1: Ports Module** (Month 1-2)
- [ ] Port page template
- [ ] IANA import system
- [ ] Shodan integration
- [ ] Basic search
- [ ] 1,000 priority ports

### **Phase 2: Error Codes** (Month 2-3)
- [ ] Error page template
- [ ] Windows/Mac/Linux errors
- [ ] Video embedding system
- [ ] User solutions

### **Phase 3: File Extensions** (Month 3-4)
- [ ] Extension pages
- [ ] Conversion paths
- [ ] Security warnings
- [ ] Software affiliates

### **Phase 4: HTTP & Integration** (Month 4-5)
- [ ] HTTP status codes
- [ ] MIME types
- [ ] Cross-linking system
- [ ] API launch

---

## **7. Success Metrics**

| Metric | 3 Months | 6 Months | 12 Months |
|--------|----------|----------|-----------|
| Indexed Pages | 5,000 | 25,000 | 75,000 |
| Monthly Traffic | 10K | 100K | 500K |
| API Users | 10 | 100 | 500 |
| Email Subscribers | 500 | 2,500 | 10,000 |
| Revenue | $100 | $1,000 | $5,000 |

---

## **8. Next Steps**

1. **Immediate**: Finalize database schema for ports
2. **Week 1**: Build port page template with Laravel
3. **Week 2**: Implement IANA import pipeline
4. **Week 3**: Add Shodan/security data
5. **Week 4**: Launch with 1,000 ports
6. **Ongoing**: Add remaining ports, begin error codes module

---

*This specification is a living document. Each module will receive its own detailed specification as development progresses.*