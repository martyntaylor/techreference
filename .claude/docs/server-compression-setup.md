# Server Compression Setup Guide

Response compression (gzip/brotli) is configured at the web server level for optimal performance. Laravel can handle compression via PHP, but server-level compression is significantly more efficient.

## Why Server-Level Compression?

**Advantages:**
- **10-50x faster** than PHP compression
- Lower CPU usage (optimized C code vs PHP)
- Automatic caching of compressed responses
- Native support for Vary headers
- Better streaming support

**Compression Ratios:**
- HTML/CSS/JS: 70-80% reduction (100KB → 20-30KB)
- JSON/XML: 60-70% reduction
- Images (already compressed): 0-5% reduction

---

## Nginx Configuration

### Basic Gzip Setup

Add to your Nginx site config (`/etc/nginx/sites-available/techreference.test`):

```nginx
server {
    listen 80;
    server_name techreference.test;
    root /var/www/techreference/public;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;  # 1-9, higher = more compression but slower
    gzip_types
        text/plain
        text/css
        text/xml
        text/javascript
        application/json
        application/javascript
        application/xml+rss
        application/rss+xml
        font/truetype
        font/opentype
        application/vnd.ms-fontobject
        image/svg+xml;
    gzip_disable "msie6";
    gzip_min_length 256;  # Don't compress files < 256 bytes

    # Brotli compression (if ngx_brotli module installed)
    brotli on;
    brotli_comp_level 6;  # 0-11
    brotli_types
        text/plain
        text/css
        text/xml
        text/javascript
        application/json
        application/javascript
        application/xml+rss
        application/rss+xml
        font/truetype
        font/opentype
        application/vnd.ms-fontobject
        image/svg+xml;
    brotli_static on;  # Use pre-compressed .br files if available

    # ... rest of your config
}
```

### Advanced Configuration

```nginx
# Create compressed static files at build time
location ~* \.(css|js)$ {
    # Try pre-compressed brotli first, then gzip, then original
    gzip_static on;
    brotli_static on;

    # Cache static assets
    expires 1y;
    add_header Cache-Control "public, immutable";
}

# Compression for dynamic content
location ~ \.php$ {
    # PHP-FPM config
    fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    include fastcgi_params;

    # Buffer settings for compression
    fastcgi_buffer_size 128k;
    fastcgi_buffers 4 256k;
    fastcgi_busy_buffers_size 256k;
}
```

### Testing Nginx Config

```bash
# Test config syntax
sudo nginx -t

# Reload Nginx
sudo systemctl reload nginx

# Check if gzip is working
curl -H "Accept-Encoding: gzip" -I http://techreference.test
# Should see: Content-Encoding: gzip

# Check if brotli is working
curl -H "Accept-Encoding: br" -I http://techreference.test
# Should see: Content-Encoding: br
```

---

## Apache Configuration

### Enable Modules

```bash
# Enable compression modules
sudo a2enmod deflate
sudo a2enmod headers
sudo a2enmod filter
sudo a2enmod brotli  # If available

# Restart Apache
sudo systemctl restart apache2
```

### .htaccess Configuration

Add to your `.htaccess` or Apache config:

```apache
<IfModule mod_deflate.c>
    # Compress HTML, CSS, JavaScript, Text, XML and fonts
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/json
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/vnd.ms-fontobject
    AddOutputFilterByType DEFLATE application/x-font
    AddOutputFilterByType DEFLATE application/x-font-opentype
    AddOutputFilterByType DEFLATE application/x-font-otf
    AddOutputFilterByType DEFLATE application/x-font-truetype
    AddOutputFilterByType DEFLATE application/x-font-ttf
    AddOutputFilterByType DEFLATE application/x-javascript
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE font/opentype
    AddOutputFilterByType DEFLATE font/otf
    AddOutputFilterByType DEFLATE font/ttf
    AddOutputFilterByType DEFLATE image/svg+xml
    AddOutputFilterByType DEFLATE image/x-icon
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/javascript
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/xml

    # Remove browser bugs (only needed for really old browsers)
    BrowserMatch ^Mozilla/4 gzip-only-text/html
    BrowserMatch ^Mozilla/4\.0[678] no-gzip
    BrowserMatch \bMSIE !no-gzip !gzip-only-text/html
    Header append Vary User-Agent
</IfModule>

<IfModule mod_brotli.c>
    # Brotli compression
    AddOutputFilterByType BROTLI_COMPRESS text/html text/plain text/xml text/css
    AddOutputFilterByType BROTLI_COMPRESS text/javascript application/javascript application/json
    AddOutputFilterByType BROTLI_COMPRESS application/xml+rss application/vnd.ms-fontobject
    AddOutputFilterByType BROTLI_COMPRESS font/truetype font/opentype image/svg+xml
</IfModule>
```

### Testing Apache Config

```bash
# Test config
sudo apache2ctl configtest

# Restart Apache
sudo systemctl restart apache2

# Test compression
curl -H "Accept-Encoding: gzip" -I http://techreference.test
```

---

## Valet Configuration (Local Development)

Laravel Valet uses Nginx. Edit your site config:

```bash
# Edit Nginx config
valet edit techreference.test

# Add gzip settings (see Nginx section above)
# Then restart Valet
valet restart
```

Or for global compression:

```bash
# Edit global Nginx config
sudo nano /opt/homebrew/etc/nginx/nginx.conf

# Add gzip settings inside http block
# Restart Valet
valet restart
```

---

## Pre-Compression at Build Time

Generate pre-compressed files during deployment for even better performance:

### package.json

```json
{
  "scripts": {
    "build": "vite build",
    "compress": "npm run compress:gzip && npm run compress:brotli",
    "compress:gzip": "gzip -9 -k -r -f public/build/assets/*.js public/build/assets/*.css",
    "compress:brotli": "brotli -9 -f public/build/assets/*.js public/build/assets/*.css"
  }
}
```

### Deployment Script

```bash
#!/bin/bash

# Build assets
npm run build

# Compress static files
find public/build/assets -type f \( -name "*.js" -o -name "*.css" \) -exec gzip -9 -k {} \;
find public/build/assets -type f \( -name "*.js" -o -name "*.css" \) -exec brotli -9 {} \;

# Result:
# public/build/assets/app.abc123.js
# public/build/assets/app.abc123.js.gz
# public/build/assets/app.abc123.js.br
```

With `gzip_static on` or `brotli_static on`, Nginx will serve `.gz` or `.br` files automatically if they exist.

---

## Cloudflare (CDN with Automatic Compression)

If using Cloudflare, compression is automatic:

1. **Free Plan:** Gzip only
2. **Pro Plan:** Gzip + Brotli

### Enable in Cloudflare Dashboard

1. Go to **Speed** → **Optimization**
2. Enable **Auto Minify** (HTML, CSS, JS)
3. Enable **Brotli** (Pro plan)
4. Enable **Rocket Loader** (optional, defers JS)

### Cloudflare Workers (Advanced)

For custom compression logic:

```javascript
addEventListener('fetch', event => {
  event.respondWith(handleRequest(event.request))
})

async function handleRequest(request) {
  const response = await fetch(request)
  const acceptEncoding = request.headers.get('accept-encoding') || ''

  // Cloudflare automatically handles compression
  // This is just for custom logic
  return response
}
```

---

## Testing Compression

### Command Line

```bash
# Check if gzip is enabled
curl -H "Accept-Encoding: gzip" -I https://techreference.io | grep -i "content-encoding"

# Check if brotli is enabled
curl -H "Accept-Encoding: br" -I https://techreference.io | grep -i "content-encoding"

# See actual size reduction
curl https://techreference.io -o /dev/null -s -w 'Size: %{size_download} bytes\n'
curl -H "Accept-Encoding: gzip" https://techreference.io -o /dev/null -s -w 'Compressed: %{size_download} bytes\n'

# Detailed output
curl -H "Accept-Encoding: gzip" -v https://techreference.io > /dev/null
```

### Browser Dev Tools

1. Open **Chrome DevTools** → **Network** tab
2. Reload page
3. Look for **Size** column: "123 KB / 45.6 KB" (45.6 KB is compressed)
4. Click on a request → **Headers** tab → Look for `content-encoding: gzip` or `content-encoding: br`

### Online Tools

- [GTmetrix](https://gtmetrix.com/) - Shows compression status
- [WebPageTest](https://www.webpagetest.org/) - Detailed compression analysis
- [GiftOfSpeed](https://www.giftofspeed.com/gzip-test/) - Quick gzip test

---

## Monitoring Compression Performance

### Nginx Access Log Format

```nginx
log_format compression '$remote_addr - $remote_user [$time_local] '
                       '"$request" $status $body_bytes_sent '
                       '"$http_referer" "$http_user_agent" '
                       'gzip_ratio:$gzip_ratio';

access_log /var/log/nginx/access.log compression;
```

### Apache Access Log

```apache
LogFormat "%h %l %u %t \"%r\" %>s %b \"%{Referer}i\" \"%{User-Agent}i\" %{ratio}n%%" compression
CustomLog ${APACHE_LOG_DIR}/access.log compression
```

---

## Troubleshooting

### Compression Not Working?

**Check 1:** Is the module enabled?
```bash
# Nginx
nginx -V 2>&1 | grep -o with-http_gzip_static_module

# Apache
apache2ctl -M | grep deflate
```

**Check 2:** Is the response large enough?
- Most servers have a minimum size (256-1024 bytes)
- Small responses won't be compressed

**Check 3:** Is the Content-Type compressible?
- Check your `gzip_types` or `AddOutputFilterByType` config

**Check 4:** Check browser request headers
```bash
curl -v https://techreference.io 2>&1 | grep -i accept-encoding
# Should see: accept-encoding: gzip, deflate, br
```

**Check 5:** Conflicting middleware?
- Disable AddETagHeader temporarily to test
- Check for double compression (PHP + Server)

---

## Performance Impact

**Before Compression:**
- HTML: 150 KB
- CSS: 200 KB
- JS: 500 KB
- **Total: 850 KB**

**After Gzip (Level 6):**
- HTML: 30 KB (80% reduction)
- CSS: 40 KB (80% reduction)
- JS: 125 KB (75% reduction)
- **Total: 195 KB (77% reduction)**

**After Brotli (Level 6):**
- HTML: 25 KB (83% reduction)
- CSS: 35 KB (82.5% reduction)
- JS: 110 KB (78% reduction)
- **Total: 170 KB (80% reduction)**

**Time Savings:**
- On 10 Mbps connection: 850 KB → 195 KB = **5.2 seconds faster**
- On 4G connection: 850 KB → 195 KB = **4.1 seconds faster**

---

## Deployment Checklist

- [ ] Enable gzip in Nginx/Apache
- [ ] Enable brotli if available
- [ ] Configure compression types (text/html, text/css, application/javascript, etc.)
- [ ] Set appropriate compression level (5-6 recommended)
- [ ] Test compression with curl
- [ ] Verify in browser dev tools
- [ ] Add pre-compression to build script
- [ ] Monitor compression ratio in logs
- [ ] Configure Cloudflare if using CDN

---

## Further Reading

- [Google Web Fundamentals - Text Compression](https://developers.google.com/web/fundamentals/performance/optimizing-content-efficiency/optimize-encoding-and-transfer)
- [Nginx Compression Module Docs](http://nginx.org/en/docs/http/ngx_http_gzip_module.html)
- [Brotli vs Gzip Comparison](https://paulcalvano.com/2018-07-25-brotli-compression-how-much-will-it-reduce-your-content/)
