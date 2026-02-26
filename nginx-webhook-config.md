# Nginx Configuration untuk Auto Deploy Webhook

## Basic Configuration

Tambahkan di nginx config untuk handle webhook endpoint:

```nginx
server {
    listen 443 ssl http2;
    server_name api.globalintermedia.online;
    
    root /var/www/api.globalintermedia.online/public;
    index index.php index.html;
    
    # SSL certificates
    ssl_certificate /path/to/ssl/cert.pem;
    ssl_certificate_key /path/to/ssl/key.pem;
    
    # Normal Laravel routes
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    # Webhook endpoint with security
    location = /deploy-webhook.php {
        # Optional: Whitelist GitHub/GitLab IPs only
        # Uncomment jika ingin extra security
        # allow 140.82.112.0/20;  # GitHub
        # allow 143.55.64.0/20;   # GitHub
        # allow 185.199.108.0/22; # GitHub Pages
        # allow 34.74.90.64/28;   # GitLab
        # deny all;
        
        # Rate limiting untuk webhook
        limit_req zone=webhook burst=5 nodelay;
        
        # Pass to PHP-FPM
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        
        # Important headers untuk Laravel detect HTTPS
        fastcgi_param HTTP_X_FORWARDED_PROTO https;
        fastcgi_param HTTP_X_FORWARDED_PORT 443;
        fastcgi_param HTTPS on;
        
        include fastcgi_params;
        
        # Security headers
        add_header X-Frame-Options "DENY" always;
        add_header X-Content-Type-Options "nosniff" always;
        
        # Timeout settings (deployment bisa lama)
        fastcgi_read_timeout 300;
        fastcgi_send_timeout 300;
    }
    
    # Standard PHP handler untuk Laravel
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        
        fastcgi_param HTTP_X_FORWARDED_PROTO https;
        fastcgi_param HTTP_X_FORWARDED_PORT 443;
        fastcgi_param HTTPS on;
        
        include fastcgi_params;
    }
    
    # Deny access to hidden files
    location ~ /\.(?!well-known).* {
        deny all;
    }
}

# Redirect HTTP to HTTPS
server {
    listen 80;
    server_name api.globalintermedia.online;
    return 301 https://$server_name$request_uri;
}
```

## Rate Limiting Configuration

Tambahkan di bagian `http {}` block (biasanya di `/etc/nginx/nginx.conf`):

```nginx
http {
    # ... other configs ...
    
    # Rate limiting untuk webhook endpoint
    limit_req_zone $binary_remote_addr zone=webhook:10m rate=10r/m;
    
    # ... rest of config ...
}
```

**Penjelasan:** Max 10 requests per menit per IP untuk webhook endpoint.

## IP Whitelist (Optional - Extra Security)

### GitHub Webhook IPs

```nginx
location = /deploy-webhook.php {
    # GitHub webhook IPs (update berkala)
    allow 140.82.112.0/20;
    allow 143.55.64.0/20;
    allow 185.199.108.0/22;
    allow 192.30.252.0/22;
    deny all;
    
    # ... rest of config ...
}
```

### GitLab Webhook IPs

```nginx
location = /deploy-webhook.php {
    # GitLab webhook IPs
    allow 34.74.90.64/28;
    allow 34.74.226.0/24;
    deny all;
    
    # ... rest of config ...
}
```

⚠️ **Warning:** IP ranges bisa berubah. Check:
- GitHub: https://api.github.com/meta
- GitLab: https://docs.gitlab.com/ee/user/gitlab_com/

## Testing Configuration

```bash
# Test nginx config
sudo nginx -t

# Reload nginx
sudo systemctl reload nginx

# Test webhook endpoint
curl -I https://api.globalintermedia.online/deploy-webhook.php
```

## Monitoring Nginx Access

```bash
# Watch nginx access log untuk webhook
sudo tail -f /var/log/nginx/access.log | grep deploy-webhook

# Watch nginx error log
sudo tail -f /var/log/nginx/error.log
```

## Complete Example dengan Security Headers

```nginx
server {
    listen 443 ssl http2;
    server_name api.globalintermedia.online;
    
    root /var/www/api.globalintermedia.online/public;
    index index.php index.html;
    
    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/api.globalintermedia.online/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.globalintermedia.online/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    
    # Security Headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    
    # Logging
    access_log /var/log/nginx/api.globalintermedia.online-access.log;
    error_log /var/log/nginx/api.globalintermedia.online-error.log;
    
    # Webhook endpoint
    location = /deploy-webhook.php {
        limit_req zone=webhook burst=5 nodelay;
        
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param HTTP_X_FORWARDED_PROTO https;
        fastcgi_param HTTP_X_FORWARDED_PORT 443;
        fastcgi_param HTTPS on;
        include fastcgi_params;
        
        fastcgi_read_timeout 300;
        fastcgi_send_timeout 300;
    }
    
    # Laravel routes
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    # PHP handler
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param HTTP_X_FORWARDED_PROTO https;
        fastcgi_param HTTP_X_FORWARDED_PORT 443;
        fastcgi_param HTTPS on;
        include fastcgi_params;
    }
    
    # Deny access to hidden files except .well-known
    location ~ /\.(?!well-known).* {
        deny all;
    }
    
    # Deny access to sensitive files
    location ~* (\.env|\.git|composer\.json|composer\.lock|package\.json|yarn\.lock)$ {
        deny all;
    }
}

# HTTP to HTTPS redirect
server {
    listen 80;
    server_name api.globalintermedia.online;
    return 301 https://$server_name$request_uri;
}
```

## Apply Configuration

```bash
# 1. Edit nginx config
sudo nano /etc/nginx/sites-available/api.globalintermedia.online

# 2. Test configuration
sudo nginx -t

# 3. Jika OK, reload nginx
sudo systemctl reload nginx

# 4. Check status
sudo systemctl status nginx

# 5. Test webhook
curl -I https://api.globalintermedia.online/deploy-webhook.php
```

## Troubleshooting

### 502 Bad Gateway
```bash
# Check PHP-FPM status
sudo systemctl status php8.2-fpm

# Check socket file exists
ls -la /var/run/php/php8.2-fpm.sock

# Restart PHP-FPM
sudo systemctl restart php8.2-fpm
```

### 404 Not Found
```bash
# Check root path
# Make sure root points to /public directory

# Check file exists
ls -la /var/www/api.globalintermedia.online/public/deploy-webhook.php
```

### 403 Forbidden
```bash
# Check file permissions
sudo chown -R www-data:www-data /var/www/api.globalintermedia.online/public
sudo chmod 644 /var/www/api.globalintermedia.online/public/deploy-webhook.php
```

---

**Location:** `/etc/nginx/sites-available/api.globalintermedia.online`  
**Symlink:** `/etc/nginx/sites-enabled/api.globalintermedia.online`
