# Stage 1: Build
FROM php:8.3-fpm AS build

# Install system dependencies
RUN apt-get update && apt-get install -y \
    curl \
    wget \
    git \
    zip \
    unzip \
    nginx \
    supervisor \
    zlib1g-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions (using -j2 to reduce memory usage during compilation)
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
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy application files
COPY . .

# Install PHP dependencies
RUN composer install --no-interaction --optimize-autoloader --no-dev

# Install npm dependencies with increased timeouts and reduced parallelism
RUN npm config set fetch-timeout 600000 \
    && npm config set fetch-retry-mintimeout 20000 \
    && npm config set fetch-retry-maxtimeout 120000 \
    && npm config set maxsockets 1 \
    && npm install --legacy-peer-deps --prefer-offline --no-audit --no-progress

# Build assets
RUN npm run build

# Clear npm cache to save space in image
RUN npm cache clean --force

# Generate application key and clear caches
RUN cp .env.example .env \
    && php artisan config:clear \
    && php artisan cache:clear

# Stage 2: Runtime
FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    curl \
    nginx \
    supervisor \    zlib1g-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \    libpng16-16t64 \
    libjpeg62-turbo \
    libfreetype6 \
    libonig5 \
    libxml2 \
    libzip5 \
    && rm -rf /var/lib/apt/lists/*

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

WORKDIR /app

COPY --from=build /app .

# Set proper permissions
RUN chown -R www-data:www-data /app \
    && chmod -R 755 /app \
    && chmod -R 775 /app/storage \
    && chmod -R 775 /app/bootstrap/cache

# Create nginx config
RUN mkdir -p /etc/nginx/sites-available /etc/nginx/sites-enabled

COPY docker/nginx.conf /etc/nginx/sites-available/default

RUN ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default

# Create supervisor config
RUN mkdir -p /etc/supervisor/conf.d

COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Create startup script
RUN mkdir -p /app/docker && cat > /app/docker/entrypoint.sh << 'EOF'
#!/bin/bash
set -e

# Run migrations (allow to fail on first attempt in case DB isn't ready)
php artisan migrate --force || true

# Start PHP-FPM in background
php-fpm -D

# Start Nginx in background
nginx -g "daemon off;" &

# Start Supervisor for queue workers in background
supervisord -c /etc/supervisor/conf.d/supervisord.conf &

# Wait for all background processes
wait
EOF

RUN chmod +x /app/docker/entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["/app/docker/entrypoint.sh"]
