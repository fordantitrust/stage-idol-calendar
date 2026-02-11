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
# 1. Prepare your ICS files
mkdir -p ics
cp your-events.ics ics/

# 2. (Optional) Configure admin credentials
# Edit config/admin.php before building

# 3. Build and start
docker-compose up -d

# 4. Import data (if not auto-imported)
docker exec idol-stage-calendar php tools/import-ics-to-sqlite.php

# 5. Create required tables
docker exec idol-stage-calendar php tools/migrate-add-requests-table.php
docker exec idol-stage-calendar php tools/migrate-add-credits-table.php

# 6. Verify
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
  -v $(pwd)/calendar.db:/var/www/html/calendar.db \
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

```bash
# Run all tests
docker exec idol-stage-calendar php tests/run-tests.php

# Run specific test suite
docker exec idol-stage-calendar php tests/run-tests.php SecurityTest

# Run quick tests
docker exec idol-stage-calendar sh quick-test.sh
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

  # Cache (read-write)
  - ./cache:/var/www/html/cache

  # Database (read-write)
  - ./calendar.db:/var/www/html/calendar.db

  # Config (optional, read-only)
  - ./config:/var/www/html/config:ro
```

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
docker exec idol-stage-calendar chmod 666 /var/www/html/calendar.db
```

### Database Not Found

```bash
# Import ICS files
docker exec idol-stage-calendar php tools/import-ics-to-sqlite.php

# Verify database
docker exec idol-stage-calendar ls -la calendar.db
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
FROM php:8.2-apache AS builder

RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite

WORKDIR /app
COPY . /app/

# Import data
RUN cd tools && \
    php import-ics-to-sqlite.php && \
    php migrate-add-requests-table.php && \
    php migrate-add-credits-table.php

# Stage 2: Runtime
FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_sqlite \
    && a2enmod rewrite

COPY --from=builder /app /var/www/html/
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
CMD ["apache2-foreground"]
```

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

      - name: Build Docker image
        run: docker build -t idol-stage-calendar:latest .

      - name: Test image
        run: |
          docker run -d --name test idol-stage-calendar:latest
          sleep 5
          docker exec test php tests/run-tests.php
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
  - calendar-db:/var/www/html/calendar.db
  - cache-data:/var/www/html/cache

volumes:
  calendar-db:
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

---

## 📊 Maintenance

### Backup Database

```bash
# Copy database from container
docker cp idol-stage-calendar:/var/www/html/calendar.db ./backup-$(date +%Y%m%d).db

# Or use volume backup
docker run --rm \
  -v idol-stage-calendar_calendar-data:/data \
  -v $(pwd):/backup \
  alpine tar czf /backup/backup.tar.gz /data
```

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

# Run tests
docker exec idol-stage-calendar php tests/run-tests.php

# Backup
docker cp idol-stage-calendar:/var/www/html/calendar.db ./backup.db

# Update
git pull && docker-compose up -d --build
```

---

## 🙏 Support

- **Documentation**: [README.md](README.md)
- **Issues**: [GitHub Issues](https://github.com/yourusername/stage-idol-calendar/issues)
- **Docker Hub**: (Coming soon)

---

**Happy Dockerizing!** 🐳

[⭐ Star on GitHub](https://github.com/yourusername/stage-idol-calendar) | [🐛 Report Issues](https://github.com/yourusername/stage-idol-calendar/issues) | [📖 Full Docs](README.md)
