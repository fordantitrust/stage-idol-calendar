# 🐳 Docker Deployment Guide - Idol Stage Timetable

Complete guide for deploying the application using Docker and Docker Compose.

---

## 📑 Table of Contents

- [Quick Start](#-quick-start)
- [Prerequisites](#-prerequisites)
- [Production Deployment](#-production-deployment)
- [Development Setup](#-development-setup)
- [Configuration](#-configuration)
- [Troubleshooting](#-troubleshooting)
- [Advanced Usage](#-advanced-usage)

---

## 🚀 Quick Start

### 1. Install Docker

**Windows/Mac**: Download [Docker Desktop](https://www.docker.com/products/docker-desktop)

**Linux**:
```bash
# Ubuntu/Debian
sudo apt-get update
sudo apt-get install docker.io docker-compose

# Start Docker service
sudo systemctl start docker
sudo systemctl enable docker
```

### 2. Deploy Application

```bash
# Clone/navigate to project
cd stage-idol-calendar

# Build and start container
docker-compose up -d

# Check status
docker-compose ps

# View logs
docker-compose logs -f
```

### 3. Access Application

Open browser: **http://localhost:8000**

That's it! 🎉

---

## 🔧 Prerequisites

### System Requirements

- **Docker**: 20.10+ (with Docker Compose V2)
- **Disk Space**: ~500 MB for image + your data
- **RAM**: 256 MB minimum
- **Network**: Internet connection for initial image pull

### What the image includes

The provided `Dockerfile` builds on **`php:8.1-apache`** and installs everything the app needs out of the box:

- **PDO SQLite** + `mod_rewrite` (clean URLs)
- **GD + FreeType** — required for server-side PNG export (`image.php`)
- **Fonts** — `fonts-thai-tlwg` (Thai), `fonts-noto-cjk` (Japanese/CJK), `fonts-unifont` (symbol fallback), so exported images render Thai/Japanese/symbol glyphs correctly with no extra setup
- Pre-created writable dirs: `cache/{images,favorites,logs}`, `uploads/{site,artists,events}`, `ics`, `backups`

### Verify Installation

```bash
# Check Docker version
docker --version

# Check Docker Compose version
docker-compose --version

# Test Docker (should print "Hello from Docker!")
docker run hello-world
```

---

## 📦 Production Deployment

### Method 1: Using docker-compose.yml (Recommended)

```bash
# 1. Prepare host directories (persisted via bind mounts) + ICS files
mkdir -p ics data cache config uploads backups
cp your-events.ics ics/

# 2. Build and start
docker-compose up -d

# 3. Initialize database (recommended: use Setup Wizard)
# Open http://localhost:8000/setup.php and follow the 6-step wizard
# It creates all tables, imports data, and sets admin credentials

# --- OR initialize manually ---
docker exec idol-stage-calendar php tools/import-ics-to-sqlite.php
# Then run all migrations (see README.md — Option B: Manual CLI)

# 4. Verify
curl http://localhost:8000
```

### Method 2: Using Docker directly

```bash
# Build image
docker build -t idol-stage-calendar .

# Run container
docker run -d \
  --name idol-stage-calendar \
  -p 8000:80 \
  -v $(pwd)/ics:/var/www/html/ics:ro \
  -v $(pwd)/cache:/var/www/html/cache \
  -v $(pwd)/data:/var/www/html/data \
  -v $(pwd)/config:/var/www/html/config \
  -v $(pwd)/uploads:/var/www/html/uploads \
  -v $(pwd)/backups:/var/www/html/backups \
  idol-stage-calendar

# Check logs
docker logs -f idol-stage-calendar
```

### Accessing the Application

- **Main Page**: http://localhost:8000
- **Admin Panel**: http://localhost:8000/admin/
- **API**: http://localhost:8000/api.php

---

## 💻 Development Setup

For development with live code reload:

```bash
# Use development compose file
docker-compose -f docker-compose.dev.yml up

# Or override with bind mount
docker-compose up --build
```

**Features**:
- Live code reload (no rebuild needed)
- PHP error display enabled
- Increased memory limit
- Full project mounted as volume

### Run Tests in Container

> **Note:** `.dockerignore` excludes `tests/` from the **production** image, so `php tests/run-tests.php` only works when the full project is bind-mounted — i.e. the development compose file (`docker-compose.dev.yml`) or `docker-compose up --build` with a `.:/var/www/html` mount. In a plain production image the `tests/` directory is absent.

```bash
# Run all tests (dev container with full project mounted) — 27 suites, 13,231 tests
# Note: docker-compose.dev.yml names the container idol-stage-calendar-dev
docker exec idol-stage-calendar-dev php tests/run-tests.php

# Run specific test suite
docker exec idol-stage-calendar-dev php tests/run-tests.php SecurityTest

# Run quick tests
docker exec idol-stage-calendar-dev sh quick-test.sh
```

---

## ⚙️ Configuration

### Environment Variables

Edit `docker-compose.yml` to customize:

```yaml
environment:
  # PHP Settings
  - PHP_MEMORY_LIMIT=512M          # Increase if needed
  - PHP_UPLOAD_MAX_FILESIZE=20M    # For ICS uploads
  - PHP_POST_MAX_SIZE=20M

  # Timezone
  - TZ=Asia/Bangkok                # Change to your timezone

  # Production mode
  - PRODUCTION_MODE=true
```

### Port Configuration

Change exposed port in `docker-compose.yml`:

```yaml
ports:
  - "8080:80"  # Access via http://localhost:8080
```

### Volume Mounts

**Persistent Data**:
```yaml
volumes:
  # ICS files (read-only)
  - ./ics:/var/www/html/ics:ro

  # Cache (read-write) — also holds favorites/, ratelimit/, logs/, images/
  - ./cache:/var/www/html/cache

  # Database directory (read-write) — contains calendar.db + .setup_locked
  - ./data:/var/www/html/data

  # Config (read-write) — persists Admin UI runtime settings:
  # google-config.json, telegram-config.json, email-config.json,
  # webpush-config.json, favorites-config.json
  - ./config:/var/www/html/config

  # Uploaded images (read-write) — artist/event/site covers + galleries.
  # Without this mount, uploaded images are LOST on every rebuild/recreate.
  - ./uploads:/var/www/html/uploads

  # Database backups created via Admin → Settings → Backup
  - ./backups:/var/www/html/backups
```

> **Important:** The default `docker-compose.yml` mounts `ics`, `cache`, `data`, and `config`. Add `./uploads` and `./backups` if you use the image-upload features (artist/event pictures, site covers) or in-app backups — otherwise those files live only inside the container and disappear when it is rebuilt.

---

### Email Notifications in Docker *(v9.6.0+)*

Admin › Settings › Email writes SMTP settings to `config/email-config.json`, so Docker deployments should keep `./config:/var/www/html/config` mounted read-write. Without that mount, settings saved in the Admin UI may be lost when the container is rebuilt or recreated.

Operational notes:
- Docker does not need inbound mail ports exposed. The container only needs outbound network access to your SMTP host.
- Prefer SMTP ports `587` with TLS or `465` with SSL; many hosts block outbound port `25`.
- Delivery logs are written to `cache/logs/email.log`, so keep `./cache:/var/www/html/cache` mounted.
- `config/*-config.json` is excluded by `.dockerignore` to avoid baking SMTP, Telegram, or Google credentials into the Docker image.
- If the Admin UI cannot save Email settings, fix host permissions for `config/` so the container user can write JSON files.

```bash
mkdir -p config cache/logs
docker-compose up -d --build
```

---

### Background Jobs (Cron) in Docker *(Telegram v5.0.0+, Web Push v15.0.0+)*

The base image runs Apache only — it has **no cron daemon**. If you use Telegram or Web Push notifications, schedule the `cron/` scripts from the **host** crontab via `docker exec`. All scripts are CLI-only (HTTP blocked by `cron/.htaccess`).

```cron
# Notifications (run frequently — interval depends on your notify-before window)
*/5  * * * *  docker exec idol-stage-calendar php cron/send-telegram-notifications.php   >> /var/log/idol-telegram.log 2>&1
*/5  * * * *  docker exec idol-stage-calendar php cron/send-web-push-notifications.php    >> /var/log/idol-webpush.log 2>&1

# Daily log rotation (7-day retention) + audit-log cleanup
0 0 * * *  docker exec idol-stage-calendar php cron/rotate-telegram-logs.php
0 0 * * *  docker exec idol-stage-calendar php cron/rotate-webpush-logs.php
0 0 * * *  docker exec idol-stage-calendar php cron/rotate-email-logs.php
0 0 * * *  docker exec idol-stage-calendar php cron/rotate-admin-audit-logs.php
```

Notes:
- For **Web Push**, set **Site URL** (`WEBPUSH_SITE_URL`) and generate VAPID keys via Admin › Settings › Web Push so notification links and the icon/badge resolve to the public origin.
- For **Telegram**, register the webhook after the container is reachable over HTTPS (Admin › Settings › Telegram, then `php tools/setup-telegram-webhook.php`).
- Keep `./cache:/var/www/html/cache` mounted so notification logs and the favorites/subscription state persist.

---

## 🔍 Troubleshooting

### Container Won't Start

```bash
# Check container status
docker-compose ps

# View logs
docker-compose logs web

# Restart container
docker-compose restart

# Rebuild from scratch
docker-compose down
docker-compose up --build -d
```

### Permission Issues

```bash
# Fix cache permissions
docker exec idol-stage-calendar chmod -R 777 /var/www/html/cache

# Fix database permissions
docker exec idol-stage-calendar chmod 666 /var/www/html/data/calendar.db

# Fix runtime config permissions for Admin UI JSON settings
docker exec idol-stage-calendar chown -R www-data:www-data /var/www/html/config
```

### Database Not Found

```bash
# Run setup wizard (recommended) or import ICS files manually
docker exec idol-stage-calendar php tools/import-ics-to-sqlite.php

# Verify database
docker exec idol-stage-calendar ls -la data/calendar.db
```

### Access Shell Inside Container

```bash
# Enter container shell
docker exec -it idol-stage-calendar bash

# Then run commands
cd tools
php import-ics-to-sqlite.php
exit
```

### View Real-Time Logs

```bash
# All logs
docker-compose logs -f

# Web service only
docker-compose logs -f web

# Last 100 lines
docker-compose logs --tail=100 web
```

---

## 🔨 Advanced Usage

### Multi-Stage Build (Optimized)

Create `Dockerfile.production`:

```dockerfile
# Stage 1: Builder
FROM php:8.1-apache AS builder

RUN apt-get update && apt-get install -y \
    libsqlite3-dev libfreetype6-dev libjpeg62-turbo-dev libpng-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_sqlite gd

WORKDIR /app
COPY . /app/

# Initialize the database. Prefer the Setup Wizard at runtime, or build the full
# schema here via setup.php's "Run All Migrations" path / the tools/ migrations
# (see README.md — Option B: Manual CLI for the complete, current sequence).
RUN cd tools && php import-ics-to-sqlite.php || true

# Stage 2: Runtime
FROM php:8.1-apache

RUN apt-get update && apt-get install -y \
    libfreetype6-dev libjpeg62-turbo-dev libpng-dev \
    fonts-thai-tlwg fonts-noto-cjk fonts-unifont \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_sqlite gd \
    && a2enmod rewrite && fc-cache -fv \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=builder /app /var/www/html/
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
CMD ["apache2-foreground"]
```

> The default `Dockerfile` already does all of this in a single stage. Use a multi-stage build only if you want to keep build-time tooling out of the final image.

Build:
```bash
docker build -f Dockerfile.production -t idol-stage-calendar:prod .
```

### Using Nginx Instead of Apache

Create `Dockerfile.nginx`:

```dockerfile
FROM php:8.2-fpm

RUN docker-php-ext-install pdo pdo_sqlite

COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

EXPOSE 9000
CMD ["php-fpm"]
```

And `nginx.conf`:

```nginx
server {
    listen 80;
    root /var/www/html;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass php:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

### Docker Compose with Nginx

```yaml
version: '3.8'

services:
  php:
    build:
      context: .
      dockerfile: Dockerfile.nginx
    volumes:
      - .:/var/www/html
    networks:
      - app-network

  nginx:
    image: nginx:alpine
    ports:
      - "8000:80"
    volumes:
      - .:/var/www/html
      - ./nginx.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - php
    networks:
      - app-network

networks:
  app-network:
    driver: bridge
```

### CI/CD with Docker

**GitHub Actions** (`.github/workflows/docker.yml`):

```yaml
name: Docker Build and Push

on:
  push:
    branches: [main, master]
    tags: ['v*']

jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      # Run the test suite against the source checkout, not the built image —
      # tests/ is excluded by .dockerignore so it is NOT inside the production image.
      - name: Run tests (on source)
        run: docker run --rm -v "$PWD":/app -w /app php:8.1-cli php tests/run-tests.php

      - name: Build Docker image
        run: docker build -t idol-stage-calendar:latest .

      - name: Smoke-test image
        run: |
          docker run -d --name test -p 8000:80 idol-stage-calendar:latest
          sleep 5
          curl -f http://localhost:8000/ || (docker logs test && exit 1)
          docker stop test

      # Optional: Push to Docker Hub
      # - name: Push to Docker Hub
      #   run: |
      #     echo ${{ secrets.DOCKER_PASSWORD }} | docker login -u ${{ secrets.DOCKER_USERNAME }} --password-stdin
      #     docker push idol-stage-calendar:latest
```

---

## 🎯 Best Practices

### 1. Use Named Volumes for Data

```yaml
volumes:
  - calendar-data:/var/www/html/data
  - cache-data:/var/www/html/cache

volumes:
  calendar-data:
  cache-data:
```

### 2. Enable Health Checks

```yaml
healthcheck:
  test: ["CMD", "curl", "-f", "http://localhost/"]
  interval: 30s
  timeout: 3s
  retries: 3
  start_period: 5s
```

### 3. Set Resource Limits

```yaml
deploy:
  resources:
    limits:
      cpus: '0.5'
      memory: 512M
    reservations:
      cpus: '0.25'
      memory: 256M
```

### 4. Use Docker Secrets

```yaml
secrets:
  admin_password:
    file: ./secrets/admin_password.txt

services:
  web:
    secrets:
      - admin_password
```

For v9.6.0 email notifications, do not bake `config/email-config.json` into custom production images. Keep it as a mounted runtime file or manage it with your platform's secret/config mechanism.

---

## 📊 Maintenance

### Backup Database

```bash
# Copy database from container
docker cp idol-stage-calendar:/var/www/html/data/calendar.db ./backup-$(date +%Y%m%d).db

# With the default bind mounts, just archive the host directories directly
tar czf backup-$(date +%Y%m%d).tar.gz data/ config/ uploads/ ics/
```

> The default `docker-compose.yml` uses **bind mounts** (`./data`, `./config`, `./uploads`, …), so your data already lives on the host — back up those folders directly. (The `calendar-data` / `cache-data` named volumes declared at the bottom of the compose file are not attached to the service by default.)

### Update Container

```bash
# Pull latest code
git pull

# Rebuild and restart
docker-compose down
docker-compose up --build -d

# Or with zero downtime
docker-compose up -d --no-deps --build web
```

### Clean Up

```bash
# Stop and remove containers
docker-compose down

# Remove volumes (CAUTION: deletes data!)
docker-compose down -v

# Remove unused images
docker image prune -a

# Remove everything
docker system prune -a --volumes
```

---

## 🌐 Production Deployment Examples

### With Traefik (Reverse Proxy)

```yaml
version: '3.8'

services:
  web:
    image: idol-stage-calendar:latest
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.calendar.rule=Host(`calendar.example.com`)"
      - "traefik.http.routers.calendar.entrypoints=websecure"
      - "traefik.http.routers.calendar.tls.certresolver=letsencrypt"
    networks:
      - traefik-network

networks:
  traefik-network:
    external: true
```

### With Let's Encrypt SSL

Use [Caddy](https://caddyserver.com/) for automatic HTTPS:

```yaml
services:
  caddy:
    image: caddy:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./Caddyfile:/etc/caddy/Caddyfile
      - caddy-data:/data
    networks:
      - app-network

volumes:
  caddy-data:
```

**Caddyfile**:
```
calendar.example.com {
    reverse_proxy web:80
}
```

---

## 📝 Summary

### Commands Cheat Sheet

```bash
# Start
docker-compose up -d

# Stop
docker-compose down

# Restart
docker-compose restart

# Logs
docker-compose logs -f

# Shell access
docker exec -it idol-stage-calendar bash

# Run tests (dev container only — tests/ is excluded from the production image)
docker exec idol-stage-calendar php tests/run-tests.php

# Backup
docker cp idol-stage-calendar:/var/www/html/data/calendar.db ./backup.db

# Update
git pull && docker-compose up -d --build
```

---

## 🙏 Support

- **Documentation**: [README.md](README.md)
- **Issues**: [GitHub Issues](https://github.com/fordantitrust/stage-idol-calendar/issues)
- **Docker Hub**: (Coming soon)

---

**Happy Dockerizing!** 🐳

[⭐ Star on GitHub](https://github.com/fordantitrust/stage-idol-calendar) | [🐛 Report Issues](https://github.com/fordantitrust/stage-idol-calendar/issues) | [📖 Full Docs](README.md)
