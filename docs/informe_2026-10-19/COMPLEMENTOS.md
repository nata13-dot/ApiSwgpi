# Recursos complementarios y operación

Referencia de archivos propios no desarrollados como clases en el anexo principal. Se conserva código y configuración declarada, sin copiar `.env`, bases, registros ni credenciales de seeders. No ejecutar estos scripts sólo por aparecer en el informe.

## .dockerignore

Fuente: [.dockerignore](../../.dockerignore).

<details>
<summary>Ver contenido completo</summary>

```text
node_modules
vendor
.git
.gitignore
.env
storage
bootstrap/cache
.DS_Store
Thumbs.db
*.log
.vscode
.idea
public/hot
public/storage
database/seeders
tests
.phpunit.result.cache
```

</details>

## .editorconfig

Fuente: [.editorconfig](../../.editorconfig).

<details>
<summary>Ver contenido completo</summary>

```text
root = true

[*]
charset = utf-8
end_of_line = lf
indent_size = 4
indent_style = space
insert_final_newline = true
trim_trailing_whitespace = true

[*.md]
trim_trailing_whitespace = false

[*.{yml,yaml}]
indent_size = 2

[{compose,docker-compose}.{yml,yaml}]
indent_size = 4
```

</details>

## .gitattributes

Fuente: [.gitattributes](../../.gitattributes).

<details>
<summary>Ver contenido completo</summary>

```text
* text=auto eol=lf

*.blade.php diff=html
*.css diff=css
*.html diff=html
*.md diff=markdown
*.php diff=php

/.github export-ignore
CHANGELOG.md export-ignore
.styleci.yml export-ignore
```

</details>

## .github/workflows/issues.yml

Fuente: [.github/workflows/issues.yml](../../.github/workflows/issues.yml).

<details>
<summary>Ver contenido completo</summary>

```yaml
name: Issues

on:
  issues:
    types: [labeled]

permissions:
  issues: write

jobs:
  help-wanted:
    uses: laravel/.github/.github/workflows/issues.yml@main
```

</details>

## .github/workflows/pull-requests.yml

Fuente: [.github/workflows/pull-requests.yml](../../.github/workflows/pull-requests.yml).

<details>
<summary>Ver contenido completo</summary>

```yaml
name: Pull Requests

on:
  pull_request_target:
    types: [opened]

permissions:
  pull-requests: write

jobs:
  uneditable:
    uses: laravel/.github/.github/workflows/pull-requests.yml@main
```

</details>

## .github/workflows/tests.yml

Fuente: [.github/workflows/tests.yml](../../.github/workflows/tests.yml).

<details>
<summary>Ver contenido completo</summary>

```yaml
name: Tests

on:
  push:
    branches:
      - master
      - '*.x'
  pull_request:
  schedule:
    - cron: '0 0 * * *'

permissions:
  contents: read

jobs:
  tests:
    runs-on: ubuntu-latest

    strategy:
      fail-fast: true
      matrix:
        php: [8.3, 8.4, 8.5]

    name: PHP ${{ matrix.php }}

    steps:
      - name: Checkout code
        uses: actions/checkout@v6

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: dom, curl, libxml, mbstring, zip, pcntl, pdo, sqlite, pdo_sqlite
          coverage: none

      - name: Install Composer dependencies
        run: composer install --prefer-dist --no-interaction --no-progress

      - name: Copy environment file
        run: cp .env.example .env

      - name: Generate app key
        run: php artisan key:generate

      - name: Execute tests
        run: php artisan test
```

</details>

## .github/workflows/update-changelog.yml

Fuente: [.github/workflows/update-changelog.yml](../../.github/workflows/update-changelog.yml).

<details>
<summary>Ver contenido completo</summary>

```yaml
name: Update Changelog

on:
  release:
    types: [released]

permissions: {}

jobs:
  update:
    permissions:
      contents: write
    uses: laravel/.github/.github/workflows/update-changelog.yml@main
```

</details>

## .gitignore

Fuente: [.gitignore](../../.gitignore).

<details>
<summary>Ver contenido completo</summary>

```text
*.log
.DS_Store
.env
.env.backup
.env.production
.phpactor.json
.phpunit.result.cache
/.codex
/.cursor/
/.idea
/.nova
/.phpunit.cache
/.vscode
/.zed
/auth.json
/node_modules
/public/build
/public/fonts-manifest.dev.json
/public/hot
/public/storage
/storage/*.key
/storage/pail
/vendor
_ide_helper.php
Homestead.json
Homestead.yaml
Thumbs.db
```

</details>

## .npmrc

Fuente: [.npmrc](../../.npmrc).

<details>
<summary>Ver contenido completo</summary>

```text
ignore-scripts=true
audit=true
```

</details>

## .styleci.yml

Fuente: [.styleci.yml](../../.styleci.yml).

<details>
<summary>Ver contenido completo</summary>

```yaml
php:
  preset: laravel
  disabled:
    - no_unused_imports
  finder:
    not-name:
      - index.php
js: true
css: true
```

</details>

## Dockerfile

Fuente: [Dockerfile](../../Dockerfile).

<details>
<summary>Ver contenido completo</summary>

```text
# =========================================
# Stage 1: Build (Compilation & Dependencies)
# =========================================
FROM php:8.2-fpm-bookworm AS build

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
FROM php:8.2-fpm-bookworm

# Enable only on the single designated scheduler service after schema verification.
ENV SGPI_SCHEDULER_ENABLED=false

# Install only runtime tools and minimal dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates \
    curl \
    libfreetype6 \
    libjpeg62-turbo \
    libonig5 \
    libpng16-16 \
    libxml2 \
    libzip4 \
    nginx \
    supervisor \
    && rm -rf /var/lib/apt/lists/*

# Copy PHP compiled extensions from build stage
# This includes all the .so files and PHP configuration
COPY --from=build /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=build /usr/local/etc/php/conf.d /usr/local/etc/php/conf.d

# Upload limits for repository and deliverable documents
RUN { \
    echo "upload_max_filesize=100M"; \
    echo "post_max_size=100M"; \
    echo "max_file_uploads=20"; \
    echo "memory_limit=256M"; \
    echo "max_execution_time=120"; \
    echo "max_input_time=120"; \
    } > /usr/local/etc/php/conf.d/uploads.ini

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

# Ensure runtime directories exist after Railway volumes are mounted
mkdir -p \
    /app/storage/app/public \
    /app/storage/framework/cache/data \
    /app/storage/framework/sessions \
    /app/storage/framework/views \
    /app/storage/logs \
    /app/bootstrap/cache

# Ensure permissions on storage and cache directories
chown -R www-data:www-data /app/storage /app/bootstrap/cache
chmod -R ug+rwX /app/storage /app/bootstrap/cache

# Railway injects PORT at runtime; keep Nginx aligned with it.
sed -i "s/listen 8000;/listen ${PORT:-8000};/" /etc/nginx/sites-available/default

# Public storage symlink for Laravel files
php artisan storage:link || true

# Apply reviewed additive migrations in the release job, after backup rehearsal.
# Web replicas never run migrations automatically.

# Cache Laravel configurations for performance
echo "⚡ Caching configurations..."
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

echo "PHP upload_max_filesize=$(php -r 'echo ini_get("upload_max_filesize");')"
echo "PHP post_max_size=$(php -r 'echo ini_get("post_max_size");')"
echo "PHP file_uploads=$(php -r 'echo ini_get("file_uploads");')"
echo "PHP gd_extension=$(php -r 'echo extension_loaded("gd") ? "yes" : "no";')"
echo "PHP zip_extension=$(php -r 'echo class_exists("ZipArchive") ? "yes" : "no";')"
echo "Storage writable=$(php -r 'echo is_writable("/app/storage/app/public") ? "yes" : "no";')"
echo "Nginx port=${PORT:-8000}"

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
    CMD curl -f http://localhost:${PORT:-8000}/up || exit 1

ENTRYPOINT ["/app/docker/entrypoint.sh"]
```

</details>

## artisan

Fuente: [artisan](../../artisan).

<details>
<summary>Ver contenido completo</summary>

```text
#!/usr/bin/env php
<?php

use Illuminate\Foundation\Application;
use Symfony\Component\Console\Input\ArgvInput;

define('LARAVEL_START', microtime(true));

// Register the Composer autoloader...
require __DIR__.'/vendor/autoload.php';

// Bootstrap Laravel and handle the command...
/** @var Application $app */
$app = require_once __DIR__.'/bootstrap/app.php';

$status = $app->handleCommand(new ArgvInput);

exit($status);
```

</details>

## bootstrap/app.php

Fuente: [bootstrap/app.php](../../bootstrap/app.php).

<details>
<summary>Ver contenido completo</summary>

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(\App\Http\Middleware\EnsureCorsHeaders::class);
        $middleware->prepend(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->prependToPriorityList(\Illuminate\Routing\Middleware\SubstituteBindings::class, \App\Http\Middleware\ResolveCareerContext::class);
        $middleware->redirectGuestsTo(fn () => null);
        $middleware->api(prepend: [
            \App\Http\Middleware\SanitizeApiInput::class,
        ]);
        
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'active' => \App\Http\Middleware\EnsureUserIsActive::class,
            'career' => \App\Http\Middleware\ResolveCareerContext::class,
            'career.module' => \App\Http\Middleware\EnsureCareerModuleEnabled::class,
            'academic.open' => \App\Http\Middleware\EnsureAcademicPeriodOpen::class,
            'audit' => \App\Http\Middleware\AuditCareerMutation::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (
            \Illuminate\Auth\AuthenticationException $exception,
            \Illuminate\Http\Request $request
        ) {
            if (!$request->is('api/*')) {
                return null;
            }

            return response()->json(['error' => 'No autenticado.'], 401);
        });

        $exceptions->render(function (
            \Tymon\JWTAuth\Exceptions\JWTException $exception,
            \Illuminate\Http\Request $request
        ) {
            if (!$request->is('api/*')) {
                return null;
            }

            $message = $exception instanceof \Tymon\JWTAuth\Exceptions\TokenExpiredException
                ? 'La sesion ha expirado.'
                : 'No autenticado.';

            return response()->json(['error' => $message], 401);
        });

        $exceptions->respond(function (
            \Symfony\Component\HttpFoundation\Response $response,
            \Throwable $exception,
            \Illuminate\Http\Request $request
        ) {
            return \App\Http\Middleware\EnsureCorsHeaders::apply($response, $request);
        });
    })->create();
```

</details>

## bootstrap/providers.php

Fuente: [bootstrap/providers.php](../../bootstrap/providers.php).

<details>
<summary>Ver contenido completo</summary>

```php
<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
];
```

</details>

## composer.json

Fuente: [composer.json](../../composer.json).

<details>
<summary>Ver contenido completo</summary>

```json
{
    "$schema": "https://getcomposer.org/schema.json",
    "name": "laravel/laravel",
    "type": "project",
    "description": "The skeleton application for the Laravel framework.",
    "keywords": ["laravel", "framework"],
    "license": "MIT",
    "require": {
        "php": "^8.2",
        "barryvdh/laravel-dompdf": "^3.1",
        "laravel/framework": "^12.0",
        "laravel/sanctum": "^4.3",
        "laravel/tinker": "^2.10",
        "resend/resend-laravel": "^1.4",
        "resend/resend-php": "^1.0",
        "tymon/jwt-auth": "^2.3"
    },
    "require-dev": {
        "fakerphp/faker": "^1.23",
        "laravel/pail": "^1.2.5",
        "laravel/pint": "^1.27",
        "mockery/mockery": "^1.6",
        "nunomaduro/collision": "^8.6",
        "phpunit/phpunit": "^11.5"
    },
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "Database\\Factories\\": "database/factories/",
            "Database\\Seeders\\": "database/seeders/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\": "tests/"
        }
    },
    "scripts": {
        "setup": [
            "composer install",
            "@php -r \"file_exists('.env') || copy('.env.example', '.env');\"",
            "@php artisan key:generate",
            "@php artisan migrate --force",
            "npm install --ignore-scripts",
            "npm run build"
        ],
        "dev": [
            "Composer\\Config::disableProcessTimeout",
            "npx concurrently -c \"#93c5fd,#c4b5fd,#fb7185,#fdba74\" \"php artisan serve\" \"php artisan queue:listen --tries=1 --timeout=0\" \"php artisan pail --timeout=0\" \"npm run dev\" --names=server,queue,logs,vite --kill-others"
        ],
        "test": [
            "@php artisan config:clear --ansi @no_additional_args",
            "@php artisan test"
        ],
        "post-autoload-dump": [
            "Illuminate\\Foundation\\ComposerScripts::postAutoloadDump",
            "@php artisan package:discover --ansi"
        ],
        "post-update-cmd": [
            "@php artisan vendor:publish --tag=laravel-assets --ansi --force"
        ],
        "post-root-package-install": [
            "@php -r \"file_exists('.env') || copy('.env.example', '.env');\""
        ],
        "post-create-project-cmd": [
            "@php artisan key:generate --ansi",
            "@php -r \"file_exists('database/database.sqlite') || touch('database/database.sqlite');\"",
            "@php artisan migrate --graceful --ansi"
        ],
        "pre-package-uninstall": [
            "Illuminate\\Foundation\\ComposerScripts::prePackageUninstall"
        ]
    },
    "extra": {
        "laravel": {
            "dont-discover": []
        }
    },
    "config": {
        "platform": {
            "php": "8.2.0"
        },
        "optimize-autoloader": true,
        "preferred-install": "dist",
        "sort-packages": true,
        "allow-plugins": {
            "pestphp/pest-plugin": true,
            "php-http/discovery": true
        }
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}
```

</details>

## docker/nginx.conf

Fuente: [docker/nginx.conf](../../docker/nginx.conf).

<details>
<summary>Ver contenido completo</summary>

```text
server {
    listen 8000;
    server_name _;
    root /app/public;
    index index.php;
    client_max_body_size 100M;

    # Gzip compression
    gzip on;
    gzip_types text/plain text/css text/xml text/javascript application/x-javascript application/xml+rss application/json;

    # Security headers
    add_header Content-Security-Policy "frame-ancestors 'self' https://swgpi.online https://www.swgpi.online https://*.up.railway.app http://localhost:* http://127.0.0.1:*" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    # Logging
    access_log /var/log/nginx/access.log;
    error_log /var/log/nginx/error.log;

    # Laravel rewrite rules
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP handler
    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PHP_VALUE "upload_max_filesize=100M \n post_max_size=100M";
        include fastcgi_params;
    }

    # Deny access to dotfiles
    location ~ /\. {
        deny all;
    }

    # Static files caching
    location ~* ^/(?:css|js|images|fonts)/(.*)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

</details>

## docker/supervisord.conf

Fuente: [docker/supervisord.conf](../../docker/supervisord.conf).

<details>
<summary>Ver contenido completo</summary>

```text
[supervisord]
nodaemon=true
user=root
logfile=/var/log/supervisor/supervisord.log
pidfile=/var/run/supervisord.pid

[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /app/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/supervisor/worker.log
stopasgroup=true

; Enable on exactly one scheduler instance after applying the academic migration.
[program:laravel-scheduler]
command=php /app/artisan schedule:work
autostart=%(ENV_SGPI_SCHEDULER_ENABLED)s
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/supervisor/scheduler.log
stopasgroup=true
```

</details>

## package.json

Fuente: [package.json](../../package.json).

<details>
<summary>Ver contenido completo</summary>

```json
{
    "$schema": "https://www.schemastore.org/package.json",
    "private": true,
    "type": "module",
    "scripts": {
        "build": "vite build",
        "dev": "vite"
    },
    "devDependencies": {
        "@tailwindcss/vite": "^4.0.0",
        "concurrently": "^9.0.1",
        "laravel-vite-plugin": "^3.1",
        "tailwindcss": "^4.0.0",
        "vite": "^8.0.0"
    }
}
```

</details>

## phpunit.xml

Fuente: [phpunit.xml](../../phpunit.xml).

<details>
<summary>Ver contenido completo</summary>

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
>
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>app</directory>
        </include>
    </source>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="APP_MAINTENANCE_DRIVER" value="file"/>
        <env name="BCRYPT_ROUNDS" value="4"/>
        <env name="BROADCAST_CONNECTION" value="null"/>
        <env name="CACHE_STORE" value="array"/>
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
        <env name="DB_URL" value=""/>
        <env name="MAIL_MAILER" value="array"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
        <env name="SESSION_DRIVER" value="array"/>
        <env name="PULSE_ENABLED" value="false"/>
        <env name="TELESCOPE_ENABLED" value="false"/>
        <env name="NIGHTWATCH_ENABLED" value="false"/>
    </php>
</phpunit>
```

</details>

## public/.htaccess

Fuente: [public/.htaccess](../../public/.htaccess).

<details>
<summary>Ver contenido completo</summary>

```text
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Handle X-XSRF-Token Header
    RewriteCond %{HTTP:x-xsrf-token} .
    RewriteRule .* - [E=HTTP_X_XSRF_TOKEN:%{HTTP:X-XSRF-Token}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>

<IfModule mod_headers.c>
    Header always unset X-Powered-By
</IfModule>

<IfModule mod_php.c>
    php_flag expose_php Off
</IfModule>
```

</details>

## public/.user.ini

Fuente: [public/.user.ini](../../public/.user.ini).

<details>
<summary>Ver contenido completo</summary>

```text
expose_php=Off
```

</details>

## public/index.php

Fuente: [public/index.php](../../public/index.php).

<details>
<summary>Ver contenido completo</summary>

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
```

</details>

## public/robots.txt

Fuente: [public/robots.txt](../../public/robots.txt).

<details>
<summary>Ver contenido completo</summary>

```text
User-agent: *
Disallow:
```

</details>

## railway.json

Fuente: [railway.json](../../railway.json).

<details>
<summary>Ver contenido completo</summary>

```json
{
  "$schema": "https://railway.com/railway.schema.json",
  "build": {
    "builder": "DOCKERFILE"
  }
}
```

</details>

## resources/css/app.css

Fuente: [resources/css/app.css](../../resources/css/app.css).

<details>
<summary>Ver contenido completo</summary>

```css
@import 'tailwindcss';

@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
@source '../../storage/framework/views/*.php';
@source '../**/*.blade.php';
@source '../**/*.js';

@theme {
    --font-sans: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji',
        'Segoe UI Symbol', 'Noto Color Emoji';
}
```

</details>

## resources/js/app.js

Fuente: [resources/js/app.js](../../resources/js/app.js).

<details>
<summary>Ver contenido completo</summary>

```javascript
//
```

</details>

## resources/views/emails/password-reset-token-text.blade.php

Fuente: [resources/views/emails/password-reset-token-text.blade.php](../../resources/views/emails/password-reset-token-text.blade.php).

<details>
<summary>Ver contenido completo</summary>

```php
Hola {{ $name }},

Recibimos una solicitud para cambiar la contrasena de tu cuenta.

Tu token de recuperacion es: {{ $token }}

Este token vence en 15 minutos.

Si no solicitaste este cambio, puedes ignorar este correo. Tu contrasena actual seguira funcionando.

Correo automatico enviado por Sistema SGPI. No respondas a este mensaje.
```

</details>

## resources/views/emails/password-reset-token.blade.php

Fuente: [resources/views/emails/password-reset-token.blade.php](../../resources/views/emails/password-reset-token.blade.php).

<details>
<summary>Ver contenido completo</summary>

```php
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Token de recuperacion</title>
</head>
<body style="margin:0; padding:0; background:#eef2f7; font-family:Arial, Helvetica, sans-serif; color:#172033;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#eef2f7; padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px; background:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #d9e2ef;">
                    <tr>
                        <td style="background:#1B396A; padding:26px 30px;">
                            <div style="font-size:13px; letter-spacing:.08em; text-transform:uppercase; color:#c9d8ef;">Sistema SGPI</div>
                            <h1 style="margin:8px 0 0; color:#ffffff; font-size:24px; line-height:1.25;">Recuperacion de contrasena</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:30px;">
                            <p style="margin:0 0 14px; font-size:16px;">Hola {{ $name }},</p>
                            <p style="margin:0 0 22px; font-size:15px; line-height:1.6; color:#42526b;">
                                Recibimos una solicitud para cambiar la contrasena de tu cuenta. Usa el siguiente token en el sistema para continuar:
                            </p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 24px;">
                                <tr>
                                    <td align="center" style="background:#f8fafc; border:1px solid #dce6f2; border-radius:10px; padding:24px 12px;">
                                        <div style="font-size:34px; line-height:1; font-weight:700; letter-spacing:8px; color:#1B396A;">{{ $token }}</div>
                                        <div style="margin-top:12px; font-size:12px; color:#68758a;">Este token vence en 15 minutos.</div>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 12px; font-size:14px; line-height:1.6; color:#42526b;">
                                Si no solicitaste este cambio, puedes ignorar este correo. Tu contrasena actual seguira funcionando.
                            </p>
                            <p style="margin:0; font-size:13px; line-height:1.6; color:#68758a;">
                                Por seguridad, no compartas este token con nadie.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#f8fafc; padding:18px 30px; border-top:1px solid #e5edf6;">
                            <p style="margin:0; font-size:12px; line-height:1.5; color:#68758a;">
                                Correo automatico enviado por Sistema SGPI. No respondas a este mensaje.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
```

</details>

## resources/views/emails/user-credentials-text.blade.php

Fuente: [resources/views/emails/user-credentials-text.blade.php](../../resources/views/emails/user-credentials-text.blade.php).

<details>
<summary>Ver contenido completo</summary>

```php
{{ $body }}

Si no reconoces este correo, contacta al administrador del sistema.
```

</details>

## resources/views/emails/user-credentials.blade.php

Fuente: [resources/views/emails/user-credentials.blade.php](../../resources/views/emails/user-credentials.blade.php).

<details>
<summary>Ver contenido completo</summary>

```php
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Credenciales de acceso</title>
</head>
<body style="margin:0;padding:0;background:#f4f6fb;font-family:Arial,Helvetica,sans-serif;color:#333645;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f6fb;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="620" cellspacing="0" cellpadding="0" style="max-width:620px;width:100%;background:#ffffff;border:1px solid #e0e0e0;border-radius:8px;overflow:hidden;">
                    <tr>
                        <td style="background:#1B396A;color:#ffffff;padding:18px 24px;font-size:18px;font-weight:bold;">
                            Sistema de Gestion de Proyectos Integradores
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px;line-height:1.55;font-size:15px;white-space:pre-line;">
                            {!! nl2br(e($body)) !!}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 24px;background:#f8f9fa;color:#7a7f88;font-size:12px;">
                            Si no reconoces este correo, contacta al administrador del sistema.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
```

</details>

## resources/views/reports/continuity-pdf.blade.php

Fuente: [resources/views/reports/continuity-pdf.blade.php](../../resources/views/reports/continuity-pdf.blade.php).

<details>
<summary>Ver contenido completo</summary>

```php
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 28px 34px; }
    body { font-family: DejaVu Sans, sans-serif; color: #102a4c; font-size: 10px; }
    h1 { font-size: 22px; margin: 0 0 4px; color: #072f5f; }
    h2 { font-size: 13px; margin: 20px 0 8px; color: #072f5f; border-bottom: 1px solid #d8e1eb; padding-bottom: 5px; }
    .muted { color: #65758b; }
    .score { margin: 18px 0; padding: 16px; border-radius: 8px; background: {{ $readiness_score === 100 ? '#eaf7ee' : '#fff4df' }}; }
    .score strong { font-size: 28px; color: {{ $readiness_score === 100 ? '#168b37' : '#b26a00' }}; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    th { text-align: left; background: #eef3f8; color: #183b66; }
    th, td { padding: 7px; border: 1px solid #dbe3ec; vertical-align: top; }
    .ok { color: #168b37; font-weight: bold; }
    .fail { color: #b42318; font-weight: bold; }
    .footer { margin-top: 20px; font-size: 8px; color: #718096; }
</style>
</head>
<body>
    <h1>Reporte institucional de continuidad</h1>
    <div class="muted">SGPI ITSSMT · Generado {{ $generated_at->format('d/m/Y H:i:s') }} por {{ $generated_by ?: 'Administrador general' }}</div>

    <div class="score">
        <strong>{{ $readiness_score }}%</strong><br>
        Índice de preparación · {{ $controls_passed }} de {{ $controls_total }} controles correctos
    </div>

    <h2>Controles de continuidad</h2>
    <table>
        <thead><tr><th>Control</th><th>Resultado</th><th>Evidencia</th></tr></thead>
        <tbody>
        @foreach($controls as $control)
            <tr>
                <td>{{ $control['name'] }}</td>
                <td class="{{ $control['passed'] ? 'ok' : 'fail' }}">{{ $control['passed'] ? 'CORRECTO' : 'REVISAR' }}</td>
                <td>{{ $control['detail'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <h2>Estado de recuperación</h2>
    <table>
        <tbody>
            <tr><th>Respaldos disponibles</th><td>{{ $storage['available'] }}</td><th>Copias restaurables</th><td>{{ $storage['verified'] }}</td></tr>
            <tr><th>Archivos faltantes</th><td>{{ $storage['missing'] }}</td><th>Archivos alterados</th><td>{{ $storage['altered'] }}</td></tr>
            <tr><th>Espacio utilizado</th><td>{{ number_format($storage['total_bytes'] / 1024, 1) }} KB</td><th>Espacio libre</th><td>{{ number_format(($storage['disk_free_bytes'] ?? 0) / 1073741824, 1) }} GB</td></tr>
            <tr><th>Último respaldo</th><td colspan="3">{{ $latest_backup?->creado_en ?? 'No disponible' }}</td></tr>
            <tr><th>Último simulacro</th><td colspan="3">{{ $latest_verification?->creado_en ?? 'No disponible' }}</td></tr>
        </tbody>
    </table>

    <h2>Resumen multicarrera</h2>
    <table>
        <thead><tr><th>Clave</th><th>Carrera</th><th>Estado</th><th>Usuarios</th><th>Proyectos</th></tr></thead>
        <tbody>
        @foreach($careers as $career)
            <tr>
                <td>{{ $career->clave }}</td><td>{{ $career->nombre }}</td>
                <td>{{ $career->activa ? 'Activa' : 'Inactiva' }}</td>
                <td>{{ $career->usuarios }}</td><td>{{ $career->proyectos }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <h2>Alertas operativas</h2>
    <p>{{ $alerts['total'] }} alertas activas · {{ $alerts['critical'] }} críticas · {{ $integrity['violations'] }} incidencias de integridad.</p>
    @if(count($alerts['data']))
        <table>
            <thead><tr><th>Severidad</th><th>Estado</th><th>Alerta</th></tr></thead>
            <tbody>
            @foreach($alerts['data'] as $alert)
                <tr><td>{{ strtoupper($alert->severidad) }}</td><td>{{ $alert->estado }}</td><td><strong>{{ $alert->titulo }}</strong><br>{{ $alert->detalle }}</td></tr>
            @endforeach
            </tbody>
        </table>
    @else
        <p class="ok">No existen alertas operativas activas.</p>
    @endif

    <div class="footer">Documento generado automáticamente por SGPI. El reporte no contiene contraseñas, tokens ni rutas privadas de respaldo.</div>
</body>
</html>
```

</details>

## resources/views/reports/evaluation-pdf.blade.php

Fuente: [resources/views/reports/evaluation-pdf.blade.php](../../resources/views/reports/evaluation-pdf.blade.php).

<details>
<summary>Ver contenido completo</summary>

```php
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de Evaluacion</title>
    <style>
        @page { margin: 18px 16px; size: letter landscape; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 9px; }
        h1 { font-size: 18px; margin: 0 0 5px; }
        h2 { font-size: 12px; margin: 15px 0 6px; color: #1f2937; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 4px; vertical-align: top; overflow-wrap: anywhere; word-break: break-word; }
        th { background: #e5eefc; font-weight: bold; }
        .muted { color: #6b7280; }
        .summary td:first-child { width: 32%; font-weight: bold; background: #f9fafb; }
        .matrix-table { table-layout: fixed; }
        .matrix-table th, .matrix-table td { font-size: 7.2px; line-height: 1.25; }
        .matrix-table th:first-child, .matrix-table td:first-child { width: 18%; font-weight: bold; }
        .matrix-table th:last-child, .matrix-table td:last-child { width: 8%; }
        .score { white-space: normal; }
        .chart { text-align: center; margin-top: 8px; }
        .chart img { max-width: 100%; height: auto; border: 1px solid #e5e7eb; }
        .comment { border: 1px solid #d1d5db; padding: 6px; margin-bottom: 6px; page-break-inside: avoid; }
        .comment strong { display: block; margin-bottom: 4px; }
        .criterion-comment { margin: 4px 0 0 12px; }
    </style>
</head>
<body>
    <h1>Reporte de Evaluacion</h1>
    <div class="muted">{{ $project?->title ?? 'Proyecto sin titulo' }}</div>

    <h2>Datos Generales</h2>
    <table class="summary">
        <tbody>
            <tr><td>Proyecto</td><td>{{ $project?->title ?? '-' }}</td></tr>
            <tr><td>Equipo</td><td>{{ $students->map(fn ($student) => trim(collect([$student->nombres, $student->apa, $student->ama])->filter()->join(' ')))->filter()->join(', ') ?: '-' }}</td></tr>
            <tr><td>Semestre</td><td>{{ $evaluation->semestre }}</td></tr>
            <tr><td>Etapa</td><td>{{ ucfirst((string) $evaluation->etapa) }}</td></tr>
            <tr><td>Sala</td><td>{{ $evaluation->room?->nombre ?? $evaluation->sala ?? '-' }}</td></tr>
            <tr><td>Fecha de evaluacion</td><td>{{ optional($evaluation->fecha_exposicion)->format('d/m/Y H:i') ?? '-' }}</td></tr>
            <tr><td>Promedio global</td><td>{{ $globalAverage }}%</td></tr>
            @if ($titulationAptSummary['applies'])
                <tr>
                    <td>Recomendación del proyecto (sin efecto de egreso)</td>
                    <td>{{ $titulationAptSummary['label'] }}</td>
                </tr>
            @endif
            <tr><td>Fecha de reporte</td><td>{{ $generatedAt->format('d/m/Y H:i') }}</td></tr>
        </tbody>
    </table>

    <h2>Tabla de Calificaciones por Criterio</h2>
    <table class="matrix-table">
        <thead>
            <tr>
                <th>Criterio</th>
                @foreach ($teachers as $teacher)
                    <th>{{ $teacherLabels[(string) $teacher->id] ?? (trim(collect([$teacher->nombres, $teacher->apa])->filter()->join(' ')) ?: $teacher->id) }}</th>
                @endforeach
                <th>Promedio</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($matrix as $row)
                <tr>
                    <td>{{ $row['label'] }}</td>
                    @foreach ($row['teacher_scores'] as $score)
                        <td class="score">
                            @if ($score['percentage'] === null)
                                -
                            @else
                                {{ $score['value'] }}<br>
                                <span class="muted">{{ $score['percentage'] }}% · {{ $score['level'] }}</span>
                            @endif
                        </td>
                    @endforeach
                    <td class="score"><strong>{{ $row['average'] }}%</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Grafica de Barras</h2>
    <div class="chart">
        <img src="{{ $chartUrl }}" alt="Grafica de promedio por criterio">
    </div>

    <h2>Comentarios por Docente</h2>
    @forelse ($comments as $comment)
        <div class="comment">
            <strong>{{ $comment['teacher_name'] }}</strong>
            @if ($titulationAptSummary['applies'])
                <div class="muted">
                    Recomendación del proyecto (sin efecto de egreso):
                    @if ($comment['apto_titulacion'] === null)
                        Sin respuesta
                    @else
                        {{ $comment['apto_titulacion'] ? 'Si' : 'No' }}
                    @endif
                </div>
            @endif
            <div>{{ $comment['general_comment'] ?: 'Sin comentario general.' }}</div>
            @foreach ($comment['criterion_comments'] as $criterionComment)
                <div class="criterion-comment">
                    <span class="muted">{{ $criterionComment['criterion'] }}:</span>
                    {{ $criterionComment['comment'] }}
                </div>
            @endforeach
        </div>
    @empty
        <p class="muted">Sin comentarios registrados.</p>
    @endforelse
</body>
</html>
```

</details>

## resources/views/reports/room-pdf.blade.php

Fuente: [resources/views/reports/room-pdf.blade.php](../../resources/views/reports/room-pdf.blade.php).

<details>
<summary>Ver contenido completo</summary>

```php
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de Sala</title>
    <style>
        @page { margin: 18px 16px; size: letter landscape; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 8.8px; }
        h1 { font-size: 18px; margin: 0 0 5px; }
        h2 { font-size: 12px; margin: 15px 0 6px; color: #1f2937; }
        h3 { font-size: 11px; margin: 14px 0 6px; color: #374151; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 4px; vertical-align: top; overflow-wrap: anywhere; word-break: break-word; }
        th { background: #e5eefc; font-weight: bold; }
        .muted { color: #6b7280; }
        .summary td:first-child { width: 30%; font-weight: bold; background: #f9fafb; }
        .projects-table { table-layout: fixed; }
        .projects-table th:nth-child(1), .projects-table td:nth-child(1) { width: 7%; }
        .projects-table th:nth-child(4), .projects-table td:nth-child(4),
        .projects-table th:nth-child(5), .projects-table td:nth-child(5),
        .projects-table th:nth-child(6), .projects-table td:nth-child(6),
        .projects-table th:nth-child(7), .projects-table td:nth-child(7) { width: 10%; }
        .matrix-table { table-layout: fixed; }
        .matrix-table th, .matrix-table td { font-size: 7px; line-height: 1.25; }
        .matrix-table th:first-child, .matrix-table td:first-child { width: 18%; font-weight: bold; }
        .matrix-table th:last-child, .matrix-table td:last-child { width: 8%; }
        .chart { text-align: center; margin-top: 8px; }
        .chart img { max-width: 100%; height: auto; border: 1px solid #e5e7eb; }
        .project-block { page-break-inside: avoid; margin-top: 14px; }
        .comment { border: 1px solid #d1d5db; padding: 6px; margin-bottom: 6px; page-break-inside: avoid; }
        .comment strong { display: block; margin-bottom: 3px; }
        .criterion-comment { margin: 3px 0 0 10px; }
        .score { white-space: normal; }
    </style>
</head>
<body>
    <h1>Reporte de Sala de Evaluacion</h1>
    <div class="muted">{{ $room->nombre }} · {{ $room->salon ?: 'Sin salon' }}</div>

    <h2>Datos Generales</h2>
    <table class="summary">
        <tbody>
            <tr><td>Sala</td><td>{{ $room->nombre }}</td></tr>
            <tr><td>Salon</td><td>{{ $room->salon ?: '-' }}</td></tr>
            <tr><td>Semestre</td><td>{{ $room->semestre }}</td></tr>
            <tr><td>Fecha de evaluacion</td><td>{{ optional($room->fecha_evaluacion)->format('d/m/Y H:i') ?? '-' }}</td></tr>
            <tr><td>Responsable</td><td>{{ $room->responsibleTeacher ? ($roomTeacherLabels[(string) $room->responsibleTeacher->id] ?? trim(collect([$room->responsibleTeacher->nombres, $room->responsibleTeacher->apa, $room->responsibleTeacher->ama])->filter()->join(' '))) : '-' }}</td></tr>
            <tr><td>Docentes</td><td>{{ $room->teachers->map(fn ($teacher) => $roomTeacherLabels[(string) $teacher->id] ?? trim(collect([$teacher->nombres, $teacher->apa, $teacher->ama])->filter()->join(' ')))->filter()->join(', ') ?: '-' }}</td></tr>
            <tr><td>Proyectos evaluados</td><td>{{ count($projectRows) }}</td></tr>
            <tr><td>Promedio global de sala</td><td>{{ $roomAverage }}%</td></tr>
            <tr><td>Fecha de reporte</td><td>{{ $generatedAt->format('d/m/Y H:i') }}</td></tr>
        </tbody>
    </table>

    <h2>Proyectos Evaluados</h2>
    <table class="projects-table">
        <thead>
            <tr>
                <th>Orden</th>
                <th>Proyecto</th>
                <th>Equipo</th>
                <th>Docentes</th>
                <th>Promedio</th>
                <th>Recomendación del proyecto</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($projectRows as $project)
                <tr>
                    <td>{{ $project['order'] ?: '-' }}</td>
                    <td>{{ $project['project_title'] }}</td>
                    <td>{{ $project['students'] ?: '-' }}</td>
                    <td>{{ $project['teachers_count'] }}</td>
                    <td><strong>{{ $project['average'] }}%</strong></td>
                    <td>{{ $project['titulation_apt_result'] }}</td>
                    <td>{{ $project['status'] ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">No hay proyectos evaluados en esta sala.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Grafica de Promedio por Proyecto</h2>
    <div class="chart">
        <img src="{{ $chartUrl }}" alt="Grafica de promedio por proyecto">
    </div>

    <h2>Detalle por Proyecto</h2>
    @foreach ($evaluationReports as $report)
        <div class="project-block">
            <h3>{{ $report['project']?->title ?? 'Proyecto sin titulo' }} · {{ $report['globalAverage'] }}%</h3>
            @if ($report['titulationAptSummary']['applies'])
                <p class="muted">
                    Recomendación del proyecto (sin efecto de egreso): <strong>{{ $report['titulationAptSummary']['label'] }}</strong>
                </p>
            @endif
            <table class="matrix-table">
                <thead>
                    <tr>
                        <th>Criterio</th>
                        @foreach ($report['teachers'] as $teacher)
                            <th>{{ $report['teacherLabels'][(string) $teacher->id] ?? (trim(collect([$teacher->nombres, $teacher->apa])->filter()->join(' ')) ?: $teacher->id) }}</th>
                        @endforeach
                        <th>Promedio</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($report['matrix'] as $row)
                        <tr>
                            <td>{{ $row['label'] }}</td>
                            @foreach ($row['teacher_scores'] as $score)
                                <td class="score">
                                    @if ($score['percentage'] === null)
                                        -
                                    @else
                                        {{ $score['value'] }}<br>
                                        <span class="muted">{{ $score['percentage'] }}% · {{ $score['level'] }}</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="score"><strong>{{ $row['average'] }}%</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <h3>Comentarios</h3>
            @forelse ($report['comments'] as $comment)
                <div class="comment">
                    <strong>{{ $comment['teacher_name'] }}</strong>
                    @if ($report['titulationAptSummary']['applies'])
                        <div class="muted">
                            Recomendación del proyecto (sin efecto de egreso):
                            @if ($comment['apto_titulacion'] === null)
                                Sin respuesta
                            @else
                                {{ $comment['apto_titulacion'] ? 'Si' : 'No' }}
                            @endif
                        </div>
                    @endif
                    <div>{{ $comment['general_comment'] ?: 'Sin comentario general.' }}</div>
                    @foreach ($comment['criterion_comments'] as $criterionComment)
                        <div class="criterion-comment">
                            <span class="muted">{{ $criterionComment['criterion'] }}:</span>
                            {{ $criterionComment['comment'] }}
                        </div>
                    @endforeach
                </div>
            @empty
                <p class="muted">Sin comentarios registrados.</p>
            @endforelse
        </div>
    @endforeach
</body>
</html>
```

</details>

## resources/views/welcome.blade.php

Fuente: [resources/views/welcome.blade.php](../../resources/views/welcome.blade.php).

<details>
<summary>Ver contenido completo</summary>

```php
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Styles / Scripts -->
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <style>
                /*! tailwindcss v4.0.7 | MIT License | https://tailwindcss.com */ @layer properties{@supports (((-webkit-hyphens:none)) and (not (margin-trim:inline))) or ((-moz-orient:inline) and (not (color:rgb(from red r g b)))){*,:before,:after,::backdrop{--tw-translate-x:0;--tw-translate-y:0;--tw-translate-z:0;--tw-rotate-x:initial;--tw-rotate-y:initial;--tw-rotate-z:initial;--tw-skew-x:initial;--tw-skew-y:initial;--tw-space-x-reverse:0;--tw-border-style:solid;--tw-leading:initial;--tw-font-weight:initial;--tw-tracking:initial;--tw-shadow:0 0 #0000;--tw-shadow-color:initial;--tw-shadow-alpha:100%;--tw-inset-shadow:0 0 #0000;--tw-inset-shadow-color:initial;--tw-inset-shadow-alpha:100%;--tw-ring-color:initial;--tw-ring-shadow:0 0 #0000;--tw-inset-ring-color:initial;--tw-inset-ring-shadow:0 0 #0000;--tw-ring-inset:initial;--tw-ring-offset-width:0px;--tw-ring-offset-color:#fff;--tw-ring-offset-shadow:0 0 #0000;--tw-blur:initial;--tw-brightness:initial;--tw-contrast:initial;--tw-grayscale:initial;--tw-hue-rotate:initial;--tw-invert:initial;--tw-opacity:initial;--tw-saturate:initial;--tw-sepia:initial;--tw-drop-shadow:initial;--tw-drop-shadow-color:initial;--tw-drop-shadow-alpha:100%;--tw-drop-shadow-size:initial;--tw-duration:initial;--tw-ease:initial;--tw-content:""}}}@layer theme{:root,:host{--font-sans:"Instrument Sans", ui-sans-serif, system-ui, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";--font-serif:ui-serif, Georgia, Cambria, "Times New Roman", Times, serif;--font-mono:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;--color-red-50:oklch(97.1% .013 17.38);--color-red-100:oklch(93.6% .032 17.717);--color-red-200:oklch(88.5% .062 18.334);--color-red-300:oklch(80.8% .114 19.571);--color-red-400:oklch(70.4% .191 22.216);--color-red-500:oklch(63.7% .237 25.331);--color-red-600:oklch(57.7% .245 27.325);--color-red-700:oklch(50.5% .213 27.518);--color-red-800:oklch(44.4% .177 26.899);--color-red-900:oklch(39.6% .141 25.723);--color-red-950:oklch(25.8% .092 26.042);--color-orange-50:oklch(98% .016 73.684);--color-orange-100:oklch(95.4% .038 75.164);--color-orange-200:oklch(90.1% .076 70.697);--color-orange-300:oklch(83.7% .128 66.29);--color-orange-400:oklch(75% .183 55.934);--color-orange-500:oklch(70.5% .213 47.604);--color-orange-600:oklch(64.6% .222 41.116);--color-orange-700:oklch(55.3% .195 38.402);--color-orange-800:oklch(47% .157 37.304);--color-orange-900:oklch(40.8% .123 38.172);--color-orange-950:oklch(26.6% .079 36.259);--color-amber-50:oklch(98.7% .022 95.277);--color-amber-100:oklch(96.2% .059 95.617);--color-amber-200:oklch(92.4% .12 95.746);--color-amber-300:oklch(87.9% .169 91.605);--color-amber-400:oklch(82.8% .189 84.429);--color-amber-500:oklch(76.9% .188 70.08);--color-amber-600:oklch(66.6% .179 58.318);--color-amber-700:oklch(55.5% .163 48.998);--color-amber-800:oklch(47.3% .137 46.201);--color-amber-900:oklch(41.4% .112 45.904);--color-amber-950:oklch(27.9% .077 45.635);--color-yellow-50:oklch(98.7% .026 102.212);--color-yellow-100:oklch(97.3% .071 103.193);--color-yellow-200:oklch(94.5% .129 101.54);--color-yellow-300:oklch(90.5% .182 98.111);--color-yellow-400:oklch(85.2% .199 91.936);--color-yellow-500:oklch(79.5% .184 86.047);--color-yellow-600:oklch(68.1% .162 75.834);--color-yellow-700:oklch(55.4% .135 66.442);--color-yellow-800:oklch(47.6% .114 61.907);--color-yellow-900:oklch(42.1% .095 57.708);--color-yellow-950:oklch(28.6% .066 53.813);--color-lime-50:oklch(98.6% .031 120.757);--color-lime-100:oklch(96.7% .067 122.328);--color-lime-200:oklch(93.8% .127 124.321);--color-lime-300:oklch(89.7% .196 126.665);--color-lime-400:oklch(84.1% .238 128.85);--color-lime-500:oklch(76.8% .233 130.85);--color-lime-600:oklch(64.8% .2 131.684);--color-lime-700:oklch(53.2% .157 131.589);--color-lime-800:oklch(45.3% .124 130.933);--color-lime-900:oklch(40.5% .101 131.063);--color-lime-950:oklch(27.4% .072 132.109);--color-green-50:oklch(98.2% .018 155.826);--color-green-100:oklch(96.2% .044 156.743);--color-green-200:oklch(92.5% .084 155.995);--color-green-300:oklch(87.1% .15 154.449);--color-green-400:oklch(79.2% .209 151.711);--color-green-500:oklch(72.3% .219 149.579);--color-green-600:oklch(62.7% .194 149.214);--color-green-700:oklch(52.7% .154 150.069);--color-green-800:oklch(44.8% .119 151.328);--color-green-900:oklch(39.3% .095 152.535);--color-green-950:oklch(26.6% .065 152.934);--color-emerald-50:oklch(97.9% .021 166.113);--color-emerald-100:oklch(95% .052 163.051);--color-emerald-200:oklch(90.5% .093 164.15);--color-emerald-300:oklch(84.5% .143 164.978);--color-emerald-400:oklch(76.5% .177 163.223);--color-emerald-500:oklch(69.6% .17 162.48);--color-emerald-600:oklch(59.6% .145 163.225);--color-emerald-700:oklch(50.8% .118 165.612);--color-emerald-800:oklch(43.2% .095 166.913);--color-emerald-900:oklch(37.8% .077 168.94);--color-emerald-950:oklch(26.2% .051 172.552);--color-teal-50:oklch(98.4% .014 180.72);--color-teal-100:oklch(95.3% .051 180.801);--color-teal-200:oklch(91% .096 180.426);--color-teal-300:oklch(85.5% .138 181.071);--color-teal-400:oklch(77.7% .152 181.912);--color-teal-500:oklch(70.4% .14 182.503);--color-teal-600:oklch(60% .118 184.704);--color-teal-700:oklch(51.1% .096 186.391);--color-teal-800:oklch(43.7% .078 188.216);--color-teal-900:oklch(38.6% .063 188.416);--color-teal-950:oklch(27.7% .046 192.524);--color-cyan-50:oklch(98.4% .019 200.873);--color-cyan-100:oklch(95.6% .045 203.388);--color-cyan-200:oklch(91.7% .08 205.041);--color-cyan-300:oklch(86.5% .127 207.078);--color-cyan-400:oklch(78.9% .154 211.53);--color-cyan-500:oklch(71.5% .143 215.221);--color-cyan-600:oklch(60.9% .126 221.723);--color-cyan-700:oklch(52% .105 223.128);--color-cyan-800:oklch(45% .085 224.283);--color-cyan-900:oklch(39.8% .07 227.392);--color-cyan-950:oklch(30.2% .056 229.695);--color-sky-50:oklch(97.7% .013 236.62);--color-sky-100:oklch(95.1% .026 236.824);--color-sky-200:oklch(90.1% .058 230.902);--color-sky-300:oklch(82.8% .111 230.318);--color-sky-400:oklch(74.6% .16 232.661);--color-sky-500:oklch(68.5% .169 237.323);--color-sky-600:oklch(58.8% .158 241.966);--color-sky-700:oklch(50% .134 242.749);--color-sky-800:oklch(44.3% .11 240.79);--color-sky-900:oklch(39.1% .09 240.876);--color-sky-950:oklch(29.3% .066 243.157);--color-blue-50:oklch(97% .014 254.604);--color-blue-100:oklch(93.2% .032 255.585);--color-blue-200:oklch(88.2% .059 254.128);--color-blue-300:oklch(80.9% .105 251.813);--color-blue-400:oklch(70.7% .165 254.624);--color-blue-500:oklch(62.3% .214 259.815);--color-blue-600:oklch(54.6% .245 262.881);--color-blue-700:oklch(48.8% .243 264.376);--color-blue-800:oklch(42.4% .199 265.638);--color-blue-900:oklch(37.9% .146 265.522);--color-blue-950:oklch(28.2% .091 267.935);--color-indigo-50:oklch(96.2% .018 272.314);--color-indigo-100:oklch(93% .034 272.788);--color-indigo-200:oklch(87% .065 274.039);--color-indigo-300:oklch(78.5% .115 274.713);--color-indigo-400:oklch(67.3% .182 276.935);--color-indigo-500:oklch(58.5% .233 277.117);--color-indigo-600:oklch(51.1% .262 276.966);--color-indigo-700:oklch(45.7% .24 277.023);--color-indigo-800:oklch(39.8% .195 277.366);--color-indigo-900:oklch(35.9% .144 278.697);--color-indigo-950:oklch(25.7% .09 281.288);--color-violet-50:oklch(96.9% .016 293.756);--color-violet-100:oklch(94.3% .029 294.588);--color-violet-200:oklch(89.4% .057 293.283);--color-violet-300:oklch(81.1% .111 293.571);--color-violet-400:oklch(70.2% .183 293.541);--color-violet-500:oklch(60.6% .25 292.717);--color-violet-600:oklch(54.1% .281 293.009);--color-violet-700:oklch(49.1% .27 292.581);--color-violet-800:oklch(43.2% .232 292.759);--color-violet-900:oklch(38% .189 293.745);--color-violet-950:oklch(28.3% .141 291.089);--color-purple-50:oklch(97.7% .014 308.299);--color-purple-100:oklch(94.6% .033 307.174);--color-purple-200:oklch(90.2% .063 306.703);--color-purple-300:oklch(82.7% .119 306.383);--color-purple-400:oklch(71.4% .203 305.504);--color-purple-500:oklch(62.7% .265 303.9);--color-purple-600:oklch(55.8% .288 302.321);--color-purple-700:oklch(49.6% .265 301.924);--color-purple-800:oklch(43.8% .218 303.724);--color-purple-900:oklch(38.1% .176 304.987);--color-purple-950:oklch(29.1% .149 302.717);--color-fuchsia-50:oklch(97.7% .017 320.058);--color-fuchsia-100:oklch(95.2% .037 318.852);--color-fuchsia-200:oklch(90.3% .076 319.62);--color-fuchsia-300:oklch(83.3% .145 321.434);--color-fuchsia-400:oklch(74% .238 322.16);--color-fuchsia-500:oklch(66.7% .295 322.15);--color-fuchsia-600:oklch(59.1% .293 322.896);--color-fuchsia-700:oklch(51.8% .253 323.949);--color-fuchsia-800:oklch(45.2% .211 324.591);--color-fuchsia-900:oklch(40.1% .17 325.612);--color-fuchsia-950:oklch(29.3% .136 325.661);--color-pink-50:oklch(97.1% .014 343.198);--color-pink-100:oklch(94.8% .028 342.258);--color-pink-200:oklch(89.9% .061 343.231);--color-pink-300:oklch(82.3% .12 346.018);--color-pink-400:oklch(71.8% .202 349.761);--color-pink-500:oklch(65.6% .241 354.308);--color-pink-600:oklch(59.2% .249 .584);--color-pink-700:oklch(52.5% .223 3.958);--color-pink-800:oklch(45.9% .187 3.815);--color-pink-900:oklch(40.8% .153 2.432);--color-pink-950:oklch(28.4% .109 3.907);--color-rose-50:oklch(96.9% .015 12.422);--color-rose-100:oklch(94.1% .03 12.58);--color-rose-200:oklch(89.2% .058 10.001);--color-rose-300:oklch(81% .117 11.638);--color-rose-400:oklch(71.2% .194 13.428);--color-rose-500:oklch(64.5% .246 16.439);--color-rose-600:oklch(58.6% .253 17.585);--color-rose-700:oklch(51.4% .222 16.935);--color-rose-800:oklch(45.5% .188 13.697);--color-rose-900:oklch(41% .159 10.272);--color-rose-950:oklch(27.1% .105 12.094);--color-slate-50:oklch(98.4% .003 247.858);--color-slate-100:oklch(96.8% .007 247.896);--color-slate-200:oklch(92.9% .013 255.508);--color-slate-300:oklch(86.9% .022 252.894);--color-slate-400:oklch(70.4% .04 256.788);--color-slate-500:oklch(55.4% .046 257.417);--color-slate-600:oklch(44.6% .043 257.281);--color-slate-700:oklch(37.2% .044 257.287);--color-slate-800:oklch(27.9% .041 260.031);--color-slate-900:oklch(20.8% .042 265.755);--color-slate-950:oklch(12.9% .042 264.695);--color-gray-50:oklch(98.5% .002 247.839);--color-gray-100:oklch(96.7% .003 264.542);--color-gray-200:oklch(92.8% .006 264.531);--color-gray-300:oklch(87.2% .01 258.338);--color-gray-400:oklch(70.7% .022 261.325);--color-gray-500:oklch(55.1% .027 264.364);--color-gray-600:oklch(44.6% .03 256.802);--color-gray-700:oklch(37.3% .034 259.733);--color-gray-800:oklch(27.8% .033 256.848);--color-gray-900:oklch(21% .034 264.665);--color-gray-950:oklch(13% .028 261.692);--color-zinc-50:oklch(98.5% 0 0);--color-zinc-100:oklch(96.7% .001 286.375);--color-zinc-200:oklch(92% .004 286.32);--color-zinc-300:oklch(87.1% .006 286.286);--color-zinc-400:oklch(70.5% .015 286.067);--color-zinc-500:oklch(55.2% .016 285.938);--color-zinc-600:oklch(44.2% .017 285.786);--color-zinc-700:oklch(37% .013 285.805);--color-zinc-800:oklch(27.4% .006 286.033);--color-zinc-900:oklch(21% .006 285.885);--color-zinc-950:oklch(14.1% .005 285.823);--color-neutral-50:oklch(98.5% 0 0);--color-neutral-100:oklch(97% 0 0);--color-neutral-200:oklch(92.2% 0 0);--color-neutral-300:oklch(87% 0 0);--color-neutral-400:oklch(70.8% 0 0);--color-neutral-500:oklch(55.6% 0 0);--color-neutral-600:oklch(43.9% 0 0);--color-neutral-700:oklch(37.1% 0 0);--color-neutral-800:oklch(26.9% 0 0);--color-neutral-900:oklch(20.5% 0 0);--color-neutral-950:oklch(14.5% 0 0);--color-stone-50:oklch(98.5% .001 106.423);--color-stone-100:oklch(97% .001 106.424);--color-stone-200:oklch(92.3% .003 48.717);--color-stone-300:oklch(86.9% .005 56.366);--color-stone-400:oklch(70.9% .01 56.259);--color-stone-500:oklch(55.3% .013 58.071);--color-stone-600:oklch(44.4% .011 73.639);--color-stone-700:oklch(37.4% .01 67.558);--color-stone-800:oklch(26.8% .007 34.298);--color-stone-900:oklch(21.6% .006 56.043);--color-stone-950:oklch(14.7% .004 49.25);--color-black:#000;--color-white:#fff;--spacing:.25rem;--breakpoint-sm:40rem;--breakpoint-md:48rem;--breakpoint-lg:64rem;--breakpoint-xl:80rem;--breakpoint-2xl:96rem;--container-3xs:16rem;--container-2xs:18rem;--container-xs:20rem;--container-sm:24rem;--container-md:28rem;--container-lg:32rem;--container-xl:36rem;--container-2xl:42rem;--container-3xl:48rem;--container-4xl:56rem;--container-5xl:64rem;--container-6xl:72rem;--container-7xl:80rem;--text-xs:.75rem;--text-xs--line-height:calc(1 / .75);--text-sm:.875rem;--text-sm--line-height:calc(1.25 / .875);--text-base:1rem;--text-base--line-height: 1.5 ;--text-lg:1.125rem;--text-lg--line-height:calc(1.75 / 1.125);--text-xl:1.25rem;--text-xl--line-height:calc(1.75 / 1.25);--text-2xl:1.5rem;--text-2xl--line-height:calc(2 / 1.5);--text-3xl:1.875rem;--text-3xl--line-height: 1.2 ;--text-4xl:2.25rem;--text-4xl--line-height:calc(2.5 / 2.25);--text-5xl:3rem;--text-5xl--line-height:1;--text-6xl:3.75rem;--text-6xl--line-height:1;--text-7xl:4.5rem;--text-7xl--line-height:1;--text-8xl:6rem;--text-8xl--line-height:1;--text-9xl:8rem;--text-9xl--line-height:1;--font-weight-thin:100;--font-weight-extralight:200;--font-weight-light:300;--font-weight-normal:400;--font-weight-medium:500;--font-weight-semibold:600;--font-weight-bold:700;--font-weight-extrabold:800;--font-weight-black:900;--tracking-tighter:-.05em;--tracking-tight:-.025em;--tracking-normal:0em;--tracking-wide:.025em;--tracking-wider:.05em;--tracking-widest:.1em;--leading-tight:1.25;--leading-snug:1.375;--leading-normal:1.5;--leading-relaxed:1.625;--leading-loose:2;--radius-xs:.125rem;--radius-sm:.25rem;--radius-md:.375rem;--radius-lg:.5rem;--radius-xl:.75rem;--radius-2xl:1rem;--radius-3xl:1.5rem;--radius-4xl:2rem;--shadow-2xs:0 1px #0000000d;--shadow-xs:0 1px 2px 0 #0000000d;--shadow-sm:0 1px 3px 0 #0000001a, 0 1px 2px -1px #0000001a;--shadow-md:0 4px 6px -1px #0000001a, 0 2px 4px -2px #0000001a;--shadow-lg:0 10px 15px -3px #0000001a, 0 4px 6px -4px #0000001a;--shadow-xl:0 20px 25px -5px #0000001a, 0 8px 10px -6px #0000001a;--shadow-2xl:0 25px 50px -12px #00000040;--inset-shadow-2xs:inset 0 1px #0000000d;--inset-shadow-xs:inset 0 1px 1px #0000000d;--inset-shadow-sm:inset 0 2px 4px #0000000d;--drop-shadow-xs:0 1px 1px #0000000d;--drop-shadow-sm:0 1px 2px #00000026;--drop-shadow-md:0 3px 3px #0000001f;--drop-shadow-lg:0 4px 4px #00000026;--drop-shadow-xl:0 9px 7px #0000001a;--drop-shadow-2xl:0 25px 25px #00000026;--ease-in:cubic-bezier(.4, 0, 1, 1);--ease-out:cubic-bezier(0, 0, .2, 1);--ease-in-out:cubic-bezier(.4, 0, .2, 1);--animate-spin:spin 1s linear infinite;--animate-ping:ping 1s cubic-bezier(0, 0, .2, 1) infinite;--animate-pulse:pulse 2s cubic-bezier(.4, 0, .6, 1) infinite;--animate-bounce:bounce 1s infinite;--blur-xs:4px;--blur-sm:8px;--blur-md:12px;--blur-lg:16px;--blur-xl:24px;--blur-2xl:40px;--blur-3xl:64px;--perspective-dramatic:100px;--perspective-near:300px;--perspective-normal:500px;--perspective-midrange:800px;--perspective-distant:1200px;--aspect-video:16 / 9;--default-transition-duration:.15s;--default-transition-timing-function:cubic-bezier(.4, 0, .2, 1);--default-font-family:var(--font-sans);--default-mono-font-family:var(--font-mono)}}@layer base{*,:after,:before,::backdrop{box-sizing:border-box;border:0 solid;margin:0;padding:0}::file-selector-button{box-sizing:border-box;border:0 solid;margin:0;padding:0}html,:host{-webkit-text-size-adjust:100%;tab-size:4;line-height:1.5;font-family:var(--default-font-family,ui-sans-serif, system-ui, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji");font-feature-settings:var(--default-font-feature-settings,normal);font-variation-settings:var(--default-font-variation-settings,normal);-webkit-tap-highlight-color:transparent}hr{height:0;color:inherit;border-top-width:1px}abbr:where([title]){-webkit-text-decoration:underline dotted;text-decoration:underline dotted}h1,h2,h3,h4,h5,h6{font-size:inherit;font-weight:inherit}a{color:inherit;-webkit-text-decoration:inherit;text-decoration:inherit}b,strong{font-weight:bolder}code,kbd,samp,pre{font-family:var(--default-mono-font-family,ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace);font-feature-settings:var(--default-mono-font-feature-settings,normal);font-variation-settings:var(--default-mono-font-variation-settings,normal);font-size:1em}small{font-size:80%}sub,sup{vertical-align:baseline;font-size:75%;line-height:0;position:relative}sub{bottom:-.25em}sup{top:-.5em}table{text-indent:0;border-color:inherit;border-collapse:collapse}:-moz-focusring{outline:auto}progress{vertical-align:baseline}summary{display:list-item}ol,ul,menu{list-style:none}img,svg,video,canvas,audio,iframe,embed,object{vertical-align:middle;display:block}img,video{max-width:100%;height:auto}button,input,select,optgroup,textarea{font:inherit;font-feature-settings:inherit;font-variation-settings:inherit;letter-spacing:inherit;color:inherit;opacity:1;background-color:#0000;border-radius:0}::file-selector-button{font:inherit;font-feature-settings:inherit;font-variation-settings:inherit;letter-spacing:inherit;color:inherit;opacity:1;background-color:#0000;border-radius:0}:where(select:is([multiple],[size])) optgroup{font-weight:bolder}:where(select:is([multiple],[size])) optgroup option{padding-inline-start:20px}::file-selector-button{margin-inline-end:4px}::placeholder{opacity:1}@supports (not ((-webkit-appearance:-apple-pay-button))) or (contain-intrinsic-size:1px){::placeholder{color:currentColor}@supports (color:color-mix(in lab,red,red)){::placeholder{color:color-mix(in oklab,currentcolor 50%,transparent)}}}textarea{resize:vertical}::-webkit-search-decoration{-webkit-appearance:none}::-webkit-date-and-time-value{min-height:1lh;text-align:inherit}::-webkit-datetime-edit{display:inline-flex}::-webkit-datetime-edit-fields-wrapper{padding:0}::-webkit-datetime-edit{padding-block:0}::-webkit-datetime-edit-year-field{padding-block:0}::-webkit-datetime-edit-month-field{padding-block:0}::-webkit-datetime-edit-day-field{padding-block:0}::-webkit-datetime-edit-hour-field{padding-block:0}::-webkit-datetime-edit-minute-field{padding-block:0}::-webkit-datetime-edit-second-field{padding-block:0}::-webkit-datetime-edit-millisecond-field{padding-block:0}::-webkit-datetime-edit-meridiem-field{padding-block:0}::-webkit-calendar-picker-indicator{line-height:1}:-moz-ui-invalid{box-shadow:none}button,input:where([type=button],[type=reset],[type=submit]){appearance:button}::file-selector-button{appearance:button}::-webkit-inner-spin-button{height:auto}::-webkit-outer-spin-button{height:auto}[hidden]:where(:not([hidden=until-found])){display:none!important}}@layer components;@layer utilities{.absolute{position:absolute}.fixed{position:fixed}.relative{position:relative}.static{position:static}.inset-0{inset:calc(var(--spacing) * 0)}.start{inset-inline-start:var(--spacing)}.top-0{top:calc(var(--spacing) * 0)}.right-0{right:calc(var(--spacing) * 0)}.container{width:100%}@media(min-width:40rem){.container{max-width:40rem}}@media(min-width:48rem){.container{max-width:48rem}}@media(min-width:64rem){.container{max-width:64rem}}@media(min-width:80rem){.container{max-width:80rem}}@media(min-width:96rem){.container{max-width:96rem}}.mx-auto{margin-inline:auto}.-mt-\[6\.6rem\]{margin-top:-6.6rem}.-mt-px{margin-top:-1px}.mt-2{margin-top:calc(var(--spacing) * 2)}.mt-4{margin-top:calc(var(--spacing) * 4)}.mt-6{margin-top:calc(var(--spacing) * 6)}.mt-8{margin-top:calc(var(--spacing) * 8)}.mr-2{margin-right:calc(var(--spacing) * 2)}.-mb-px{margin-bottom:-1px}.mb-1{margin-bottom:calc(var(--spacing) * 1)}.mb-2{margin-bottom:calc(var(--spacing) * 2)}.mb-4{margin-bottom:calc(var(--spacing) * 4)}.mb-6{margin-bottom:calc(var(--spacing) * 6)}.-ml-8{margin-left:calc(var(--spacing) * -8)}.-ml-px{margin-left:-1px}.ml-1{margin-left:calc(var(--spacing) * 1)}.ml-2{margin-left:calc(var(--spacing) * 2)}.ml-4{margin-left:calc(var(--spacing) * 4)}.ml-12{margin-left:calc(var(--spacing) * 12)}.contents{display:contents}.flex{display:flex}.grid{display:grid}.hidden{display:none}.inline-block{display:inline-block}.inline-flex{display:inline-flex}.table{display:table}.aspect-\[335\/364\]{aspect-ratio:335/364}.h-1{height:calc(var(--spacing) * 1)}.h-1\.5{height:calc(var(--spacing) * 1.5)}.h-2{height:calc(var(--spacing) * 2)}.h-2\.5{height:calc(var(--spacing) * 2.5)}.h-3{height:calc(var(--spacing) * 3)}.h-3\.5{height:calc(var(--spacing) * 3.5)}.h-5{height:calc(var(--spacing) * 5)}.h-8{height:calc(var(--spacing) * 8)}.h-14{height:calc(var(--spacing) * 14)}.h-14\.5{height:calc(var(--spacing) * 14.5)}.h-16{height:calc(var(--spacing) * 16)}.min-h-screen{min-height:100vh}.w-1{width:calc(var(--spacing) * 1)}.w-1\.5{width:calc(var(--spacing) * 1.5)}.w-2{width:calc(var(--spacing) * 2)}.w-2\.5{width:calc(var(--spacing) * 2.5)}.w-3{width:calc(var(--spacing) * 3)}.w-3\.5{width:calc(var(--spacing) * 3.5)}.w-5{width:calc(var(--spacing) * 5)}.w-8{width:calc(var(--spacing) * 8)}.w-\[438px\]{width:438px}.w-auto{width:auto}.w-full{width:100%}.max-w-6xl{max-width:var(--container-6xl)}.max-w-\[335px\]{max-width:335px}.max-w-none{max-width:none}.max-w-xl{max-width:var(--container-xl)}.flex-1{flex:1}.shrink-0{flex-shrink:0}.translate-y-0{--tw-translate-y:calc(var(--spacing) * 0);translate:var(--tw-translate-x) var(--tw-translate-y)}.transform{transform:var(--tw-rotate-x,) var(--tw-rotate-y,) var(--tw-rotate-z,) var(--tw-skew-x,) var(--tw-skew-y,)}.cursor-default{cursor:default}.cursor-not-allowed{cursor:not-allowed}.grid-cols-1{grid-template-columns:repeat(1,minmax(0,1fr))}.flex-col{flex-direction:column}.flex-col-reverse{flex-direction:column-reverse}.items-center{align-items:center}.justify-between{justify-content:space-between}.justify-center{justify-content:center}.justify-end{justify-content:flex-end}.justify-items-center{justify-items:center}.gap-2{gap:calc(var(--spacing) * 2)}.gap-3{gap:calc(var(--spacing) * 3)}.gap-4{gap:calc(var(--spacing) * 4)}:where(.space-x-1>:not(:last-child)){--tw-space-x-reverse:0;margin-inline-start:calc(calc(var(--spacing) * 1) * var(--tw-space-x-reverse));margin-inline-end:calc(calc(var(--spacing) * 1) * calc(1 - var(--tw-space-x-reverse)))}.overflow-hidden{overflow:hidden}.rounded-full{border-radius:3.40282e38px}.rounded-md{border-radius:var(--radius-md)}.rounded-sm{border-radius:var(--radius-sm)}.rounded-t-lg{border-top-left-radius:var(--radius-lg);border-top-right-radius:var(--radius-lg)}.rounded-l-md{border-top-left-radius:var(--radius-md);border-bottom-left-radius:var(--radius-md)}.rounded-r-md{border-top-right-radius:var(--radius-md);border-bottom-right-radius:var(--radius-md)}.rounded-br-lg{border-bottom-right-radius:var(--radius-lg)}.rounded-bl-lg{border-bottom-left-radius:var(--radius-lg)}.border{border-style:var(--tw-border-style);border-width:1px}.border-t{border-top-style:var(--tw-border-style);border-top-width:1px}.border-r{border-right-style:var(--tw-border-style);border-right-width:1px}.border-\[\#19140035\]{border-color:#19140035}.border-\[\#e3e3e0\]{border-color:#e3e3e0}.border-black{border-color:var(--color-black)}.border-gray-200{border-color:var(--color-gray-200)}.border-gray-300{border-color:var(--color-gray-300)}.border-gray-400{border-color:var(--color-gray-400)}.border-transparent{border-color:#0000}.bg-\[\#1b1b18\]{background-color:#1b1b18}.bg-\[\#FDFDFC\]{background-color:#fdfdfc}.bg-\[\#dbdbd7\]{background-color:#dbdbd7}.bg-\[\#fff2f2\]{background-color:#fff2f2}.bg-gray-100{background-color:var(--color-gray-100)}.bg-gray-200{background-color:var(--color-gray-200)}.bg-white{background-color:var(--color-white)}.p-6{padding:calc(var(--spacing) * 6)}.px-2{padding-inline:calc(var(--spacing) * 2)}.px-4{padding-inline:calc(var(--spacing) * 4)}.px-5{padding-inline:calc(var(--spacing) * 5)}.px-6{padding-inline:calc(var(--spacing) * 6)}.py-1{padding-block:calc(var(--spacing) * 1)}.py-1\.5{padding-block:calc(var(--spacing) * 1.5)}.py-2{padding-block:calc(var(--spacing) * 2)}.py-4{padding-block:calc(var(--spacing) * 4)}.pt-8{padding-top:calc(var(--spacing) * 8)}.pb-6{padding-bottom:calc(var(--spacing) * 6)}.pb-12{padding-bottom:calc(var(--spacing) * 12)}.text-center{text-align:center}.text-lg{font-size:var(--text-lg);line-height:var(--tw-leading,var(--text-lg--line-height))}.text-sm{font-size:var(--text-sm);line-height:var(--tw-leading,var(--text-sm--line-height))}.text-\[13px\]{font-size:13px}.leading-5{--tw-leading:calc(var(--spacing) * 5);line-height:calc(var(--spacing) * 5)}.leading-7{--tw-leading:calc(var(--spacing) * 7);line-height:calc(var(--spacing) * 7)}.leading-\[20px\]{--tw-leading:20px;line-height:20px}.leading-normal{--tw-leading:var(--leading-normal);line-height:var(--leading-normal)}.font-medium{--tw-font-weight:var(--font-weight-medium);font-weight:var(--font-weight-medium)}.font-semibold{--tw-font-weight:var(--font-weight-semibold);font-weight:var(--font-weight-semibold)}.tracking-wider{--tw-tracking:var(--tracking-wider);letter-spacing:var(--tracking-wider)}.text-\[\#1B1B18\],.text-\[\#1b1b18\]{color:#1b1b18}.text-\[\#706f6c\]{color:#706f6c}.text-\[\#F3BEC7\]{color:#f3bec7}.text-\[\#F8B803\]{color:#f8b803}.text-\[\#F53003\],.text-\[\#f53003\]{color:#f53003}.text-gray-200{color:var(--color-gray-200)}.text-gray-300{color:var(--color-gray-300)}.text-gray-400{color:var(--color-gray-400)}.text-gray-500{color:var(--color-gray-500)}.text-gray-600{color:var(--color-gray-600)}.text-gray-700{color:var(--color-gray-700)}.text-gray-800{color:var(--color-gray-800)}.text-gray-900{color:var(--color-gray-900)}.text-white{color:var(--color-white)}.uppercase{text-transform:uppercase}.underline{text-decoration-line:underline}.underline-offset-4{text-underline-offset:4px}.antialiased{-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}.opacity-100{opacity:1}.mix-blend-color{mix-blend-mode:color}.mix-blend-darken{mix-blend-mode:darken}.mix-blend-hard-light{mix-blend-mode:hard-light}.mix-blend-multiply{mix-blend-mode:multiply}.shadow{--tw-shadow:0 1px 3px 0 var(--tw-shadow-color,#0000001a), 0 1px 2px -1px var(--tw-shadow-color,#0000001a);box-shadow:var(--tw-inset-shadow),var(--tw-inset-ring-shadow),var(--tw-ring-offset-shadow),var(--tw-ring-shadow),var(--tw-shadow)}.shadow-\[0px_0px_1px_0px_rgba\(0\,0\,0\,0\.03\)\,0px_1px_2px_0px_rgba\(0\,0\,0\,0\.06\)\]{--tw-shadow:0px 0px 1px 0px var(--tw-shadow-color,#00000008), 0px 1px 2px 0px var(--tw-shadow-color,#0000000f);box-shadow:var(--tw-inset-shadow),var(--tw-inset-ring-shadow),var(--tw-ring-offset-shadow),var(--tw-ring-shadow),var(--tw-shadow)}.shadow-\[inset_0px_0px_0px_1px_rgba\(26\,26\,0\,0\.16\)\]{--tw-shadow:inset 0px 0px 0px 1px var(--tw-shadow-color,#1a1a0029);box-shadow:var(--tw-inset-shadow),var(--tw-inset-ring-shadow),var(--tw-ring-offset-shadow),var(--tw-ring-shadow),var(--tw-shadow)}.shadow-sm{--tw-shadow:0 1px 3px 0 var(--tw-shadow-color,#0000001a), 0 1px 2px -1px var(--tw-shadow-color,#0000001a);box-shadow:var(--tw-inset-shadow),var(--tw-inset-ring-shadow),var(--tw-ring-offset-shadow),var(--tw-ring-shadow),var(--tw-shadow)}.ring-gray-300{--tw-ring-color:var(--color-gray-300)}.filter{filter:var(--tw-blur,) var(--tw-brightness,) var(--tw-contrast,) var(--tw-grayscale,) var(--tw-hue-rotate,) var(--tw-invert,) var(--tw-saturate,) var(--tw-sepia,) var(--tw-drop-shadow,)}.transition{transition-property:color,background-color,border-color,outline-color,text-decoration-color,fill,stroke,--tw-gradient-from,--tw-gradient-via,--tw-gradient-to,opacity,box-shadow,transform,translate,scale,rotate,filter,-webkit-backdrop-filter,backdrop-filter,display,content-visibility,overlay,pointer-events;transition-timing-function:var(--tw-ease,var(--default-transition-timing-function));transition-duration:var(--tw-duration,var(--default-transition-duration))}.transition-all{transition-property:all;transition-timing-function:var(--tw-ease,var(--default-transition-timing-function));transition-duration:var(--tw-duration,var(--default-transition-duration))}.transition-opacity{transition-property:opacity;transition-timing-function:var(--tw-ease,var(--default-transition-timing-function));transition-duration:var(--tw-duration,var(--default-transition-duration))}.delay-200{transition-delay:.2s}.delay-300{transition-delay:.3s}.delay-400{transition-delay:.4s}.duration-150{--tw-duration:.15s;transition-duration:.15s}.duration-750{--tw-duration:.75s;transition-duration:.75s}.ease-in-out{--tw-ease:var(--ease-in-out);transition-timing-function:var(--ease-in-out)}.\[--stroke-color\:\#1B1B18\]{--stroke-color:#1b1b18}.not-has-\[nav\]\:hidden:not(:has(:is(nav))){display:none}.before\:absolute:before{content:var(--tw-content);position:absolute}.before\:top-0:before{content:var(--tw-content);top:calc(var(--spacing) * 0)}.before\:top-1\/2:before{content:var(--tw-content);top:50%}.before\:bottom-0:before{content:var(--tw-content);bottom:calc(var(--spacing) * 0)}.before\:bottom-1\/2:before{content:var(--tw-content);bottom:50%}.before\:left-\[0\.4rem\]:before{content:var(--tw-content);left:.4rem}.before\:border-l:before{content:var(--tw-content);border-left-style:var(--tw-border-style);border-left-width:1px}.before\:border-\[\#e3e3e0\]:before{content:var(--tw-content);border-color:#e3e3e0}@media(hover:hover){.hover\:border-\[\#1915014a\]:hover{border-color:#1915014a}.hover\:border-\[\#19140035\]:hover{border-color:#19140035}.hover\:border-black:hover{border-color:var(--color-black)}.hover\:bg-black:hover{background-color:var(--color-black)}.hover\:bg-gray-100:hover{background-color:var(--color-gray-100)}.hover\:text-gray-400:hover{color:var(--color-gray-400)}.hover\:text-gray-700:hover{color:var(--color-gray-700)}}.focus\:border-blue-300:focus{border-color:var(--color-blue-300)}.focus\:ring:focus{--tw-ring-shadow:var(--tw-ring-inset,) 0 0 0 calc(1px + var(--tw-ring-offset-width)) var(--tw-ring-color,currentcolor);box-shadow:var(--tw-inset-shadow),var(--tw-inset-ring-shadow),var(--tw-ring-offset-shadow),var(--tw-ring-shadow),var(--tw-shadow)}.focus\:outline-none:focus{--tw-outline-style:none;outline-style:none}.active\:bg-gray-100:active{background-color:var(--color-gray-100)}.active\:text-gray-500:active{color:var(--color-gray-500)}.active\:text-gray-700:active{color:var(--color-gray-700)}.active\:text-gray-800:active{color:var(--color-gray-800)}@media(min-width:40rem){.sm\:flex{display:flex}.sm\:hidden{display:none}.sm\:flex-1{flex:1}.sm\:items-center{align-items:center}.sm\:justify-between{justify-content:space-between}.sm\:justify-start{justify-content:flex-start}.sm\:gap-2{gap:calc(var(--spacing) * 2)}.sm\:px-6{padding-inline:calc(var(--spacing) * 6)}.sm\:pt-0{padding-top:calc(var(--spacing) * 0)}}@media(min-width:64rem){.lg\:mt-10{margin-top:calc(var(--spacing) * 10)}.lg\:mb-0{margin-bottom:calc(var(--spacing) * 0)}.lg\:mb-6{margin-bottom:calc(var(--spacing) * 6)}.lg\:-ml-px{margin-left:-1px}.lg\:ml-0{margin-left:calc(var(--spacing) * 0)}.lg\:block{display:block}.lg\:aspect-auto{aspect-ratio:auto}.lg\:w-\[438px\]{width:438px}.lg\:max-w-4xl{max-width:var(--container-4xl)}.lg\:grow{flex-grow:1}.lg\:flex-row{flex-direction:row}.lg\:justify-center{justify-content:center}.lg\:rounded-t-none{border-top-left-radius:0;border-top-right-radius:0}.lg\:rounded-tl-lg{border-top-left-radius:var(--radius-lg)}.lg\:rounded-r-lg{border-top-right-radius:var(--radius-lg);border-bottom-right-radius:var(--radius-lg)}.lg\:rounded-br-none{border-bottom-right-radius:0}.lg\:p-8{padding:calc(var(--spacing) * 8)}.lg\:p-20{padding:calc(var(--spacing) * 20)}.lg\:px-8{padding-inline:calc(var(--spacing) * 8)}.lg\:pb-10{padding-bottom:calc(var(--spacing) * 10)}}.rtl\:flex-row-reverse:where(:dir(rtl),[dir=rtl],[dir=rtl] *){flex-direction:row-reverse}@media(prefers-color-scheme:dark){.dark\:border-\[\#3E3E3A\]{border-color:#3e3e3a}.dark\:border-\[\#eeeeec\]{border-color:#eeeeec}.dark\:border-gray-600{border-color:var(--color-gray-600)}.dark\:bg-\[\#0a0a0a\]{background-color:#0a0a0a}.dark\:bg-\[\#1D0002\]{background-color:#1d0002}.dark\:bg-\[\#3E3E3A\]{background-color:#3e3e3a}.dark\:bg-\[\#161615\]{background-color:#161615}.dark\:bg-\[\#eeeeec\]{background-color:#eeeeec}.dark\:bg-gray-700{background-color:var(--color-gray-700)}.dark\:bg-gray-800{background-color:var(--color-gray-800)}.dark\:bg-gray-900{background-color:var(--color-gray-900)}.dark\:text-\[\#1C1C1A\]{color:#1c1c1a}.dark\:text-\[\#4B0600\]{color:#4b0600}.dark\:text-\[\#391800\]{color:#391800}.dark\:text-\[\#733000\]{color:#733000}.dark\:text-\[\#A1A09A\]{color:#a1a09a}.dark\:text-\[\#EDEDEC\]{color:#ededec}.dark\:text-\[\#F61500\]{color:#f61500}.dark\:text-\[\#FF4433\]{color:#f43}.dark\:text-black{color:var(--color-black)}.dark\:text-gray-200{color:var(--color-gray-200)}.dark\:text-gray-300{color:var(--color-gray-300)}.dark\:text-gray-400{color:var(--color-gray-400)}.dark\:text-gray-600{color:var(--color-gray-600)}.dark\:mix-blend-hard-light{mix-blend-mode:hard-light}.dark\:mix-blend-normal{mix-blend-mode:normal}.dark\:shadow-\[inset_0px_0px_0px_1px_\#fffaed2d\]{--tw-shadow:inset 0px 0px 0px 1px var(--tw-shadow-color,#fffaed2d);box-shadow:var(--tw-inset-shadow),var(--tw-inset-ring-shadow),var(--tw-ring-offset-shadow),var(--tw-ring-shadow),var(--tw-shadow)}.dark\:\[--stroke-color\:\#FF750F\]{--stroke-color:#ff750f}.dark\:before\:border-\[\#3E3E3A\]:before{content:var(--tw-content);border-color:#3e3e3a}@media(hover:hover){.dark\:hover\:border-\[\#3E3E3A\]:hover{border-color:#3e3e3a}.dark\:hover\:border-\[\#62605b\]:hover{border-color:#62605b}.dark\:hover\:border-white:hover{border-color:var(--color-white)}.dark\:hover\:bg-gray-900:hover{background-color:var(--color-gray-900)}.dark\:hover\:bg-white:hover{background-color:var(--color-white)}.dark\:hover\:text-gray-200:hover{color:var(--color-gray-200)}.dark\:hover\:text-gray-300:hover{color:var(--color-gray-300)}}.dark\:focus\:border-blue-700:focus{border-color:var(--color-blue-700)}.dark\:focus\:border-blue-800:focus{border-color:var(--color-blue-800)}.dark\:active\:bg-gray-700:active{background-color:var(--color-gray-700)}.dark\:active\:text-gray-300:active{color:var(--color-gray-300)}}@starting-style{.starting\:opacity-0{opacity:0}}@media(prefers-reduced-motion:no-preference){@starting-style{.motion-safe\:starting\:-translate-x-\[26px\]{--tw-translate-x: -26px ;translate:var(--tw-translate-x) var(--tw-translate-y)}}@starting-style{.motion-safe\:starting\:-translate-x-\[51px\]{--tw-translate-x: -51px ;translate:var(--tw-translate-x) var(--tw-translate-y)}}@starting-style{.motion-safe\:starting\:-translate-x-\[78px\]{--tw-translate-x: -78px ;translate:var(--tw-translate-x) var(--tw-translate-y)}}@starting-style{.motion-safe\:starting\:-translate-x-\[102px\]{--tw-translate-x: -102px ;translate:var(--tw-translate-x) var(--tw-translate-y)}}@starting-style{.motion-safe\:starting\:translate-y-6{--tw-translate-y:calc(var(--spacing) * 6);translate:var(--tw-translate-x) var(--tw-translate-y)}}}}@property --tw-translate-x{syntax:"*";inherits:false;initial-value:0}@property --tw-translate-y{syntax:"*";inherits:false;initial-value:0}@property --tw-translate-z{syntax:"*";inherits:false;initial-value:0}@property --tw-rotate-x{syntax:"*";inherits:false}@property --tw-rotate-y{syntax:"*";inherits:false}@property --tw-rotate-z{syntax:"*";inherits:false}@property --tw-skew-x{syntax:"*";inherits:false}@property --tw-skew-y{syntax:"*";inherits:false}@property --tw-space-x-reverse{syntax:"*";inherits:false;initial-value:0}@property --tw-border-style{syntax:"*";inherits:false;initial-value:solid}@property --tw-leading{syntax:"*";inherits:false}@property --tw-font-weight{syntax:"*";inherits:false}@property --tw-tracking{syntax:"*";inherits:false}@property --tw-shadow{syntax:"*";inherits:false;initial-value:0 0 #0000}@property --tw-shadow-color{syntax:"*";inherits:false}@property --tw-shadow-alpha{syntax:"<percentage>";inherits:false;initial-value:100%}@property --tw-inset-shadow{syntax:"*";inherits:false;initial-value:0 0 #0000}@property --tw-inset-shadow-color{syntax:"*";inherits:false}@property --tw-inset-shadow-alpha{syntax:"<percentage>";inherits:false;initial-value:100%}@property --tw-ring-color{syntax:"*";inherits:false}@property --tw-ring-shadow{syntax:"*";inherits:false;initial-value:0 0 #0000}@property --tw-inset-ring-color{syntax:"*";inherits:false}@property --tw-inset-ring-shadow{syntax:"*";inherits:false;initial-value:0 0 #0000}@property --tw-ring-inset{syntax:"*";inherits:false}@property --tw-ring-offset-width{syntax:"<length>";inherits:false;initial-value:0}@property --tw-ring-offset-color{syntax:"*";inherits:false;initial-value:#fff}@property --tw-ring-offset-shadow{syntax:"*";inherits:false;initial-value:0 0 #0000}@property --tw-blur{syntax:"*";inherits:false}@property --tw-brightness{syntax:"*";inherits:false}@property --tw-contrast{syntax:"*";inherits:false}@property --tw-grayscale{syntax:"*";inherits:false}@property --tw-hue-rotate{syntax:"*";inherits:false}@property --tw-invert{syntax:"*";inherits:false}@property --tw-opacity{syntax:"*";inherits:false}@property --tw-saturate{syntax:"*";inherits:false}@property --tw-sepia{syntax:"*";inherits:false}@property --tw-drop-shadow{syntax:"*";inherits:false}@property --tw-drop-shadow-color{syntax:"*";inherits:false}@property --tw-drop-shadow-alpha{syntax:"<percentage>";inherits:false;initial-value:100%}@property --tw-drop-shadow-size{syntax:"*";inherits:false}@property --tw-duration{syntax:"*";inherits:false}@property --tw-ease{syntax:"*";inherits:false}@property --tw-content{syntax:"*";inherits:false;initial-value:""}@keyframes spin{to{transform:rotate(360deg)}}@keyframes ping{75%,to{opacity:0;transform:scale(2)}}@keyframes pulse{50%{opacity:.5}}@keyframes bounce{0%,to{animation-timing-function:cubic-bezier(.8,0,1,1);transform:translateY(-25%)}50%{animation-timing-function:cubic-bezier(0,0,.2,1);transform:none}}
            </style>
        @endif
    </head>
    <body class="bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] flex p-6 lg:p-8 items-center lg:justify-center min-h-screen flex-col">
        <header class="w-full lg:max-w-4xl max-w-[335px] text-sm mb-6 not-has-[nav]:hidden">
            @if (Route::has('login'))
                <nav class="flex items-center justify-end gap-4">
                    @auth
                        <a
                            href="{{ url('/dashboard') }}"
                            class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] border-[#19140035] hover:border-[#1915014a] border text-[#1b1b18] dark:border-[#3E3E3A] dark:hover:border-[#62605b] rounded-sm text-sm leading-normal"
                        >
                            Dashboard
                        </a>
                    @else
                        <a
                            href="{{ route('login') }}"
                            class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] text-[#1b1b18] border border-transparent hover:border-[#19140035] dark:hover:border-[#3E3E3A] rounded-sm text-sm leading-normal"
                        >
                            Log in
                        </a>

                        @if (Route::has('register'))
                            <a
                                href="{{ route('register') }}"
                                class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] border-[#19140035] hover:border-[#1915014a] border text-[#1b1b18] dark:border-[#3E3E3A] dark:hover:border-[#62605b] rounded-sm text-sm leading-normal">
                                Register
                            </a>
                        @endif
                    @endauth
                </nav>
            @endif
        </header>
        <div class="flex items-center justify-center w-full transition-opacity opacity-100 duration-750 lg:grow starting:opacity-0">
            <main class="flex max-w-[335px] w-full flex-col-reverse lg:max-w-4xl lg:flex-row">
                <div class="text-[13px] leading-[20px] flex-1 p-6 pb-6 lg:p-20 lg:pb-10 bg-white dark:bg-[#161615] dark:text-[#EDEDEC] shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d] rounded-bl-lg rounded-br-lg lg:rounded-tl-lg lg:rounded-br-none">
                    <h1 class="mb-1 font-medium">Let's get started</h1>
                    <p class="mb-2 text-[#706f6c] dark:text-[#A1A09A]">With so many options available to you,<br /> we suggest you start with the following:</p>
                    <ul class="flex flex-col mb-4 lg:mb-6">
                        <li class="flex items-center gap-4 py-2 relative before:border-l before:border-[#e3e3e0] dark:before:border-[#3E3E3A] before:top-1/2 before:bottom-0 before:left-[0.4rem] before:absolute">
                            <span class="relative py-1 bg-white dark:bg-[#161615]">
                                <span class="flex items-center justify-center rounded-full bg-[#FDFDFC] dark:bg-[#161615] shadow-[0px_0px_1px_0px_rgba(0,0,0,0.03),0px_1px_2px_0px_rgba(0,0,0,0.06)] w-3.5 h-3.5 border dark:border-[#3E3E3A] border-[#e3e3e0]">
                                    <span class="rounded-full bg-[#dbdbd7] dark:bg-[#3E3E3A] w-1.5 h-1.5"></span>
                                </span>
                            </span>
                            <span>
                                Read the
                                <a href="https://laravel.com/docs" target="_blank" class="inline-flex items-center space-x-1 font-medium underline underline-offset-4 text-[#f53003] dark:text-[#FF4433] ml-1">
                                    <span>Documentation</span>
                                    <svg
                                        width="10"
                                        height="11"
                                        viewBox="0 0 10 11"
                                        fill="none"
                                        xmlns="http://www.w3.org/2000/svg"
                                        class="w-2.5 h-2.5"
                                    >
                                        <path
                                            d="M7.70833 6.95834V2.79167H3.54167M2.5 8L7.5 3.00001"
                                            stroke="currentColor"
                                            stroke-linecap="square"
                                        />
                                    </svg>
                                </a>
                            </span>
                        </li>
                        <li class="flex items-center gap-4 py-2 relative before:border-l before:border-[#e3e3e0] dark:before:border-[#3E3E3A] before:bottom-1/2 before:top-0 before:left-[0.4rem] before:absolute">
                            <span class="relative py-1 bg-white dark:bg-[#161615]">
                                <span class="flex items-center justify-center rounded-full bg-[#FDFDFC] dark:bg-[#161615] shadow-[0px_0px_1px_0px_rgba(0,0,0,0.03),0px_1px_2px_0px_rgba(0,0,0,0.06)] w-3.5 h-3.5 border dark:border-[#3E3E3A] border-[#e3e3e0]">
                                    <span class="rounded-full bg-[#dbdbd7] dark:bg-[#3E3E3A] w-1.5 h-1.5"></span>
                                </span>
                            </span>
                            <span>
                                Watch video tutorials at
                                <a href="https://laracasts.com" target="_blank" class="inline-flex items-center space-x-1 font-medium underline underline-offset-4 text-[#f53003] dark:text-[#FF4433] ml-1">
                                    <span>Laracasts</span>
                                    <svg
                                        width="10"
                                        height="11"
                                        viewBox="0 0 10 11"
                                        fill="none"
                                        xmlns="http://www.w3.org/2000/svg"
                                        class="w-2.5 h-2.5"
                                    >
                                        <path
                                            d="M7.70833 6.95834V2.79167H3.54167M2.5 8L7.5 3.00001"
                                            stroke="currentColor"
                                            stroke-linecap="square"
                                        />
                                    </svg>
                                </a>
                            </span>
                        </li>
                    </ul>
                    <ul class="flex gap-3 text-sm leading-normal">
                        <li>
                            <a href="https://cloud.laravel.com" target="_blank" class="inline-block dark:bg-[#eeeeec] dark:border-[#eeeeec] dark:text-[#1C1C1A] dark:hover:bg-white dark:hover:border-white hover:bg-black hover:border-black px-5 py-1.5 bg-[#1b1b18] rounded-sm border border-black text-white text-sm leading-normal">
                                Deploy now
                            </a>
                        </li>
                    </ul>

                    <p class="mt-6 lg:mt-10 text-[#706f6c] dark:text-[#A1A09A]">
                        v{{ app()->version() }}
                        <a href="https://github.com/laravel/framework/blob/13.x/CHANGELOG.md" target="_blank" class="inline-flex items-center space-x-1 font-medium underline underline-offset-4 text-[#f53003] dark:text-[#FF4433] ml-1">
                            <span>View changelog</span>
                            <svg
                                width="10"
                                height="11"
                                viewBox="0 0 10 11"
                                fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                                class="w-2.5 h-2.5"
                            >
                                <path
                                    d="M7.70833 6.95834V2.79167H3.54167M2.5 8L7.5 3.00001"
                                    stroke="currentColor"
                                    stroke-linecap="square"
                                />
                            </svg>
                        </a>
                    </p>
                </div>
                <div class="bg-[#fff2f2] dark:bg-[#1D0002] relative lg:-ml-px -mb-px lg:mb-0 rounded-t-lg lg:rounded-t-none lg:rounded-r-lg aspect-[335/364] lg:aspect-auto w-full lg:w-[438px] shrink-0 overflow-hidden">
                    {{-- Laravel Logo --}}
                    <svg class="w-full text-[#F53003] dark:text-[#F61500] transition-all translate-y-0 opacity-100 max-w-none duration-750 starting:opacity-0 motion-safe:starting:translate-y-6" viewBox="0 0 438 104" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M17.2036 -3H0V102.197H49.5189V86.7187H17.2036V-3Z" fill="currentColor" />
                        <path d="M110.256 41.6337C108.061 38.1275 104.945 35.3731 100.905 33.3681C96.8667 31.3647 92.8016 30.3618 88.7131 30.3618C83.4247 30.3618 78.5885 31.3389 74.201 33.2923C69.8111 35.2456 66.0474 37.928 62.9059 41.3333C59.7643 44.7401 57.3198 48.6726 55.5754 53.1293C53.8287 57.589 52.9572 62.274 52.9572 67.1813C52.9572 72.1925 53.8287 76.8995 55.5754 81.3069C57.3191 85.7173 59.7636 89.6241 62.9059 93.0293C66.0474 96.4361 69.8119 99.1155 74.201 101.069C78.5885 103.022 83.4247 103.999 88.7131 103.999C92.8016 103.999 96.8667 102.997 100.905 100.994C104.945 98.9911 108.061 96.2359 110.256 92.7282V102.195H126.563V32.1642H110.256V41.6337ZM108.76 75.7472C107.762 78.4531 106.366 80.8078 104.572 82.8112C102.776 84.8161 100.606 86.4183 98.0637 87.6206C95.5202 88.823 92.7004 89.4238 89.6103 89.4238C86.5178 89.4238 83.7252 88.823 81.2324 87.6206C78.7388 86.4183 76.5949 84.8161 74.7998 82.8112C73.004 80.8078 71.6319 78.4531 70.6856 75.7472C69.7356 73.0421 69.2644 70.1868 69.2644 67.1821C69.2644 64.1758 69.7356 61.3205 70.6856 58.6154C71.6319 55.9102 73.004 53.5571 74.7998 51.5522C76.5949 49.5495 78.738 47.9451 81.2324 46.7427C83.7252 45.5404 86.5178 44.9396 89.6103 44.9396C92.7012 44.9396 95.5202 45.5404 98.0637 46.7427C100.606 47.9451 102.776 49.5487 104.572 51.5522C106.367 53.5571 107.762 55.9102 108.76 58.6154C109.756 61.3205 110.256 64.1758 110.256 67.1821C110.256 70.1868 109.756 73.0421 108.76 75.7472Z" fill="currentColor" />
                        <path d="M242.805 41.6337C240.611 38.1275 237.494 35.3731 233.455 33.3681C229.416 31.3647 225.351 30.3618 221.262 30.3618C215.974 30.3618 211.138 31.3389 206.75 33.2923C202.36 35.2456 198.597 37.928 195.455 41.3333C192.314 44.7401 189.869 48.6726 188.125 53.1293C186.378 57.589 185.507 62.274 185.507 67.1813C185.507 72.1925 186.378 76.8995 188.125 81.3069C189.868 85.7173 192.313 89.6241 195.455 93.0293C198.597 96.4361 202.361 99.1155 206.75 101.069C211.138 103.022 215.974 103.999 221.262 103.999C225.351 103.999 229.416 102.997 233.455 100.994C237.494 98.9911 240.611 96.2359 242.805 92.7282V102.195H259.112V32.1642H242.805V41.6337ZM241.31 75.7472C240.312 78.4531 238.916 80.8078 237.122 82.8112C235.326 84.8161 233.156 86.4183 230.614 87.6206C228.07 88.823 225.251 89.4238 222.16 89.4238C219.068 89.4238 216.275 88.823 213.782 87.6206C211.289 86.4183 209.145 84.8161 207.35 82.8112C205.554 80.8078 204.182 78.4531 203.236 75.7472C202.286 73.0421 201.814 70.1868 201.814 67.1821C201.814 64.1758 202.286 61.3205 203.236 58.6154C204.182 55.9102 205.554 53.5571 207.35 51.5522C209.145 49.5495 211.288 47.9451 213.782 46.7427C216.275 45.5404 219.068 44.9396 222.16 44.9396C225.251 44.9396 228.07 45.5404 230.614 46.7427C233.156 47.9451 235.326 49.5487 237.122 51.5522C238.917 53.5571 240.312 55.9102 241.31 58.6154C242.306 61.3205 242.806 64.1758 242.806 67.1821C242.805 70.1868 242.305 73.0421 241.31 75.7472Z" fill="currentColor" />
                        <path d="M438 -3H421.694V102.197H438V-3Z" fill="currentColor" />
                        <path d="M139.43 102.197H155.735V48.2834H183.712V32.1665H139.43V102.197Z" fill="currentColor" />
                        <path d="M324.49 32.1665L303.995 85.794L283.498 32.1665H266.983L293.748 102.197H314.242L341.006 32.1665H324.49Z" fill="currentColor" />
                        <path d="M376.571 30.3656C356.603 30.3656 340.797 46.8497 340.797 67.1828C340.797 89.6597 356.094 104 378.661 104C391.29 104 399.354 99.1488 409.206 88.5848L398.189 80.0226C398.183 80.031 389.874 90.9895 377.468 90.9895C363.048 90.9895 356.977 79.3111 356.977 73.269H411.075C413.917 50.1328 398.775 30.3656 376.571 30.3656ZM357.02 61.0967C357.145 59.7487 359.023 43.3761 376.442 43.3761C393.861 43.3761 395.978 59.7464 396.099 61.0967H357.02Z" fill="currentColor" />
                    </svg>

                    {{-- 13 --}}
                    <svg class="w-[438px] max-w-none relative -mt-[6.6rem] -ml-8 lg:ml-0 [--stroke-color:#1B1B18] dark:[--stroke-color:#FF750F]" viewBox="0 0 440 392" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <g class="mix-blend-darken dark:mix-blend-normal transition-all delay-300 opacity-100 duration-750 starting:opacity-0 text-[#1B1B18] dark:text-black">
                            <mask id="path-1-mask" maskUnits="userSpaceOnUse" x="-0.328613" y="103" width="338" height="299" fill="black">
                                <rect fill="white" x="-0.328613" y="103" width="338" height="299"/>
                                <path d="M234.936 400.8C204.136 400.8 178.936 392.4 159.336 375.6C140.136 358.8 130.536 337 130.536 310.2H200.736C200.736 318.2 203.736 324.8 209.736 330C215.736 335.2 223.736 337.8 233.736 337.8C243.336 337.8 251.136 335 257.136 329.4C263.536 323.8 266.736 316.6 266.736 307.8C266.736 299.8 263.936 293.2 258.336 288C252.736 282.8 245.536 280.2 236.736 280.2H199.536V218.4H236.736C243.536 218.4 249.336 216 254.136 211.2C258.936 206.4 261.336 200.4 261.336 193.2C261.336 184.8 258.736 178.2 253.536 173.4C248.336 168.6 241.736 166.2 233.736 166.2C226.536 166.2 220.336 168.4 215.136 172.8C210.336 177.2 207.936 182.8 207.936 189.6H141.336C141.336 164.8 150.136 144.6 167.736 129C185.336 113 207.936 105 235.536 105C263.136 105 285.536 112.2 302.736 126.6C320.336 141 329.136 160 329.136 183.6C329.136 200.8 324.536 214.8 315.336 225.6C306.136 236 294.336 243.2 279.936 247.2C297.136 252 310.736 260.2 320.736 271.8C331.136 283.4 336.336 298 336.336 315.6C336.336 340.4 326.936 360.8 308.136 376.8C289.336 392.8 264.936 400.8 234.936 400.8Z"/>
                                <path d="M26.8714 167.6H1.67139V105.2H94.6714V400.2H26.8714V167.6Z"/>
                            </mask>
                            <path d="M234.936 400.8C204.136 400.8 178.936 392.4 159.336 375.6C140.136 358.8 130.536 337 130.536 310.2H200.736C200.736 318.2 203.736 324.8 209.736 330C215.736 335.2 223.736 337.8 233.736 337.8C243.336 337.8 251.136 335 257.136 329.4C263.536 323.8 266.736 316.6 266.736 307.8C266.736 299.8 263.936 293.2 258.336 288C252.736 282.8 245.536 280.2 236.736 280.2H199.536V218.4H236.736C243.536 218.4 249.336 216 254.136 211.2C258.936 206.4 261.336 200.4 261.336 193.2C261.336 184.8 258.736 178.2 253.536 173.4C248.336 168.6 241.736 166.2 233.736 166.2C226.536 166.2 220.336 168.4 215.136 172.8C210.336 177.2 207.936 182.8 207.936 189.6H141.336C141.336 164.8 150.136 144.6 167.736 129C185.336 113 207.936 105 235.536 105C263.136 105 285.536 112.2 302.736 126.6C320.336 141 329.136 160 329.136 183.6C329.136 200.8 324.536 214.8 315.336 225.6C306.136 236 294.336 243.2 279.936 247.2C297.136 252 310.736 260.2 320.736 271.8C331.136 283.4 336.336 298 336.336 315.6C336.336 340.4 326.936 360.8 308.136 376.8C289.336 392.8 264.936 400.8 234.936 400.8Z" fill="currentColor"/>
                            <path d="M26.8714 167.6H1.67139V105.2H94.6714V400.2H26.8714V167.6Z" fill="currentColor"/>
                            <path d="M234.936 400.8C204.136 400.8 178.936 392.4 159.336 375.6C140.136 358.8 130.536 337 130.536 310.2H200.736C200.736 318.2 203.736 324.8 209.736 330C215.736 335.2 223.736 337.8 233.736 337.8C243.336 337.8 251.136 335 257.136 329.4C263.536 323.8 266.736 316.6 266.736 307.8C266.736 299.8 263.936 293.2 258.336 288C252.736 282.8 245.536 280.2 236.736 280.2H199.536V218.4H236.736C243.536 218.4 249.336 216 254.136 211.2C258.936 206.4 261.336 200.4 261.336 193.2C261.336 184.8 258.736 178.2 253.536 173.4C248.336 168.6 241.736 166.2 233.736 166.2C226.536 166.2 220.336 168.4 215.136 172.8C210.336 177.2 207.936 182.8 207.936 189.6H141.336C141.336 164.8 150.136 144.6 167.736 129C185.336 113 207.936 105 235.536 105C263.136 105 285.536 112.2 302.736 126.6C320.336 141 329.136 160 329.136 183.6C329.136 200.8 324.536 214.8 315.336 225.6C306.136 236 294.336 243.2 279.936 247.2C297.136 252 310.736 260.2 320.736 271.8C331.136 283.4 336.336 298 336.336 315.6C336.336 340.4 326.936 360.8 308.136 376.8C289.336 392.8 264.936 400.8 234.936 400.8Z" stroke="var(--stroke-color)" stroke-width="2.4" mask="url(#path-1-mask)"/>
                            <path d="M26.8714 167.6H1.67139V105.2H94.6714V400.2H26.8714V167.6Z" stroke="var(--stroke-color)" stroke-width="2.4" mask="url(#path-1-mask)"/>
                        </g>

                        <g class="transition-all delay-400 opacity-100 duration-750 starting:opacity-0 motion-safe:starting:-translate-x-[26px] text-[#F3BEC7] dark:text-[#4B0600]">
                            <mask id="path-2-mask" maskUnits="userSpaceOnUse" x="25.3357" y="103" width="338" height="299" fill="black">
                                <rect fill="white" x="25.3357" y="103" width="338" height="299"/>
                                <path d="M260.6 400.8C229.8 400.8 204.6 392.4 185 375.6C165.8 358.8 156.2 337 156.2 310.2H226.4C226.4 318.2 229.4 324.8 235.4 330C241.4 335.2 249.4 337.8 259.4 337.8C269 337.8 276.8 335 282.8 329.4C289.2 323.8 292.4 316.6 292.4 307.8C292.4 299.8 289.6 293.2 284 288C278.4 282.8 271.2 280.2 262.4 280.2H225.2V218.4H262.4C269.2 218.4 275 216 279.8 211.2C284.6 206.4 287 200.4 287 193.2C287 184.8 284.4 178.2 279.2 173.4C274 168.6 267.4 166.2 259.4 166.2C252.2 166.2 246 168.4 240.8 172.8C236 177.2 233.6 182.8 233.6 189.6H167C167 164.8 175.8 144.6 193.4 129C211 113 233.6 105 261.2 105C288.8 105 311.2 112.2 328.4 126.6C346 141 354.8 160 354.8 183.6C354.8 200.8 350.2 214.8 341 225.6C331.8 236 320 243.2 305.6 247.2C322.8 252 336.4 260.2 346.4 271.8C356.8 283.4 362 298 362 315.6C362 340.4 352.6 360.8 333.8 376.8C315 392.8 290.6 400.8 260.6 400.8Z"/>
                                <path d="M52.5357 167.6H27.3357V105.2H120.336V400.2H52.5357V167.6Z"/>
                            </mask>
                            <path d="M260.6 400.8C229.8 400.8 204.6 392.4 185 375.6C165.8 358.8 156.2 337 156.2 310.2H226.4C226.4 318.2 229.4 324.8 235.4 330C241.4 335.2 249.4 337.8 259.4 337.8C269 337.8 276.8 335 282.8 329.4C289.2 323.8 292.4 316.6 292.4 307.8C292.4 299.8 289.6 293.2 284 288C278.4 282.8 271.2 280.2 262.4 280.2H225.2V218.4H262.4C269.2 218.4 275 216 279.8 211.2C284.6 206.4 287 200.4 287 193.2C287 184.8 284.4 178.2 279.2 173.4C274 168.6 267.4 166.2 259.4 166.2C252.2 166.2 246 168.4 240.8 172.8C236 177.2 233.6 182.8 233.6 189.6H167C167 164.8 175.8 144.6 193.4 129C211 113 233.6 105 261.2 105C288.8 105 311.2 112.2 328.4 126.6C346 141 354.8 160 354.8 183.6C354.8 200.8 350.2 214.8 341 225.6C331.8 236 320 243.2 305.6 247.2C322.8 252 336.4 260.2 346.4 271.8C356.8 283.4 362 298 362 315.6C362 340.4 352.6 360.8 333.8 376.8C315 392.8 290.6 400.8 260.6 400.8Z" fill="currentColor"/>
                            <path d="M52.5357 167.6H27.3357V105.2H120.336V400.2H52.5357V167.6Z" fill="currentColor"/>
                            <path d="M260.6 400.8C229.8 400.8 204.6 392.4 185 375.6C165.8 358.8 156.2 337 156.2 310.2H226.4C226.4 318.2 229.4 324.8 235.4 330C241.4 335.2 249.4 337.8 259.4 337.8C269 337.8 276.8 335 282.8 329.4C289.2 323.8 292.4 316.6 292.4 307.8C292.4 299.8 289.6 293.2 284 288C278.4 282.8 271.2 280.2 262.4 280.2H225.2V218.4H262.4C269.2 218.4 275 216 279.8 211.2C284.6 206.4 287 200.4 287 193.2C287 184.8 284.4 178.2 279.2 173.4C274 168.6 267.4 166.2 259.4 166.2C252.2 166.2 246 168.4 240.8 172.8C236 177.2 233.6 182.8 233.6 189.6H167C167 164.8 175.8 144.6 193.4 129C211 113 233.6 105 261.2 105C288.8 105 311.2 112.2 328.4 126.6C346 141 354.8 160 354.8 183.6C354.8 200.8 350.2 214.8 341 225.6C331.8 236 320 243.2 305.6 247.2C322.8 252 336.4 260.2 346.4 271.8C356.8 283.4 362 298 362 315.6C362 340.4 352.6 360.8 333.8 376.8C315 392.8 290.6 400.8 260.6 400.8Z" stroke="var(--stroke-color)" stroke-width="2.4" mask="url(#path-2-mask)"/>
                            <path d="M52.5357 167.6H27.3357V105.2H120.336V400.2H52.5357V167.6Z" stroke="var(--stroke-color)" stroke-width="2.4" mask="url(#path-2-mask)"/>
                        </g>
                        
                        <g class="mix-blend-color dark:mix-blend-hard-light transition-all delay-400 opacity-100 duration-750 starting:opacity-0 motion-safe:starting:-translate-x-[51px] text-[#F8B803] dark:text-[#391800]">
                            <mask id="path-3-mask" maskUnits="userSpaceOnUse" x="51" y="103" width="338" height="299" fill="black">
                                <rect fill="white" x="51" y="103" width="338" height="299"/>
                                <path d="M286.264 400.8C255.464 400.8 230.264 392.4 210.664 375.6C191.464 358.8 181.864 337 181.864 310.2H252.064C252.064 318.2 255.064 324.8 261.064 330C267.064 335.2 275.064 337.8 285.064 337.8C294.664 337.8 302.464 335 308.464 329.4C314.864 323.8 318.064 316.6 318.064 307.8C318.064 299.8 315.264 293.2 309.664 288C304.064 282.8 296.864 280.2 288.064 280.2H250.864V218.4H288.064C294.864 218.4 300.664 216 305.464 211.2C310.264 206.4 312.664 200.4 312.664 193.2C312.664 184.8 310.064 178.2 304.864 173.4C299.664 168.6 293.064 166.2 285.064 166.2C277.864 166.2 271.664 168.4 266.464 172.8C261.664 177.2 259.264 182.8 259.264 189.6H192.664C192.664 164.8 201.464 144.6 219.064 129C236.664 113 259.264 105 286.864 105C314.464 105 336.864 112.2 354.064 126.6C371.664 141 380.464 160 380.464 183.6C380.464 200.8 375.864 214.8 366.664 225.6C357.464 236 345.664 243.2 331.264 247.2C348.464 252 362.064 260.2 372.064 271.8C382.464 283.4 387.664 298 387.664 315.6C387.664 340.4 378.264 360.8 359.464 376.8C340.664 392.8 316.264 400.8 286.264 400.8Z"/>
                                <path d="M78.2 167.6H53V105.2H146V400.2H78.2V167.6Z"/>
                            </mask>
                            <path d="M286.264 400.8C255.464 400.8 230.264 392.4 210.664 375.6C191.464 358.8 181.864 337 181.864 310.2H252.064C252.064 318.2 255.064 324.8 261.064 330C267.064 335.2 275.064 337.8 285.064 337.8C294.664 337.8 302.464 335 308.464 329.4C314.864 323.8 318.064 316.6 318.064 307.8C318.064 299.8 315.264 293.2 309.664 288C304.064 282.8 296.864 280.2 288.064 280.2H250.864V218.4H288.064C294.864 218.4 300.664 216 305.464 211.2C310.264 206.4 312.664 200.4 312.664 193.2C312.664 184.8 310.064 178.2 304.864 173.4C299.664 168.6 293.064 166.2 285.064 166.2C277.864 166.2 271.664 168.4 266.464 172.8C261.664 177.2 259.264 182.8 259.264 189.6H192.664C192.664 164.8 201.464 144.6 219.064 129C236.664 113 259.264 105 286.864 105C314.464 105 336.864 112.2 354.064 126.6C371.664 141 380.464 160 380.464 183.6C380.464 200.8 375.864 214.8 366.664 225.6C357.464 236 345.664 243.2 331.264 247.2C348.464 252 362.064 260.2 372.064 271.8C382.464 283.4 387.664 298 387.664 315.6C387.664 340.4 378.264 360.8 359.464 376.8C340.664 392.8 316.264 400.8 286.264 400.8Z" fill="currentColor"/>
                            <path d="M78.2 167.6H53V105.2H146V400.2H78.2V167.6Z" fill="currentColor"/>
                            <path d="M286.264 400.8C255.464 400.8 230.264 392.4 210.664 375.6C191.464 358.8 181.864 337 181.864 310.2H252.064C252.064 318.2 255.064 324.8 261.064 330C267.064 335.2 275.064 337.8 285.064 337.8C294.664 337.8 302.464 335 308.464 329.4C314.864 323.8 318.064 316.6 318.064 307.8C318.064 299.8 315.264 293.2 309.664 288C304.064 282.8 296.864 280.2 288.064 280.2H250.864V218.4H288.064C294.864 218.4 300.664 216 305.464 211.2C310.264 206.4 312.664 200.4 312.664 193.2C312.664 184.8 310.064 178.2 304.864 173.4C299.664 168.6 293.064 166.2 285.064 166.2C277.864 166.2 271.664 168.4 266.464 172.8C261.664 177.2 259.264 182.8 259.264 189.6H192.664C192.664 164.8 201.464 144.6 219.064 129C236.664 113 259.264 105 286.864 105C314.464 105 336.864 112.2 354.064 126.6C371.664 141 380.464 160 380.464 183.6C380.464 200.8 375.864 214.8 366.664 225.6C357.464 236 345.664 243.2 331.264 247.2C348.464 252 362.064 260.2 372.064 271.8C382.464 283.4 387.664 298 387.664 315.6C387.664 340.4 378.264 360.8 359.464 376.8C340.664 392.8 316.264 400.8 286.264 400.8Z" stroke="var(--stroke-color)" stroke-width="2.4" mask="url(#path-3-mask)"/>
                            <path d="M78.2 167.6H53V105.2H146V400.2H78.2V167.6Z" stroke="var(--stroke-color)" stroke-width="2.4" mask="url(#path-3-mask)"/>
                        </g>
                        
                        <g class="mix-blend-multiply dark:mix-blend-normal transition-all delay-400 opacity-100 duration-750 starting:opacity-0 motion-safe:starting:-translate-x-[78px] text-[#F3BEC7] dark:text-[#733000]">
                            <mask id="path-4-mask" maskUnits="userSpaceOnUse" x="76.6643" y="103" width="338" height="299" fill="black">
                                <rect fill="white" x="76.6643" y="103" width="338" height="299"/>
                                <path d="M311.929 400.8C281.129 400.8 255.929 392.4 236.329 375.6C217.129 358.8 207.529 337 207.529 310.2H277.729C277.729 318.2 280.729 324.8 286.729 330C292.729 335.2 300.729 337.8 310.729 337.8C320.329 337.8 328.129 335 334.129 329.4C340.529 323.8 343.729 316.6 343.729 307.8C343.729 299.8 340.929 293.2 335.329 288C329.729 282.8 322.529 280.2 313.729 280.2H276.529V218.4H313.729C320.529 218.4 326.329 216 331.129 211.2C335.929 206.4 338.329 200.4 338.329 193.2C338.329 184.8 335.729 178.2 330.529 173.4C325.329 168.6 318.729 166.2 310.729 166.2C303.529 166.2 297.329 168.4 292.129 172.8C287.329 177.2 284.929 182.8 284.929 189.6H218.329C218.329 164.8 227.129 144.6 244.729 129C262.329 113 284.929 105 312.529 105C340.129 105 362.529 112.2 379.729 126.6C397.329 141 406.129 160 406.129 183.6C406.129 200.8 401.529 214.8 392.329 225.6C383.129 236 371.329 243.2 356.929 247.2C374.129 252 387.729 260.2 397.729 271.8C408.129 283.4 413.329 298 413.329 315.6C413.329 340.4 403.929 360.8 385.129 376.8C366.329 392.8 341.929 400.8 311.929 400.8Z"/>
                                <path d="M103.864 167.6H78.6643V105.2H171.664V400.2H103.864V167.6Z"/>
                            </mask>
                            <path d="M311.929 400.8C281.129 400.8 255.929 392.4 236.329 375.6C217.129 358.8 207.529 337 207.529 310.2H277.729C277.729 318.2 280.729 324.8 286.729 330C292.729 335.2 300.729 337.8 310.729 337.8C320.329 337.8 328.129 335 334.129 329.4C340.529 323.8 343.729 316.6 343.729 307.8C343.729 299.8 340.929 293.2 335.329 288C329.729 282.8 322.529 280.2 313.729 280.2H276.529V218.4H313.729C320.529 218.4 326.329 216 331.129 211.2C335.929 206.4 338.329 200.4 338.329 193.2C338.329 184.8 335.729 178.2 330.529 173.4C325.329 168.6 318.729 166.2 310.729 166.2C303.529 166.2 297.329 168.4 292.129 172.8C287.329 177.2 284.929 182.8 284.929 189.6H218.329C218.329 164.8 227.129 144.6 244.729 129C262.329 113 284.929 105 312.529 105C340.129 105 362.529 112.2 379.729 126.6C397.329 141 406.129 160 406.129 183.6C406.129 200.8 401.529 214.8 392.329 225.6C383.129 236 371.329 243.2 356.929 247.2C374.129 252 387.729 260.2 397.729 271.8C408.129 283.4 413.329 298 413.329 315.6C413.329 340.4 403.929 360.8 385.129 376.8C366.329 392.8 341.929 400.8 311.929 400.8Z" fill="currentColor"/>
                            <path d="M103.864 167.6H78.6643V105.2H171.664V400.2H103.864V167.6Z" fill="currentColor"/>
                            <path d="M311.929 400.8C281.129 400.8 255.929 392.4 236.329 375.6C217.129 358.8 207.529 337 207.529 310.2H277.729C277.729 318.2 280.729 324.8 286.729 330C292.729 335.2 300.729 337.8 310.729 337.8C320.329 337.8 328.129 335 334.129 329.4C340.529 323.8 343.729 316.6 343.729 307.8C343.729 299.8 340.929 293.2 335.329 288C329.729 282.8 322.529 280.2 313.729 280.2H276.529V218.4H313.729C320.529 218.4 326.329 216 331.129 211.2C335.929 206.4 338.329 200.4 338.329 193.2C338.329 184.8 335.729 178.2 330.529 173.4C325.329 168.6 318.729 166.2 310.729 166.2C303.529 166.2 297.329 168.4 292.129 172.8C287.329 177.2 284.929 182.8 284.929 189.6H218.329C218.329 164.8 227.129 144.6 244.729 129C262.329 113 284.929 105 312.529 105C340.129 105 362.529 112.2 379.729 126.6C397.329 141 406.129 160 406.129 183.6C406.129 200.8 401.529 214.8 392.329 225.6C383.129 236 371.329 243.2 356.929 247.2C374.129 252 387.729 260.2 397.729 271.8C408.129 283.4 413.329 298 413.329 315.6C413.329 340.4 403.929 360.8 385.129 376.8C366.329 392.8 341.929 400.8 311.929 400.8Z" stroke="var(--stroke-color)" stroke-width="2.4" mask="url(#path-4-mask)"/>
                            <path d="M103.864 167.6H78.6643V105.2H171.664V400.2H103.864V167.6Z" stroke="var(--stroke-color)" stroke-width="2.4" mask="url(#path-4-mask)"/>
                        </g>
                        
                        <g class="mix-blend-hard-light transition-all delay-400 opacity-100 duration-750 starting:opacity-0 motion-safe:starting:-translate-x-[102px] text-[#F3BEC7] dark:text-[#4B0600]">
                            <mask id="path-5-mask" maskUnits="userSpaceOnUse" x="102.329" y="103" width="338" height="299" fill="black">
                                <rect fill="white" x="102.329" y="103" width="338" height="299"/>
                                <path d="M337.593 400.8C306.793 400.8 281.593 392.4 261.993 375.6C242.793 358.8 233.193 337 233.193 310.2H303.393C303.393 318.2 306.393 324.8 312.393 330C318.393 335.2 326.393 337.8 336.393 337.8C345.993 337.8 353.793 335 359.793 329.4C366.193 323.8 369.393 316.6 369.393 307.8C369.393 299.8 366.593 293.2 360.993 288C355.393 282.8 348.193 280.2 339.393 280.2H302.193V218.4H339.393C346.193 218.4 351.993 216 356.793 211.2C361.593 206.4 363.993 200.4 363.993 193.2C363.993 184.8 361.393 178.2 356.193 173.4C350.993 168.6 344.393 166.2 336.393 166.2C329.193 166.2 322.993 168.4 317.793 172.8C312.993 177.2 310.593 182.8 310.593 189.6H243.993C243.993 164.8 252.793 144.6 270.393 129C287.993 113 310.593 105 338.193 105C365.793 105 388.193 112.2 405.393 126.6C422.993 141 431.793 160 431.793 183.6C431.793 200.8 427.193 214.8 417.993 225.6C408.793 236 396.993 243.2 382.593 247.2C399.793 252 413.393 260.2 423.393 271.8C433.793 283.4 438.993 298 438.993 315.6C438.993 340.4 429.593 360.8 410.793 376.8C391.993 392.8 367.593 400.8 337.593 400.8Z"/>
                                <path d="M129.529 167.6H104.329V105.2H197.329V400.2H129.529V167.6Z"/>
                            </mask>
                            <path d="M337.593 400.8C306.793 400.8 281.593 392.4 261.993 375.6C242.793 358.8 233.193 337 233.193 310.2H303.393C303.393 318.2 306.393 324.8 312.393 330C318.393 335.2 326.393 337.8 336.393 337.8C345.993 337.8 353.793 335 359.793 329.4C366.193 323.8 369.393 316.6 369.393 307.8C369.393 299.8 366.593 293.2 360.993 288C355.393 282.8 348.193 280.2 339.393 280.2H302.193V218.4H339.393C346.193 218.4 351.993 216 356.793 211.2C361.593 206.4 363.993 200.4 363.993 193.2C363.993 184.8 361.393 178.2 356.193 173.4C350.993 168.6 344.393 166.2 336.393 166.2C329.193 166.2 322.993 168.4 317.793 172.8C312.993 177.2 310.593 182.8 310.593 189.6H243.993C243.993 164.8 252.793 144.6 270.393 129C287.993 113 310.593 105 338.193 105C365.793 105 388.193 112.2 405.393 126.6C422.993 141 431.793 160 431.793 183.6C431.793 200.8 427.193 214.8 417.993 225.6C408.793 236 396.993 243.2 382.593 247.2C399.793 252 413.393 260.2 423.393 271.8C433.793 283.4 438.993 298 438.993 315.6C438.993 340.4 429.593 360.8 410.793 376.8C391.993 392.8 367.593 400.8 337.593 400.8Z" fill="currentColor"/>
                            <path d="M129.529 167.6H104.329V105.2H197.329V400.2H129.529V167.6Z" fill="currentColor"/>
                            <path d="M337.593 400.8C306.793 400.8 281.593 392.4 261.993 375.6C242.793 358.8 233.193 337 233.193 310.2H303.393C303.393 318.2 306.393 324.8 312.393 330C318.393 335.2 326.393 337.8 336.393 337.8C345.993 337.8 353.793 335 359.793 329.4C366.193 323.8 369.393 316.6 369.393 307.8C369.393 299.8 366.593 293.2 360.993 288C355.393 282.8 348.193 280.2 339.393 280.2H302.193V218.4H339.393C346.193 218.4 351.993 216 356.793 211.2C361.593 206.4 363.993 200.4 363.993 193.2C363.993 184.8 361.393 178.2 356.193 173.4C350.993 168.6 344.393 166.2 336.393 166.2C329.193 166.2 322.993 168.4 317.793 172.8C312.993 177.2 310.593 182.8 310.593 189.6H243.993C243.993 164.8 252.793 144.6 270.393 129C287.993 113 310.593 105 338.193 105C365.793 105 388.193 112.2 405.393 126.6C422.993 141 431.793 160 431.793 183.6C431.793 200.8 427.193 214.8 417.993 225.6C408.793 236 396.993 243.2 382.593 247.2C399.793 252 413.393 260.2 423.393 271.8C433.793 283.4 438.993 298 438.993 315.6C438.993 340.4 429.593 360.8 410.793 376.8C391.993 392.8 367.593 400.8 337.593 400.8Z" stroke="var(--stroke-color)" stroke-width="2.4" mask="url(#path-5-mask)"/>
                            <path d="M129.529 167.6H104.329V105.2H197.329V400.2H129.529V167.6Z" stroke="var(--stroke-color)" stroke-width="2.4" mask="url(#path-5-mask)"/>
                        </g>
                    </svg>
                    <div class="absolute inset-0 rounded-t-lg lg:rounded-t-none lg:rounded-r-lg shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d]"></div>
                </div>
            </main>
        </div>

        @if (Route::has('login'))
            <div class="h-14.5 hidden lg:block"></div>
        @endif
    </body>
</html>
```

</details>

## routes/api.php

Fuente: [routes/api.php](../../routes/api.php).

<details>
<summary>Ver contenido completo</summary>

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\ProjectController;
use App\Http\Controllers\API\DeliverableController;
use App\Http\Controllers\API\RepositoryController;
use App\Http\Controllers\API\AsignaturaController;
use App\Http\Controllers\API\CompetenciaController;
use App\Http\Controllers\API\DocumentTagController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\EvaluationController;
use App\Http\Controllers\API\SubjectGroupController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\API\ProposalWorkflowController;
use App\Http\Controllers\API\SystemSettingController;
use App\Http\Controllers\API\ActivityNotificationController;
use App\Http\Controllers\API\SemesterManagementController;
use App\Http\Controllers\API\CareerManagementController;
use App\Http\Controllers\API\CareerModuleController;
use App\Http\Controllers\API\CareerSetupController;
use App\Http\Controllers\API\CareerAuditController;
use App\Http\Controllers\API\CareerExportController;
use App\Http\Controllers\API\CareerIntegrityController;
use App\Http\Controllers\API\DatabaseBackupController;
use App\Http\Controllers\API\OperationalAlertController;
use App\Http\Controllers\API\ContinuityReportController;
use App\Http\Controllers\API\ContinuityPolicyController;
use App\Http\Controllers\API\CompanyController;
use App\Http\Controllers\API\CourseEnrollmentController;

// ========================
// AUTENTICACIÓN (sin protección)
// ========================
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/auth/refresh', [AuthController::class, 'refresh']);
Route::post('/auth/password/request-token', [AuthController::class, 'requestPasswordReset'])->middleware('throttle:password-recovery');
Route::post('/auth/password/verify-token', [AuthController::class, 'verifyPasswordResetToken'])->middleware('throttle:password-recovery');
Route::post('/auth/password/reset', [AuthController::class, 'resetPasswordWithToken'])->middleware('throttle:password-recovery');
Route::get('/settings/public', [SystemSettingController::class, 'public']);
Route::get('/users-template.csv', [UserController::class, 'blankCsvTemplate']);
Route::get('/users-template.xls', [UserController::class, 'usersExcelTemplate']);
Route::get('/projects-template.xls', [ProjectController::class, 'projectsExcelTemplate']);

// ========================
// RUTAS PÚBLICAS (sin JWT)
// ========================
Route::prefix('repositorio')->group(function () {
    Route::get('/', [RepositoryController::class, 'index']);
    Route::get('/buscar', [RepositoryController::class, 'search']);
    Route::get('/proyecto/{projectId}', [RepositoryController::class, 'byProject']);
    Route::get('/etiqueta/{tagId}', [RepositoryController::class, 'byTag']);
    Route::middleware(['auth:api', 'active', 'career', 'academic.open'])->group(function () {
        Route::get('/evaluation-documents', [RepositoryController::class, 'evaluationDocuments']);
        Route::post('/evaluation-documents', [RepositoryController::class, 'storeEvaluationDocument']);
        Route::put('/evaluation-documents/{id}/release-status', [RepositoryController::class, 'reviewEvaluationRelease']);
        Route::get('/thesis-documents', [RepositoryController::class, 'thesisDocuments']);
        Route::post('/thesis-documents', [RepositoryController::class, 'storeThesisDocument']);
    });
    Route::get('/{id}/download', [RepositoryController::class, 'download']);
    Route::get('/{id}/view', [RepositoryController::class, 'view']);
    Route::get('/{id}', [RepositoryController::class, 'show']);
});

// Estas rutas no exigen un contexto vigente para permitir recuperarse si la
// membresía de la carrera que estaba activa fue revocada.
Route::middleware(['auth:api', 'active'])->group(function () {
    Route::get('/auth/careers', [AuthController::class, 'careers']);
    Route::post('/auth/switch-career', [AuthController::class, 'switchCareer']);
});

// ========================
// RUTAS PROTEGIDAS (con JWT)
// ========================
Route::middleware(['auth:api', 'active', 'career', 'career.module', 'audit', 'academic.open'])->group(function () {
    Route::get('/residencies', [\App\Http\Controllers\API\ResidencyController::class, 'index']);
    Route::post('/residencies', [\App\Http\Controllers\API\ResidencyController::class, 'store']);
    Route::post('/residencies/{residency}/requirements', [\App\Http\Controllers\API\ResidencyController::class, 'requirement']);
    Route::post('/residencies/{residency}/requirements/{requirement}/versions', [\App\Http\Controllers\API\ResidencyController::class, 'upload']);
    Route::put('/residencies/{residency}/versions/{version}/review', [\App\Http\Controllers\API\ResidencyController::class, 'review']);
    Route::get('/residencies/{residency}/versions/{version}/download', [\App\Http\Controllers\API\ResidencyController::class, 'download']);
    Route::put('/residencies/{residency}/advisor', [\App\Http\Controllers\API\ResidencyController::class, 'advisor']);
    Route::post('/residencies/{residency}/close', [\App\Http\Controllers\API\ResidencyController::class, 'close']);
    Route::match(['GET', 'POST'], '/academic-operations/periods/{period}/reconciliation', [\App\Http\Controllers\API\AcademicOperationsController::class, 'reconciliation']);
    Route::get('/academic-operations/catalog', [\App\Http\Controllers\API\AcademicOperationsController::class, 'catalog']);
    Route::get('/academic-operations/enrollments', [\App\Http\Controllers\API\AcademicOperationsController::class, 'enrollments']);
    Route::post('/academic-operations/enrollments', [\App\Http\Controllers\API\AcademicOperationsController::class, 'assign']);
    Route::post('/academic-operations/project-period', [\App\Http\Controllers\API\AcademicOperationsController::class, 'projectPeriod']);
    Route::put('/academic-operations/memberships/{membership}', [\App\Http\Controllers\API\AcademicOperationsController::class, 'membership']);
    Route::get('/academic-operations/memberships/{membership}/commitments', [\App\Http\Controllers\API\AcademicOperationsController::class, 'commitments']);
    Route::post('/academic-operations/classrooms', [\App\Http\Controllers\API\AcademicOperationsController::class, 'classroom']);
    Route::get('/courses/{course}/students', [CourseEnrollmentController::class, 'students']);
    Route::delete('/course-enrollments/{course}', [CourseEnrollmentController::class, 'leave']);
    Route::post('/semester-management/periods/{period}/transition', [SemesterManagementController::class, 'transition'])->middleware('role:academic_manager');
    Route::put('/semester-management/exceptions/{exception}/decision', [SemesterManagementController::class, 'decideException'])->middleware('role:admin');
    Route::get('/academic-operations/authorizations', [\App\Http\Controllers\API\AcademicOperationsController::class, 'authorizations']);
    Route::post('/academic-operations/authorizations', [SemesterManagementController::class, 'storeException'])->middleware('role:teacher');
    Route::get('/career/modules', [CareerModuleController::class, 'index']);
    Route::get('/career/module-records', [CareerModuleController::class, 'records']);
    Route::middleware('role:admin')->group(function () {
        Route::get('/career/export', [CareerExportController::class, 'activeCareer']);
        Route::post('/career/setup/catalog', [CareerSetupController::class, 'importCatalog']);
        Route::put('/career/modules/{module}', [CareerModuleController::class, 'updateModule']);
        Route::post('/career/module-records', [CareerModuleController::class, 'storeRecord']);
        Route::put('/career/module-records/{record}', [CareerModuleController::class, 'updateRecord']);
        Route::delete('/career/module-records/{record}', [CareerModuleController::class, 'destroyRecord']);
        Route::post('/career/indicators', [CareerModuleController::class, 'storeIndicator']);
        Route::put('/career/indicators/{indicator}', [CareerModuleController::class, 'updateIndicator']);
        Route::delete('/career/indicators/{indicator}', [CareerModuleController::class, 'destroyIndicator']);
    });
    Route::middleware('role:general_admin')->prefix('admin')->group(function () {
        Route::get('/operational-alerts', [OperationalAlertController::class, 'index']);
        Route::get('/operational-alerts/summary', [OperationalAlertController::class, 'summary']);
        Route::post('/operational-alerts/scan', [OperationalAlertController::class, 'scan']);
        Route::put('/operational-alerts/{alert}/acknowledge', [OperationalAlertController::class, 'acknowledge']);
        Route::get('/continuity-report', [ContinuityReportController::class, 'show']);
        Route::get('/continuity-report.pdf', [ContinuityReportController::class, 'pdf']);
        Route::get('/continuity-history', [ContinuityReportController::class, 'history']);
        Route::post('/continuity-history', [ContinuityReportController::class, 'store']);
        Route::get('/continuity-policy', [ContinuityPolicyController::class, 'show']);
        Route::put('/continuity-policy', [ContinuityPolicyController::class, 'update']);
        Route::get('/integrity', [CareerIntegrityController::class, 'index']);
        Route::post('/integrity/run', [CareerIntegrityController::class, 'run']);
        Route::get('/integrity/history', [CareerIntegrityController::class, 'history']);
        Route::get('/database-backups', [DatabaseBackupController::class, 'index']);
        Route::get('/database-backups-health', [DatabaseBackupController::class, 'health']);
        Route::post('/database-backups', [DatabaseBackupController::class, 'store']);
        Route::post('/database-backups-cleanup', [DatabaseBackupController::class, 'cleanup']);
        Route::post('/database-backups/{backup}/verify', [DatabaseBackupController::class, 'verify']);
        Route::get('/database-backups/{backup}/download', [DatabaseBackupController::class, 'download']);
        Route::get('/careers/export', [CareerExportController::class, 'institutionalSummary']);
        Route::get('/audit', [CareerAuditController::class, 'index']);
        Route::get('/careers', [CareerManagementController::class, 'index']);
        Route::put('/careers/{career}', [CareerManagementController::class, 'update']);
        Route::get('/career-memberships', [CareerManagementController::class, 'memberships']);
        Route::post('/career-memberships', [CareerManagementController::class, 'storeMembership']);
        Route::put('/career-memberships/{membership}', [CareerManagementController::class, 'updateMembership']);
        Route::delete('/career-memberships/{membership}', [CareerManagementController::class, 'destroyMembership']);
    });
    
    // Evaluaciones (solo docentes y administradores)
    Route::middleware('role:admin,teacher,project_manager')->group(function () {
        Route::get('/evaluation-managers', [EvaluationController::class, 'managers']);
        Route::put('/evaluation-managers', [EvaluationController::class, 'updateManagers']);
        Route::get('/evaluations/managers', [EvaluationController::class, 'managers']);
        Route::put('/evaluations/managers', [EvaluationController::class, 'updateManagers']);
        Route::get('/evaluations/criteria', [EvaluationController::class, 'criteria']);
        Route::post('/evaluations/rubrics/initialize', [EvaluationController::class, 'initializeRubrics']);
        Route::put('/evaluations/rubric-score-modes', [EvaluationController::class, 'updateRubricScoreModes']);
        Route::post('/evaluations/rubric-criteria', [EvaluationController::class, 'storeCriterion']);
        Route::put('/evaluations/rubric-criteria/{id}', [EvaluationController::class, 'updateCriterion']);
        Route::delete('/evaluations/rubric-criteria/{id}', [EvaluationController::class, 'destroyCriterion']);
        Route::get('/evaluations/projects', [EvaluationController::class, 'projects']);
        Route::get('/evaluations/rooms', [EvaluationController::class, 'rooms']);
        Route::post('/evaluations/rooms', [EvaluationController::class, 'storeRoom']);
        Route::put('/evaluations/rooms/{id}', [EvaluationController::class, 'updateRoom']);
        Route::delete('/evaluations/rooms/{id}', [EvaluationController::class, 'destroyRoom']);
        Route::post('/evaluations/rooms/{id}/archive', [EvaluationController::class, 'archiveRoom']);
        Route::post('/evaluations/rooms/{id}/unarchive', [EvaluationController::class, 'unarchiveRoom']);
        Route::post('/evaluations/rooms/{id}/lock-sequence', [EvaluationController::class, 'lockRoomSequence']);
        Route::post('/evaluations/rooms/{id}/advance', [EvaluationController::class, 'advanceRoom']);
        Route::post('/evaluations/rooms/{id}/timer', [EvaluationController::class, 'controlRoomTimer']);
        Route::put('/evaluations/rooms/{id}/schedule', [EvaluationController::class, 'updateRoomSchedule']);
        Route::get('/evaluations/rooms/{id}/report.pdf', [EvaluationController::class, 'exportRoomPdf']);
        Route::get('/evaluations', [EvaluationController::class, 'index']);
        Route::post('/evaluations', [EvaluationController::class, 'store']);
        Route::post('/evaluations/archive-selected', [EvaluationController::class, 'archiveSelected']);
        Route::post('/evaluations/unarchive-selected', [EvaluationController::class, 'unarchiveSelected']);
        Route::get('/evaluations/{id}', [EvaluationController::class, 'show']);
        Route::get('/evaluations/{id}/report.pdf', [EvaluationController::class, 'exportEvaluationPdf']);
        Route::put('/evaluations/{id}', [EvaluationController::class, 'update']);
        Route::delete('/evaluations/{id}', [EvaluationController::class, 'destroy']);
        Route::post('/evaluations/{id}/archive', [EvaluationController::class, 'archive']);
        Route::post('/evaluations/{id}/unarchive', [EvaluationController::class, 'unarchive']);
        Route::post('/evaluations/{id}/score', [EvaluationController::class, 'score']);
        Route::post('/evaluations/{id}/mark-completed', [EvaluationController::class, 'markCompleted']);
        Route::post('/evaluations/{id}/feedback', [EvaluationController::class, 'feedback']);
    });

    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('/dashboard/teacher', [DashboardController::class, 'teacher']);
    Route::get('/dashboard/student', [DashboardController::class, 'student']);
    Route::get('/settings/current', [SystemSettingController::class, 'current']);
    Route::get('/companies', [CompanyController::class, 'index']);
    Route::middleware('role:project_manager')->put('/companies/{company}/review', [CompanyController::class, 'review']);

    Route::get('/course-enrollments', [CourseEnrollmentController::class, 'index']);
    Route::middleware('role:student')->group(function () {
        Route::post('/course-enrollments/{course}', [CourseEnrollmentController::class, 'enroll'])->middleware('throttle:10,1');
    });
    Route::middleware('role:teacher')->put('/courses/{course}/self-enrollment', [CourseEnrollmentController::class, 'updateAccess']);

    // Auth
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/heartbeat', [AuthController::class, 'heartbeat']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Notificaciones personales de actividad
    Route::get('/activity-notifications', [ActivityNotificationController::class, 'index']);
    Route::put('/activity-notifications/read-all', [ActivityNotificationController::class, 'markAllRead']);
    Route::put('/activity-notifications/{notification}/read', [ActivityNotificationController::class, 'markRead']);

    // Perfil propio
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/complete-initial', [ProfileController::class, 'completeInitial']);

    // Flujo de propuestas
    Route::get('/proposal/student-status', [ProposalWorkflowController::class, 'studentStatus']);
    Route::get('/student/evaluation-schedule', [EvaluationController::class, 'studentSchedule']);
    Route::get('/proposal/students/search', [ProposalWorkflowController::class, 'searchStudents']);
    Route::get('/proposal/teacher-projects', [ProposalWorkflowController::class, 'teacherProjects']);
    Route::post('/proposal/projects/{id}/review', [ProposalWorkflowController::class, 'review']);
    Route::middleware('role:admin,teacher,project_manager')->group(function () {
        Route::get('/proposal/window-groups', [ProposalWorkflowController::class, 'windowGroups']);
        Route::post('/proposal/windows', [ProposalWorkflowController::class, 'storeWindow']);
        Route::put('/proposal/windows/{id}', [ProposalWorkflowController::class, 'updateWindow']);
        Route::delete('/proposal/windows/{id}', [ProposalWorkflowController::class, 'destroyWindow']);
    });
    Route::get('/repositorio/student/list', [RepositoryController::class, 'studentIndex']);
    Route::post('/repositorio', [RepositoryController::class, 'store']);

    // Coordinación de proyectos: administrador, jefatura, asistencia y coordinación.
    Route::middleware('role:admin,project_manager')->group(function () {
        Route::get('/proposal/config', [ProposalWorkflowController::class, 'configIndex']);
        Route::post('/proposal/assignments', [ProposalWorkflowController::class, 'storeAssignment']);
        Route::delete('/proposal/assignments/{id}', [ProposalWorkflowController::class, 'destroyAssignment']);
        Route::post('/proposal/exceptions', [ProposalWorkflowController::class, 'storeException']);
        Route::delete('/proposal/exceptions/{id}', [ProposalWorkflowController::class, 'destroyException']);
        Route::get('/users', [UserController::class, 'index']);
    });

    // Operación académica: administrador, jefatura y asistencia de jefatura.
    Route::middleware('role:admin,academic_manager')->group(function () {
        Route::get('/semester-management', [SemesterManagementController::class, 'summary']);
        Route::get('/semester-management/search', [SemesterManagementController::class, 'search']);
        Route::post('/semester-management/periods', [SemesterManagementController::class, 'storePeriod']);
        Route::put('/semester-management/periods/{period}', [SemesterManagementController::class, 'updatePeriod']);
        Route::post('/semester-management/periods/{period}/activate', [SemesterManagementController::class, 'activatePeriod']);
        Route::get('/semester-management/periods/{period}/promotion-preview', [SemesterManagementController::class, 'promotionPreview']);
        Route::post('/semester-management/periods/{period}/promote', [SemesterManagementController::class, 'applyPromotion']);
        Route::post('/semester-management/exceptions', [SemesterManagementController::class, 'storeException']);
        Route::delete('/semester-management/exceptions/{exception}', [SemesterManagementController::class, 'destroyException']);
    });

    // Controles administrativos críticos.
    Route::middleware('role:admin')->group(function () {
        Route::get('/settings', [SystemSettingController::class, 'index']);
        Route::put('/settings', [SystemSettingController::class, 'update']);
        Route::get('/notices', [SystemSettingController::class, 'notices']);
        Route::put('/notices', [SystemSettingController::class, 'updateNotices']);
        Route::get('/repositorio/admin/list', [RepositoryController::class, 'adminIndex']);
        Route::post('/repositorio/{id}', [RepositoryController::class, 'update']);
        Route::post('/repositorio/{id}/publish', [RepositoryController::class, 'publish']);
        Route::delete('/repositorio/{id}', [RepositoryController::class, 'destroy']);
    });

    // Gobierno de identidades: exclusivamente Administrador General.
    Route::middleware('role:user_governance')->group(function () {
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::get('/users/credential-email-template', [UserController::class, 'credentialEmailTemplate']);
        Route::post('/users/send-credentials', [UserController::class, 'sendCredentialEmails']);
        Route::post('/users/import-excel', [UserController::class, 'importExcel']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
        Route::post('/users/{id}/toggle-active', [UserController::class, 'toggleActive']);
        Route::get('/users-inactive', [UserController::class, 'getInactive']);
    });

    // Cargas de asignaturas por semestre/grupo
    Route::get('/subject-groups', [SubjectGroupController::class, 'index']);
    Route::get('/subject-groups/{id}', [SubjectGroupController::class, 'show']);
    Route::middleware('role:admin,academic_manager')->group(function () {
        Route::post('/subject-groups', [SubjectGroupController::class, 'store']);
        Route::put('/subject-groups/{id}', [SubjectGroupController::class, 'update']);
        Route::delete('/subject-groups/{id}', [SubjectGroupController::class, 'destroy']);
        Route::get('/subject-groups/{id}/students', [SubjectGroupController::class, 'students']);
        Route::post('/subject-groups/{id}/students', [SubjectGroupController::class, 'syncStudents']);
    });

    // Projects (CRUD)
    Route::get('/my-projects', [ProjectController::class, 'myProjects']);
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::middleware('role:admin,project_manager')->group(function () {
        Route::post('/projects/import-excel', [ProjectController::class, 'importExcel']);
    });
    Route::middleware('role:student,project_manager')->post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{id}', [ProjectController::class, 'show']);
    Route::middleware('role:student,project_manager')->put('/projects/{id}', [ProjectController::class, 'update']);
    Route::middleware('role:project_manager')->group(function () {
        Route::delete('/projects/{id}', [ProjectController::class, 'destroy']);
        Route::post('/projects/{id}/advisors', [ProjectController::class, 'addAdvisor']);
        Route::put('/projects/{id}/advisors', [ProjectController::class, 'syncAdvisors']);
        Route::post('/projects/{id}/asignaturas', [ProjectController::class, 'syncAsignaturas']);
        Route::delete('/projects/{projectId}/advisors/{userId}', [ProjectController::class, 'removeAdvisor']);
    });

    // Deliverables (CRUD + Calificación + Upload + Descarga)
    Route::get('/teacher/deliverables-matrix', [DeliverableController::class, 'teacherMatrix']);
    Route::get('/evaluation-documents', [DeliverableController::class, 'evaluationDocuments']);
    Route::get('/my-deliverables', [DeliverableController::class, 'myDeliverables']);
    Route::get('/deliverables', [DeliverableController::class, 'index']);
    Route::post('/deliverables', [DeliverableController::class, 'store']);
    Route::get('/deliverables/{id}', [DeliverableController::class, 'show']);
    Route::put('/deliverables/{id}', [DeliverableController::class, 'update']);
    Route::delete('/deliverables/{id}', [DeliverableController::class, 'destroy']);
    
    // Endpoints adicionales de entregas
    Route::post('/deliverables/{id}/calificar', [DeliverableController::class, 'calificar']);
    Route::post('/deliverables/{id}/upload', [DeliverableController::class, 'upload']);
    Route::get('/deliverables/{id}/download', [DeliverableController::class, 'download']);

    // Asignaturas (solo Admin puede crear/editar)
    Route::get('/asignaturas', [AsignaturaController::class, 'index']);
    Route::get('/asignaturas/{id}', [AsignaturaController::class, 'show']);
    Route::middleware('role:admin,academic_manager')->group(function () {
        Route::post('/asignaturas', [AsignaturaController::class, 'store']);
        Route::put('/asignaturas/{id}', [AsignaturaController::class, 'update']);
        Route::delete('/asignaturas/{id}', [AsignaturaController::class, 'destroy']);
    });
    
    // Competencias (solo Admin puede crear/editar)
    Route::get('/competencias', [CompetenciaController::class, 'index']);
    Route::get('/competencias/{id}', [CompetenciaController::class, 'show']);
    Route::middleware('role:admin,academic_manager')->group(function () {
        Route::post('/competencias', [CompetenciaController::class, 'store']);
        Route::put('/competencias/{id}', [CompetenciaController::class, 'update']);
        Route::delete('/competencias/{id}', [CompetenciaController::class, 'destroy']);
    });
    
    // Document Tags (solo Admin)
    Route::middleware('role:admin')->group(function () {
        Route::get('/document-tags', [DocumentTagController::class, 'index']);
        Route::post('/document-tags', [DocumentTagController::class, 'store']);
        Route::get('/document-tags/{id}', [DocumentTagController::class, 'show']);
        Route::put('/document-tags/{id}', [DocumentTagController::class, 'update']);
        Route::delete('/document-tags/{id}', [DocumentTagController::class, 'destroy']);
    });
});
```

</details>

## routes/console.php

Fuente: [routes/console.php](../../routes/console.php).

<details>
<summary>Ver contenido completo</summary>

```php
<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\SemesterManagementService;
use App\Services\MulticareerIntegrityService;
use App\Services\DatabaseBackupService;
use App\Services\OperationalAlertService;
use App\Services\ContinuityReportService;
use App\Services\ReleaseReadinessService;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('sgpi:check-multicareer {--store} {--source=consola}', function (MulticareerIntegrityService $integrity) {
    $report = $integrity->report();
    if ($this->option('store')) {
        $integrity->store($report, (string) $this->option('source'));
    }
    $this->table(
        ['Verificación', 'Estado', 'Incidencias'],
        collect($report['checks'])->map(fn ($check) => [
            $check['name'],
            $check['status'] === 'ok' ? 'OK' : 'REVISAR',
            $check['count'],
        ])->all()
    );
    $this->newLine();
    $this->line($report['healthy']
        ? 'Integridad multicarrera correcta.'
        : "Se detectaron {$report['violations']} incidencias.");

    return $report['healthy'] ? self::SUCCESS : self::FAILURE;
})->purpose('Verifica que no existan relaciones cruzadas entre carreras');

Artisan::command('sgpi:backup-database {--source=consola}', function (DatabaseBackupService $backups) {
    $backup = $backups->create((string) $this->option('source'));
    $this->info("Respaldo creado: {$backup['nombre_archivo']}");
    $this->line("SHA-256: {$backup['checksum_sha256']}");

    return self::SUCCESS;
})->purpose('Genera un respaldo privado y comprimido de la base de datos');

Artisan::command('sgpi:verify-backup {backup?}', function (DatabaseBackupService $backups) {
    $backupId = $this->argument('backup') ?: DB::table('respaldos_base_datos')
        ->where('estado', 'completado')
        ->orderByDesc('id')
        ->value('id');
    if (!$backupId) {
        $this->error('No existe un respaldo completado para verificar.');
        return self::FAILURE;
    }

    $verification = $backups->verify((int) $backupId);
    $this->info("Respaldo {$backupId} restaurado correctamente en entorno temporal.");
    $this->line("Tablas esenciales: {$verification['tablas_encontradas']}");
    $this->line("Filas verificadas: {$verification['filas_verificadas']}");

    return self::SUCCESS;
})->purpose('Prueba un respaldo mediante una restauración temporal aislada');

Artisan::command('sgpi:backup-health', function (DatabaseBackupService $backups) {
    $status = $backups->storageStatus();
    $this->table(['Copias', 'Disponibles', 'Faltantes', 'Alteradas', 'Verificadas'], [[
        $status['records'],
        $status['available'],
        $status['missing'],
        $status['altered'],
        $status['verified'],
    ]]);
    $this->line($status['healthy'] ? 'Almacenamiento de respaldos saludable.' : 'Se requiere revisar el almacenamiento de respaldos.');

    return $status['healthy'] ? self::SUCCESS : self::FAILURE;
})->purpose('Comprueba disponibilidad y checksum de los respaldos privados');

Artisan::command('sgpi:scan-operations', function (OperationalAlertService $alerts) {
    $summary = $alerts->scan();
    $this->line("Abiertas: {$summary['open']} · Atendidas: {$summary['acknowledged']} · Críticas activas: {$summary['critical']}");

    return $summary['critical'] ? self::FAILURE : self::SUCCESS;
})->purpose('Actualiza las alertas de integridad y recuperación institucional');

Artisan::command('sgpi:measure-continuity {--source=consola}', function (ContinuityReportService $reports) {
    $measurement = $reports->store((string) $this->option('source'));
    $this->info("Índice de preparación: {$measurement['indice_preparacion']}%");
    $this->line("Controles: {$measurement['controles_correctos']}/{$measurement['controles_totales']}");

    return self::SUCCESS;
})->purpose('Almacena una medición histórica de continuidad operativa');

Artisan::command('sgpi:release-check {--allow-local}', function (ReleaseReadinessService $readiness) {
    $report = $readiness->check((bool) $this->option('allow-local'));
    $this->table(
        ['Control', 'Estado', 'Detalle'],
        collect($report['checks'])->map(fn ($check) => [
            $check['name'],
            strtoupper($check['status']),
            $check['detail'],
        ])->all()
    );
    $this->newLine();
    $this->line($report['ready']
        ? "Liberación preparada con {$report['warnings']} advertencias."
        : "Liberación bloqueada: {$report['failures']} controles fallidos.");

    return $report['ready'] ? self::SUCCESS : self::FAILURE;
})->purpose('Valida seguridad, esquema, aislamiento y recuperación antes de desplegar');

Artisan::command('sgpi:diagnose-academic {--baseline : Check only prerequisites before the additive migration}', function () {
    $report = app(\App\Services\AcademicDiagnosticsService::class)->report();
    $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    return $report['baseline_ready'] && ($this->option('baseline') || !$report['missing_consolidation']) ? self::SUCCESS : self::FAILURE;
})->purpose('Diagnóstico de sólo lectura del esquema y conciliación académica');

Artisan::command('sgpi:prepare-academic-periods', function () {
    $id = DB::table('ejecuciones_academicas')->insertGetId(['tarea' => 'preparar_periodos', 'estado' => 'ejecutando', 'created_at' => now(), 'updated_at' => now()]);
    try {
        app(\App\Services\AcademicMaintenanceService::class)->run();
        DB::table('ejecuciones_academicas')->where('id', $id)->update(['estado' => 'completada', 'updated_at' => now()]);
        $this->info('Periodos preparados. No se activaron ni promovieron alumnos.');
    } catch (\Throwable $error) {
        DB::table('ejecuciones_academicas')->where('id', $id)->update(['estado' => 'fallida', 'detalle' => get_class($error), 'updated_at' => now()]);
        throw $error;
    }
});

Schedule::command('sgpi:prepare-academic-periods')->hourlyAt(10)->timezone('America/Mexico_City')
    ->name('prepare-academic-periods')->withoutOverlapping();

Schedule::command('sgpi:check-multicareer --store --source=programado')
    ->dailyAt('00:20')
    ->name('check-multicareer-integrity')
    ->withoutOverlapping();

Schedule::command('sgpi:backup-database --source=programado')
    ->dailyAt('01:00')
    ->name('backup-database')
    ->withoutOverlapping();

Schedule::command('sgpi:verify-backup')
    ->weeklyOn(0, '02:00')
    ->name('verify-latest-database-backup')
    ->withoutOverlapping();

Schedule::command('sgpi:backup-health')
    ->dailyAt('01:30')
    ->name('check-database-backup-storage')
    ->withoutOverlapping();

Schedule::command('sgpi:scan-operations')
    ->dailyAt('02:30')
    ->name('scan-operational-alerts')
    ->withoutOverlapping();

Schedule::command('sgpi:measure-continuity --source=programado')
    ->dailyAt('02:15')
    ->name('measure-operational-continuity')
    ->withoutOverlapping();
```

</details>

## routes/web.php

Fuente: [routes/web.php](../../routes/web.php).

<details>
<summary>Ver contenido completo</summary>

```php
<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
```

</details>

## scripts/rehearse-academic.php

Fuente: [scripts/rehearse-academic.php](../../scripts/rehearse-academic.php).

<details>
<summary>Ver contenido completo</summary>

```php
<?php
// No source writes: dump the configured database, or migrate only the supplied isolated socket.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

umask(0077);
try {
    $mode = $argv[1] ?? '';
    $directory = $argv[2] ?? '';
    if (!is_dir($directory) || !str_starts_with(realpath($directory), realpath(storage_path('app/private')).DIRECTORY_SEPARATOR)) throw new RuntimeException('Directorio privado inválido.');
    if ($mode === 'export') {
        $config = DB::connection()->getConfig();
        if (!in_array($config['driver'], ['mysql', 'mariadb'], true)) throw new RuntimeException('Se requiere MySQL.');
        $args = ['mysqldump', '--no-defaults', '--single-transaction', '--skip-lock-tables', '--no-tablespaces', '--set-gtid-purged=OFF', '--hex-blob', '--routines', '--triggers', '--result-file='.$directory.'/source.sql'];
        if (!empty($config['unix_socket'])) $args[] = '--socket='.$config['unix_socket'];
        else { $args[] = '--host='.$config['host']; $args[] = '--port='.$config['port']; }
        $args[] = '--user='.$config['username']; $args[] = $config['database'];
        $process = new Process($args, base_path(), ['MYSQL_PWD' => $config['password']]);
        $process->setTimeout(300);
        $process->run();
        if (!$process->isSuccessful()) throw new RuntimeException('Falló la exportación; no se modificó la fuente.');
        file_put_contents($directory.'/source.sha256', hash_file('sha256', $directory.'/source.sql')."\n");
        file_put_contents($directory.'/source-diagnostic.json', json_encode(app(\App\Services\AcademicDiagnosticsService::class)->report(), JSON_PRETTY_PRINT));
        echo "Respaldo privado creado.\n";
    } elseif ($mode === 'files') {
        mkdir($directory.'/documents', 0700);
        $manifest = [];
        foreach (\App\Models\DocumentVersion::withoutGlobalScopes()->get() as $version) {
            try {
                [$disk, $path] = app(\App\Services\ProtectedDownloadService::class)->resolve($version);
                $input = \Illuminate\Support\Facades\Storage::disk($disk)->readStream($path);
                if (!is_resource($input)) throw new RuntimeException('Archivo no disponible.');
                $target = $directory.'/documents/'.(int) $version->id;
                $output = fopen($target, 'wb');
                stream_copy_to_stream($input, $output);
                fclose($input); fclose($output);
                $manifest[] = ['version_id' => $version->id, 'disk' => $disk, 'path' => $path, 'sha256' => hash_file('sha256', $target), 'bytes' => filesize($target), 'status' => 'copied'];
            } catch (Throwable $e) {
                $manifest[] = ['version_id' => $version->id, 'status' => 'unavailable'];
            }
        }
        file_put_contents($directory.'/documents-manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
        $copied = count(array_filter($manifest, fn ($r) => $r['status'] === 'copied'));
        echo 'Versiones documentales: '.count($manifest).'; respaldadas: '.$copied.'; no disponibles: '.(count($manifest)-$copied)."\n";
    } elseif ($mode === 'migrate-copy') {
        $socket = $argv[3] ?? '';
        if (!preg_match('#^/tmp/sgpi-rehearsal\.[A-Za-z0-9]+/mysql.sock$#', $socket) || !file_exists($socket)) throw new RuntimeException('Socket aislado inválido.');
        config(['database.default' => 'rehearsal', 'database.connections.rehearsal' => ['driver' => 'mysql', 'unix_socket' => $socket, 'database' => 'sgpi_rehearsal', 'username' => 'root', 'password' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true]]);
        DB::purge('rehearsal');
        if (DB::connection()->getDatabaseName() !== 'sgpi_rehearsal') throw new RuntimeException('Destino inválido.');
        $original = [];
        foreach (DB::select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"') as $table) {
            $name = array_values((array) $table)[0];
            $columns = Schema::getColumnListing($name);
            $rows = DB::table($name)->select($columns)->get()->map(fn ($r) => json_encode($r))->sort()->values()->all();
            $original[$name] = ['columns' => $columns, 'count' => count($rows), 'hash' => hash('sha256', json_encode($rows))];
        }
        foreach (['2026_09_10_000001_add_storage_metadata_to_documento_versiones.php', '2026_10_13_000001_consolidate_academic_workflows.php'] as $migration) (require database_path('migrations/'.$migration))->up();
        $preserved = [];
        foreach ($original as $table => $before) {
            $rows = DB::table($table)->select($before['columns'])->get()->map(fn ($r) => json_encode($r))->sort()->values()->all();
            if ($before['hash'] !== hash('sha256', json_encode($rows))) throw new RuntimeException('Cambió el contenido histórico de '.$table);
            $preserved[$table] = $before['count'];
        }
        $report = app(\App\Services\AcademicDiagnosticsService::class)->report();
        $report['preserved_original_tables'] = $preserved;
        $report['source_dump_sha256'] = hash_file('sha256', $directory.'/source.sql');
        file_put_contents($directory.'/rehearsal-result.json', json_encode($report, JSON_PRETTY_PRINT));
        if (!$report['baseline_ready'] || $report['missing_consolidation']) throw new RuntimeException('El esquema ensayado todavía tiene faltantes; revisa el informe.');
        echo 'Ensayo correcto: '.count($preserved)." tablas originales preservadas, incluidos todos sus valores históricos.\n";
    } else throw new RuntimeException('Modo inválido.');
} catch (Throwable $e) {
    // Never emit connection credentials, SQL values or subprocess arguments.
    fwrite(STDERR, 'Ensayo incompleto: '.get_class($e).". No aplicar a la fuente.\n");
    if (isset($directory) && is_dir($directory)) file_put_contents($directory.'/failure-type.txt', get_class($e));
    exit(1);
}
```

</details>

## scripts/rehearse-academic.sh

Fuente: [scripts/rehearse-academic.sh](../../scripts/rehearse-academic.sh).

<details>
<summary>Ver contenido completo</summary>

```bash
#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
umask 077
for binary in mysqld mysqldump mysql mysqladmin php; do command -v "$binary" >/dev/null; done
mkdir -p storage/app/private/academic-rehearsals
archive=$(mktemp -d "$PWD/storage/app/private/academic-rehearsals/run.XXXXXX")
php scripts/rehearse-academic.php export "$archive"
php scripts/rehearse-academic.php files "$archive"
work=$(mktemp -d /tmp/sgpi-rehearsal.XXXXXX)
socket="$work/mysql.sock"
cleanup() {
 mysqladmin --no-defaults --socket="$socket" -uroot shutdown >/dev/null 2>&1 || true
 rm -rf -- "$work"
}
trap cleanup EXIT
mkdir "$work/data"
mysqld --no-defaults --initialize-insecure --datadir="$work/data" --log-error="$work/init.log" --user="$(id -un)"
mysqld --no-defaults --datadir="$work/data" --socket="$socket" --skip-networking --mysqlx=0 --event-scheduler=OFF --pid-file="$work/mysql.pid" --log-error="$work/server.log" --user="$(id -un)" &
for attempt in $(seq 1 100); do
 if mysqladmin --no-defaults --socket="$socket" -uroot ping >/dev/null 2>&1; then break; fi
 sleep .1
done
mysql --no-defaults --socket="$socket" -uroot -e 'CREATE DATABASE sgpi_rehearsal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
mysql --no-defaults --socket="$socket" -uroot sgpi_rehearsal < "$archive/source.sql" 2> "$archive/import-errors.log"
php scripts/rehearse-academic.php migrate-copy "$archive" "$socket"
printf 'Informe privado: %s/rehearsal-result.json\n' "$archive"
```

</details>

## scripts/test-academic-mysql.sh

Fuente: [scripts/test-academic-mysql.sh](../../scripts/test-academic-mysql.sh).

<details>
<summary>Ver contenido completo</summary>

```bash
#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
for binary in mysqld mysql mysqladmin php; do command -v "$binary" >/dev/null || { echo "Missing $binary" >&2; exit 1; }; done
work=$(mktemp -d /tmp/sgpi-academic-test.XXXXXX)
socket="$work/mysql.sock"
cleanup() {
  mysqladmin --no-defaults --socket="$socket" -uroot shutdown >/dev/null 2>&1 || true
  rm -rf -- "$work"
}
trap cleanup EXIT
mkdir "$work/data"
mysqld --no-defaults --initialize-insecure --datadir="$work/data" --log-error="$work/init.log" --user="$(id -un)"
mysqld --no-defaults --datadir="$work/data" --socket="$socket" --skip-networking --mysqlx=0 --pid-file="$work/mysql.pid" --log-error="$work/server.log" --user="$(id -un)" &
for attempt in $(seq 1 100); do
  if mysqladmin --no-defaults --socket="$socket" -uroot ping >/dev/null 2>&1; then break; fi
  sleep .1
done
mysql --no-defaults --socket="$socket" -uroot -e 'CREATE DATABASE sgpi_test_academic CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
SGPI_TEST_MYSQL_SOCKET="$socket" APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL='' CACHE_STORE=array \
  php vendor/bin/phpunit --stop-on-error tests/Integration/AcademicWorkflowsMySqlTest.php "$@"
```

</details>

## vite.config.js

Fuente: [vite.config.js](../../vite.config.js).

<details>
<summary>Ver contenido completo</summary>

```javascript
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
```

</details>

## Seeders y factory: función y límites

`DatabaseSeeder` invoca `UserSeeder`. Este último crea identidades de ejemplo usando hashes; sus credenciales no se reproducen. `DemoTestDataSeeder` prepara usuarios, grupos, asignaciones, ventanas y proyectos de demostración; conserva referencias a estructuras históricas. `UserFactory` genera atributos sintéticos para pruebas y estado de correo no verificado.

`AsignaturasCatalogSeeder` no es una importación inocua: elimina relaciones de grupos/asignaturas y proyectos/asignaturas, limpia asignaciones, borra competencias y asignaturas y luego inserta el catálogo. Se refiere a estructuras heredadas. No se ejecutó y no debe considerarse actualización idempotente de una base operativa.

## Documentación previa

README y documentos de cumplimiento, resúmenes, perfiles, despliegue, operación y auditorías son antecedentes. Este informe prioriza código y pruebas del corte cuando difieren. `CREDENCIALES_PRUEBA.md` y cualquier archivo de datos están excluidos del contenido reproducido. Los 22 SQL se inventarían en DATOS; los INSERT de ejemplo no se copian.
