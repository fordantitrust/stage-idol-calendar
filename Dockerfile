# Idol Stage Timetable - Dockerfile
# PHP 8.1+ with Apache and SQLite support

FROM php:8.1-apache

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    sqlite3 \
    && docker-php-ext-install pdo pdo_sqlite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite (if needed for pretty URLs)
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Create necessary directories with proper permissions
RUN mkdir -p /var/www/html/cache \
    && mkdir -p /var/www/html/ics \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/cache

# Create database if ICS files exist
RUN if [ -f /var/www/html/ics/*.ics ]; then \
        cd /var/www/html/tools && \
        php import-ics-to-sqlite.php && \
        php migrate-add-requests-table.php && \
        php migrate-add-credits-table.php; \
    fi

# Set proper permissions for database
RUN mkdir -p /var/www/html/data && \
    if [ -f /var/www/html/data/calendar.db ]; then \
        chmod 666 /var/www/html/data/calendar.db && \
        chown www-data:www-data /var/www/html/data/calendar.db; \
    fi && \
    chown www-data:www-data /var/www/html/data

# Expose port 80
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Start Apache
CMD ["apache2-foreground"]
