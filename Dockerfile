# =========================================
# Stage 1: Build (Compilation & Dependencies)
# =========================================
FROM php:8.4-fpm AS build

ENV COMPOSER_ALLOW_SUPERUSER=1

# Install build tools and development dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential \
    autoconf \
    automake \
    curl \
    wget \
    git \
    zip \
    unzip \
    zlib1g-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions with reduced parallelism (-j2) to prevent memory exhaustion on Railway
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j2 \
    gd \
    zip \
    pdo \
    pdo_mysql \
    mbstring \
    xml \
    bcmath \
    opcache

# Install Node.js 20 LTS
RUN curl -sL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy application files
COPY . .

# Prepare Laravel writable directories omitted from the Docker context
RUN mkdir -p \
    storage/app \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    && cp .env.example .env

# Install PHP dependencies
RUN composer install \
    --no-interaction \
    --optimize-autoloader \
    --no-dev \
    --no-scripts \
    && composer dump-autoload --no-scripts --optimize \
    && php artisan package:discover --ansi

# Configure npm with extended timeouts for Railway environment
RUN npm config set fetch-timeout 600000 \
    && npm config set fetch-retry-mintimeout 20000 \
    && npm config set fetch-retry-maxtimeout 120000 \
    && npm config set maxsockets 1

# Install npm dependencies
RUN npm install --legacy-peer-deps --prefer-offline --no-audit --no-progress

# Build frontend assets
RUN npm run build

# Clear npm cache to save space
RUN npm cache clean --force

# Prepare Laravel configuration
RUN APP_ENV=production \
    CACHE_STORE=file \
    SESSION_DRIVER=file \
    QUEUE_CONNECTION=sync \
    DB_CONNECTION=sqlite \
    php artisan config:clear \
    && APP_ENV=production CACHE_STORE=file SESSION_DRIVER=file QUEUE_CONNECTION=sync DB_CONNECTION=sqlite php artisan cache:clear \
    && APP_ENV=production CACHE_STORE=file SESSION_DRIVER=file QUEUE_CONNECTION=sync DB_CONNECTION=sqlite php artisan route:clear \
    && APP_ENV=production CACHE_STORE=file SESSION_DRIVER=file QUEUE_CONNECTION=sync DB_CONNECTION=sqlite php artisan view:clear

# =========================================
# Stage 2: Runtime (Production Image)
# =========================================
FROM php:8.4-fpm

# Install only runtime tools and minimal dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    nginx \
    supervisor \
    && rm -rf /var/lib/apt/lists/*

# Copy PHP compiled extensions from build stage
# This includes all the .so files and PHP configuration
COPY --from=build /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=build /usr/local/etc/php/conf.d /usr/local/etc/php/conf.d

WORKDIR /app

# Copy compiled application from build stage
COPY --from=build /app .

# Set proper Laravel permissions
RUN mkdir -p \
    /app/storage/app \
    /app/storage/framework/cache/data \
    /app/storage/framework/sessions \
    /app/storage/framework/views \
    /app/storage/logs \
    /app/bootstrap/cache \
    && chown -R www-data:www-data /app \
    && chmod -R 755 /app \
    && chmod -R 775 /app/storage \
    && chmod -R 775 /app/bootstrap/cache

# Setup Nginx configuration
RUN mkdir -p /etc/nginx/sites-available /etc/nginx/sites-enabled
COPY docker/nginx.conf /etc/nginx/sites-available/default
RUN ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default

# Setup Supervisor configuration
RUN mkdir -p /etc/supervisor/conf.d
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Create entrypoint script
RUN mkdir -p /app/docker && cat > /app/docker/entrypoint.sh << 'EOF'
#!/bin/bash
set -e

echo "🚀 Starting Laravel application..."

# Ensure permissions on storage and cache directories
chown -R www-data:www-data /app/storage /app/bootstrap/cache

# Run database migrations (allow to fail if DB not ready on first attempt)
echo "📊 Running database migrations..."
php artisan migrate --force || true

# Cache Laravel configurations for performance
echo "⚡ Caching configurations..."
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

echo "✅ Application ready!"

# Start PHP-FPM
php-fpm -D

# Start Nginx
nginx -g "daemon off;" &

# Start Supervisor for queue workers
supervisord -c /etc/supervisor/conf.d/supervisord.conf &

# Keep container running
wait
EOF

RUN chmod +x /app/docker/entrypoint.sh

# Expose port for Railway
EXPOSE 8000

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:8000/up || exit 1

ENTRYPOINT ["/app/docker/entrypoint.sh"]
