# Configuración y variables de entorno

No se transcribe ningún valor de `.env`. “Presente” sólo indica que hay una asignación textual no vacía; no valida conectividad ni revela su contenido. Las variables inyectadas por el proceso o Railway pueden cambiar la configuración efectiva.

| Variable | Estado en .env local | Uso en código |
|---|---|---|
| `APP_DEBUG` | Presente, valor omitido | `config/app.php:42` |
| `APP_ENV` | Presente, valor omitido | `config/app.php:29` |
| `APP_FAKER_LOCALE` | Presente, valor omitido | `config/app.php:85` |
| `APP_FALLBACK_LOCALE` | Presente, valor omitido | `config/app.php:83` |
| `APP_KEY` | Presente, valor omitido | `config/app.php:100` |
| `APP_LOCALE` | Presente, valor omitido | `config/app.php:81` |
| `APP_MAINTENANCE_DRIVER` | Presente, valor omitido | `config/app.php:122` |
| `APP_MAINTENANCE_STORE` | No declarada; puede usar default o entorno del proceso | `config/app.php:123` |
| `APP_NAME` | Presente, valor omitido | `config/app.php:16`<br>`config/cache.php:115`<br>`config/database.php:152`<br>`config/logging.php:79`<br>`config/mail.php:115`<br>`config/session.php:132` |
| `APP_PREVIOUS_KEYS` | No declarada; puede usar default o entorno del proceso | `config/app.php:104` |
| `APP_TIMEZONE` | No declarada; puede usar default o entorno del proceso | `config/app.php:68` |
| `APP_URL` | Presente, valor omitido | `config/app.php:55`<br>`config/filesystems.php:44`<br>`config/filesystems.php:53`<br>`config/filesystems.php:70`<br>`config/mail.php:49` |
| `AUTH_ACTIVITY_CACHE_STORE` | No declarada; puede usar default o entorno del proceso | `config/auth.php:23` |
| `AUTH_GUARD` | No declarada; puede usar default o entorno del proceso | `config/auth.php:19` |
| `AUTH_MODEL` | No declarada; puede usar default o entorno del proceso | `config/auth.php:74` |
| `AUTH_PASSWORD_BROKER` | No declarada; puede usar default o entorno del proceso | `config/auth.php:20` |
| `AUTH_PASSWORD_RESET_TOKEN_TABLE` | No declarada; puede usar default o entorno del proceso | `config/auth.php:105` |
| `AUTH_PASSWORD_TIMEOUT` | No declarada; puede usar default o entorno del proceso | `config/auth.php:122` |
| `AWS_ACCESS_KEY_ID` | Asignada vacía | `config/cache.php:83`<br>`config/filesystems.php:78`<br>`config/queue.php:58`<br>`config/services.php:27` |
| `AWS_BUCKET` | Asignada vacía | `config/filesystems.php:81` |
| `AWS_DEFAULT_REGION` | Presente, valor omitido | `config/cache.php:85`<br>`config/filesystems.php:80`<br>`config/queue.php:63`<br>`config/services.php:29` |
| `AWS_ENDPOINT` | No declarada; puede usar default o entorno del proceso | `config/filesystems.php:83` |
| `AWS_SECRET_ACCESS_KEY` | Asignada vacía | `config/cache.php:84`<br>`config/filesystems.php:79`<br>`config/queue.php:59`<br>`config/services.php:28` |
| `AWS_URL` | No declarada; puede usar default o entorno del proceso | `config/filesystems.php:82` |
| `AWS_USE_PATH_STYLE_ENDPOINT` | Presente, valor omitido | `config/filesystems.php:84` |
| `BCRYPT_ROUNDS` | Presente, valor omitido | Sin llamada env() directa identificada en app/config/bootstrap; puede ser consumida por framework, Vite o despliegue |
| `BEANSTALKD_QUEUE` | No declarada; puede usar default o entorno del proceso | `config/queue.php:50` |
| `BEANSTALKD_QUEUE_HOST` | No declarada; puede usar default o entorno del proceso | `config/queue.php:49` |
| `BEANSTALKD_QUEUE_RETRY_AFTER` | No declarada; puede usar default o entorno del proceso | `config/queue.php:51` |
| `BROADCAST_CONNECTION` | Presente, valor omitido | Sin llamada env() directa identificada en app/config/bootstrap; puede ser consumida por framework, Vite o despliegue |
| `CACHE_PREFIX` | No declarada; puede usar default o entorno del proceso | `config/cache.php:115` |
| `CACHE_STORE` | Presente, valor omitido | `config/cache.php:18` |
| `CONTINUITY_CRITICAL_READINESS` | No declarada; puede usar default o entorno del proceso | `config/continuity.php:5` |
| `CONTINUITY_MAX_BACKUP_AGE_HOURS` | No declarada; puede usar default o entorno del proceso | `config/continuity.php:6` |
| `CONTINUITY_TARGET_READINESS` | No declarada; puede usar default o entorno del proceso | `config/continuity.php:4` |
| `DB_CACHE_CONNECTION` | No declarada; puede usar default o entorno del proceso | `config/cache.php:44` |
| `DB_CACHE_LOCK_CONNECTION` | No declarada; puede usar default o entorno del proceso | `config/cache.php:46` |
| `DB_CACHE_LOCK_TABLE` | No declarada; puede usar default o entorno del proceso | `config/cache.php:47` |
| `DB_CACHE_TABLE` | No declarada; puede usar default o entorno del proceso | `config/cache.php:45` |
| `DB_CHARSET` | No declarada; puede usar default o entorno del proceso | `config/database.php:56`<br>`config/database.php:76`<br>`config/database.php:95`<br>`config/database.php:110` |
| `DB_COLLATION` | No declarada; puede usar default o entorno del proceso | `config/database.php:57`<br>`config/database.php:77` |
| `DB_CONNECTION` | Presente, valor omitido | `config/database.php:20`<br>`config/queue.php:106`<br>`config/queue.php:125` |
| `DB_DATABASE` | Presente, valor omitido | `config/database.php:38`<br>`config/database.php:52`<br>`config/database.php:72`<br>`config/database.php:92`<br>`config/database.php:107` |
| `DB_ENCRYPT` | No declarada; puede usar default o entorno del proceso | `config/database.php:113` |
| `DB_FOREIGN_KEYS` | No declarada; puede usar default o entorno del proceso | `config/database.php:40` |
| `DB_HOST` | Presente, valor omitido | `config/database.php:50`<br>`config/database.php:70`<br>`config/database.php:90`<br>`config/database.php:105` |
| `DB_PASSWORD` | Asignada vacía | `config/database.php:54`<br>`config/database.php:74`<br>`config/database.php:94`<br>`config/database.php:109` |
| `DB_PORT` | Presente, valor omitido | `config/database.php:51`<br>`config/database.php:71`<br>`config/database.php:91`<br>`config/database.php:106` |
| `DB_QUEUE` | No declarada; puede usar default o entorno del proceso | `config/queue.php:42` |
| `DB_QUEUE_CONNECTION` | No declarada; puede usar default o entorno del proceso | `config/queue.php:40` |
| `DB_QUEUE_RETRY_AFTER` | No declarada; puede usar default o entorno del proceso | `config/queue.php:43` |
| `DB_QUEUE_TABLE` | No declarada; puede usar default o entorno del proceso | `config/queue.php:41` |
| `DB_SOCKET` | No declarada; puede usar default o entorno del proceso | `config/database.php:55`<br>`config/database.php:75` |
| `DB_SSLMODE` | No declarada; puede usar default o entorno del proceso | `config/database.php:99` |
| `DB_TRUST_SERVER_CERTIFICATE` | No declarada; puede usar default o entorno del proceso | `config/database.php:114` |
| `DB_URL` | No declarada; puede usar default o entorno del proceso | `config/database.php:37`<br>`config/database.php:49`<br>`config/database.php:69`<br>`config/database.php:89`<br>`config/database.php:104` |
| `DB_USERNAME` | Presente, valor omitido | `config/database.php:53`<br>`config/database.php:73`<br>`config/database.php:93`<br>`config/database.php:108` |
| `DYNAMODB_CACHE_TABLE` | No declarada; puede usar default o entorno del proceso | `config/cache.php:86` |
| `DYNAMODB_ENDPOINT` | No declarada; puede usar default o entorno del proceso | `config/cache.php:87` |
| `FILESYSTEM_DISK` | Presente, valor omitido | `config/filesystems.php:16` |
| `FRONTEND_URLS` | Presente, valor omitido | `config/cors.php:5` |
| `JWT_ABSOLUTE_TTL` | No declarada; puede usar default o entorno del proceso | `config/jwt.php:21`<br>`config/jwt.php:22` |
| `JWT_ALGO` | No declarada; puede usar default o entorno del proceso | `config/jwt.php:24` |
| `JWT_BLACKLIST_ENABLED` | No declarada; puede usar default o entorno del proceso | `config/jwt.php:40` |
| `JWT_BLACKLIST_GRACE_PERIOD` | No declarada; puede usar default o entorno del proceso | `config/jwt.php:41` |
| `JWT_LEEWAY` | No declarada; puede usar default o entorno del proceso | `config/jwt.php:38` |
| `JWT_PASSPHRASE` | No declarada; puede usar default o entorno del proceso | `config/jwt.php:18` |
| `JWT_PRIVATE_KEY` | No declarada; puede usar default o entorno del proceso | `config/jwt.php:17` |
| `JWT_PUBLIC_KEY` | No declarada; puede usar default o entorno del proceso | `config/jwt.php:16` |
| `JWT_REFRESH_TTL` | No declarada; puede usar default o entorno del proceso | `config/jwt.php:23` |
| `JWT_SECRET` | Presente, valor omitido | `config/jwt.php:13` |
| `JWT_TTL` | No declarada; puede usar default o entorno del proceso | `config/jwt.php:21` |
| `LOG_CHANNEL` | Presente, valor omitido | `config/logging.php:21` |
| `LOG_DAILY_DAYS` | No declarada; puede usar default o entorno del proceso | `config/logging.php:72` |
| `LOG_DEPRECATIONS_CHANNEL` | Presente, valor omitido | `config/logging.php:35` |
| `LOG_DEPRECATIONS_TRACE` | No declarada; puede usar default o entorno del proceso | `config/logging.php:36` |
| `LOG_LEVEL` | Presente, valor omitido | `config/logging.php:64`<br>`config/logging.php:71`<br>`config/logging.php:81`<br>`config/logging.php:87`<br>`config/logging.php:99`<br>`config/logging.php:110`<br>`config/logging.php:117` |
| `LOG_PAPERTRAIL_HANDLER` | No declarada; puede usar default o entorno del proceso | `config/logging.php:88` |
| `LOG_SLACK_EMOJI` | No declarada; puede usar default o entorno del proceso | `config/logging.php:80` |
| `LOG_SLACK_USERNAME` | No declarada; puede usar default o entorno del proceso | `config/logging.php:79` |
| `LOG_SLACK_WEBHOOK_URL` | No declarada; puede usar default o entorno del proceso | `config/logging.php:78` |
| `LOG_STACK` | Presente, valor omitido | `config/logging.php:57` |
| `LOG_STDERR_FORMATTER` | No declarada; puede usar default o entorno del proceso | `config/logging.php:104` |
| `LOG_SYSLOG_FACILITY` | No declarada; puede usar default o entorno del proceso | `config/logging.php:111` |
| `MAIL_EHLO_DOMAIN` | No declarada; puede usar default o entorno del proceso | `config/mail.php:49` |
| `MAIL_FROM_ADDRESS` | Presente, valor omitido | `config/mail.php:114`<br>`config/services.php:23` |
| `MAIL_FROM_NAME` | Presente, valor omitido | `config/mail.php:115` |
| `MAIL_HOST` | Presente, valor omitido | `config/mail.php:44` |
| `MAIL_LOG_CHANNEL` | No declarada; puede usar default o entorno del proceso | `config/mail.php:75` |
| `MAIL_MAILER` | Presente, valor omitido | `config/mail.php:17` |
| `MAIL_PASSWORD` | Presente, valor omitido | `config/mail.php:47` |
| `MAIL_PORT` | Presente, valor omitido | `config/mail.php:45` |
| `MAIL_SCHEME` | Presente, valor omitido | `config/mail.php:42` |
| `MAIL_SENDMAIL_PATH` | No declarada; puede usar default o entorno del proceso | `config/mail.php:70` |
| `MAIL_TIMEOUT` | No declarada; puede usar default o entorno del proceso | `config/mail.php:48` |
| `MAIL_URL` | No declarada; puede usar default o entorno del proceso | `config/mail.php:43` |
| `MAIL_USERNAME` | Presente, valor omitido | `config/mail.php:46` |
| `MEMCACHED_HOST` | Presente, valor omitido | `config/cache.php:68` |
| `MEMCACHED_PASSWORD` | No declarada; puede usar default o entorno del proceso | `config/cache.php:61` |
| `MEMCACHED_PERSISTENT_ID` | No declarada; puede usar default o entorno del proceso | `config/cache.php:58` |
| `MEMCACHED_PORT` | No declarada; puede usar default o entorno del proceso | `config/cache.php:69` |
| `MEMCACHED_USERNAME` | No declarada; puede usar default o entorno del proceso | `config/cache.php:60` |
| `MYSQL_ATTR_SSL_CA` | No declarada; puede usar default o entorno del proceso | `config/database.php:63`<br>`config/database.php:83` |
| `PAPERTRAIL_PORT` | No declarada; puede usar default o entorno del proceso | `config/logging.php:91`<br>`config/logging.php:92` |
| `PAPERTRAIL_URL` | No declarada; puede usar default o entorno del proceso | `config/logging.php:90`<br>`config/logging.php:92` |
| `POSTMARK_API_KEY` | No declarada; puede usar default o entorno del proceso | `config/services.php:18` |
| `POSTMARK_MESSAGE_STREAM_ID` | No declarada; puede usar default o entorno del proceso | `config/mail.php:58` |
| `QUEUE_CONNECTION` | Presente, valor omitido | `config/queue.php:16` |
| `QUEUE_FAILED_DRIVER` | No declarada; puede usar default o entorno del proceso | `config/queue.php:124` |
| `REDIS_BACKOFF_ALGORITHM` | No declarada; puede usar default o entorno del proceso | `config/database.php:164`<br>`config/database.php:177` |
| `REDIS_BACKOFF_BASE` | No declarada; puede usar default o entorno del proceso | `config/database.php:165`<br>`config/database.php:178` |
| `REDIS_BACKOFF_CAP` | No declarada; puede usar default o entorno del proceso | `config/database.php:166`<br>`config/database.php:179` |
| `REDIS_CACHE_CONNECTION` | No declarada; puede usar default o entorno del proceso | `config/cache.php:77` |
| `REDIS_CACHE_DB` | No declarada; puede usar default o entorno del proceso | `config/database.php:175` |
| `REDIS_CACHE_LOCK_CONNECTION` | No declarada; puede usar default o entorno del proceso | `config/cache.php:78` |
| `REDIS_CLIENT` | Presente, valor omitido | `config/database.php:148` |
| `REDIS_CLUSTER` | No declarada; puede usar default o entorno del proceso | `config/database.php:151` |
| `REDIS_DB` | No declarada; puede usar default o entorno del proceso | `config/database.php:162` |
| `REDIS_HOST` | Presente, valor omitido | `config/database.php:158`<br>`config/database.php:171` |
| `REDIS_MAX_RETRIES` | No declarada; puede usar default o entorno del proceso | `config/database.php:163`<br>`config/database.php:176` |
| `REDIS_PASSWORD` | Presente, valor omitido | `config/database.php:160`<br>`config/database.php:173` |
| `REDIS_PERSISTENT` | No declarada; puede usar default o entorno del proceso | `config/database.php:153` |
| `REDIS_PORT` | Presente, valor omitido | `config/database.php:161`<br>`config/database.php:174` |
| `REDIS_PREFIX` | No declarada; puede usar default o entorno del proceso | `config/database.php:152` |
| `REDIS_QUEUE` | No declarada; puede usar default o entorno del proceso | `config/queue.php:70` |
| `REDIS_QUEUE_CONNECTION` | No declarada; puede usar default o entorno del proceso | `config/queue.php:69` |
| `REDIS_QUEUE_RETRY_AFTER` | No declarada; puede usar default o entorno del proceso | `config/queue.php:71` |
| `REDIS_URL` | No declarada; puede usar default o entorno del proceso | `config/database.php:157`<br>`config/database.php:170` |
| `REDIS_USERNAME` | No declarada; puede usar default o entorno del proceso | `config/database.php:159`<br>`config/database.php:172` |
| `RESEND_API_KEY` | No declarada; puede usar default o entorno del proceso | `config/services.php:22` |
| `RESEND_FROM_EMAIL` | No declarada; puede usar default o entorno del proceso | `config/services.php:23` |
| `SESSION_CONNECTION` | No declarada; puede usar default o entorno del proceso | `config/session.php:76` |
| `SESSION_DOMAIN` | Presente, valor omitido | `config/session.php:159` |
| `SESSION_DRIVER` | Presente, valor omitido | `config/session.php:21` |
| `SESSION_ENCRYPT` | Presente, valor omitido | `config/session.php:50` |
| `SESSION_EXPIRE_ON_CLOSE` | No declarada; puede usar default o entorno del proceso | `config/session.php:37` |
| `SESSION_HTTP_ONLY` | No declarada; puede usar default o entorno del proceso | `config/session.php:185` |
| `SESSION_LIFETIME` | Presente, valor omitido | `config/session.php:35` |
| `SESSION_PARTITIONED_COOKIE` | No declarada; puede usar default o entorno del proceso | `config/session.php:215` |
| `SESSION_PATH` | Presente, valor omitido | `config/session.php:146` |
| `SESSION_SAME_SITE` | No declarada; puede usar default o entorno del proceso | `config/session.php:202` |
| `SESSION_SECURE_COOKIE` | No declarada; puede usar default o entorno del proceso | `config/session.php:172` |
| `SESSION_STORE` | No declarada; puede usar default o entorno del proceso | `config/session.php:104` |
| `SESSION_TABLE` | No declarada; puede usar default o entorno del proceso | `config/session.php:89` |
| `SLACK_BOT_USER_DEFAULT_CHANNEL` | No declarada; puede usar default o entorno del proceso | `config/services.php:35` |
| `SLACK_BOT_USER_OAUTH_TOKEN` | No declarada; puede usar default o entorno del proceso | `config/services.php:34` |
| `SQS_PREFIX` | No declarada; puede usar default o entorno del proceso | `config/queue.php:60` |
| `SQS_QUEUE` | No declarada; puede usar default o entorno del proceso | `config/queue.php:61` |
| `SQS_SUFFIX` | No declarada; puede usar default o entorno del proceso | `config/queue.php:62` |
| `SWGPI_LEGACY_PUBLIC_FALLBACK` | No declarada; puede usar default o entorno del proceso | `config/uploads.php:6` |
| `SWGPI_MAX_UPLOAD_MB` | No declarada; puede usar default o entorno del proceso | `config/uploads.php:16` |
| `SWGPI_PRIVATE_STORAGE_PATH` | No declarada; puede usar default o entorno del proceso | `config/filesystems.php:61` |
| `SWGPI_PRIVATE_UPLOADS` | No declarada; puede usar default o entorno del proceso | `config/uploads.php:4`<br>`config/uploads.php:12` |
| `SWGPI_PUBLIC_STORAGE_PATH` | No declarada; puede usar default o entorno del proceso | `config/filesystems.php:52` |
| `SWGPI_TEMPORARY_DISK` | No declarada; puede usar default o entorno del proceso | `config/uploads.php:8` |
| `SWGPI_X_ACCEL_DOWNLOADS` | No declarada; puede usar default o entorno del proceso | `config/uploads.php:5` |
| `VITE_APP_NAME` | Presente, valor omitido | Sin llamada env() directa identificada en app/config/bootstrap; puede ser consumida por framework, Vite o despliegue |

## app.php

Fuente: [app.php](../../config/app.php).

<details>
<summary>Configuración declarada y valores predeterminados del código</summary>

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => env('APP_TIMEZONE', 'America/Mexico_City'),

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
```

</details>


## auth.php

Fuente: [auth.php](../../config/auth.php).

<details>
<summary>Configuración declarada y valores predeterminados del código</summary>

```php
<?php

use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    'activity_cache_store' => env('AUTH_ACTIVITY_CACHE_STORE', 'file'),

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | which utilizes session storage plus the Eloquent user provider.
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | Supported: "session"
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        
        'api' => [
            'driver' => 'jwt',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],

        // 'users' => [
        //     'driver' => 'database',
        //     'table' => 'users',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the number of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
```

</details>


## cache.php

Fuente: [cache.php](../../config/cache.php).

<details>
<summary>Configuración declarada y valores predeterminados del código</summary>

```php
<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Cache Store
    |--------------------------------------------------------------------------
    |
    | This option controls the default cache store that will be used by the
    | framework. This connection is utilized if another isn't explicitly
    | specified when running a cache operation inside the application.
    |
    */

    'default' => env('CACHE_STORE', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Cache Stores
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the cache "stores" for your application as
    | well as their drivers. You may even define multiple stores for the
    | same cache driver to group types of items stored in your caches.
    |
    | Supported drivers: "array", "database", "file", "memcached",
    |                    "redis", "dynamodb", "octane",
    |                    "failover", "null"
    |
    */

    'stores' => [

        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CACHE_CONNECTION'),
            'table' => env('DB_CACHE_TABLE', 'cache'),
            'lock_connection' => env('DB_CACHE_LOCK_CONNECTION'),
            'lock_table' => env('DB_CACHE_LOCK_TABLE'),
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
            'lock_path' => storage_path('framework/cache/data'),
        ],

        'memcached' => [
            'driver' => 'memcached',
            'persistent_id' => env('MEMCACHED_PERSISTENT_ID'),
            'sasl' => [
                env('MEMCACHED_USERNAME'),
                env('MEMCACHED_PASSWORD'),
            ],
            'options' => [
                // Memcached::OPT_CONNECT_TIMEOUT => 2000,
            ],
            'servers' => [
                [
                    'host' => env('MEMCACHED_HOST', '127.0.0.1'),
                    'port' => env('MEMCACHED_PORT', 11211),
                    'weight' => 100,
                ],
            ],
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_CACHE_CONNECTION', 'cache'),
            'lock_connection' => env('REDIS_CACHE_LOCK_CONNECTION', 'default'),
        ],

        'dynamodb' => [
            'driver' => 'dynamodb',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'table' => env('DYNAMODB_CACHE_TABLE', 'cache'),
            'endpoint' => env('DYNAMODB_ENDPOINT'),
        ],

        'octane' => [
            'driver' => 'octane',
        ],

        'failover' => [
            'driver' => 'failover',
            'stores' => [
                'database',
                'array',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | When utilizing the APC, database, memcached, Redis, and DynamoDB cache
    | stores, there might be other applications using the same cache. For
    | that reason, you may prefix every cache key to avoid collisions.
    |
    */

    'prefix' => env('CACHE_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-cache-'),

    /*
    |--------------------------------------------------------------------------
    | Serializable Classes
    |--------------------------------------------------------------------------
    |
    | This value determines the classes that can be unserialized from cache
    | storage. By default, no PHP classes will be unserialized from your
    | cache to prevent gadget chain attacks if your APP_KEY is leaked.
    |
    */

    'serializable_classes' => false,

];
```

</details>


## continuity.php

Fuente: [continuity.php](../../config/continuity.php).

<details>
<summary>Configuración declarada y valores predeterminados del código</summary>

```php
<?php

return [
    'target_readiness' => (int) env('CONTINUITY_TARGET_READINESS', 100),
    'critical_readiness' => (int) env('CONTINUITY_CRITICAL_READINESS', 75),
    'max_backup_age_hours' => (int) env('CONTINUITY_MAX_BACKUP_AGE_HOURS', 26),
];
```

</details>


## cors.php

Fuente: [cors.php](../../config/cors.php).

<details>
<summary>Configuración declarada y valores predeterminados del código</summary>

```php
<?php

$frontendOrigins = array_filter(array_map(
    'trim',
    explode(',', env('FRONTEND_URLS', ''))
));

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin requests can be executed
    | by this Laravel application. The values that you may set here are
    | discussed in the Mozilla web docs on CORS encountered here:
    |
    | https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS#The_HTTP_request_headers
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_unique(array_merge([
        'http://127.0.0.1:3000',
        'http://localhost:3000',
        'http://127.0.0.1:3004',
        'http://localhost:3004',
        'http://127.0.0.1:3005',
        'http://localhost:3005',
        'http://127.0.0.1:5173',
        'http://localhost:5173',
        'http://127.0.0.1:8081',
        'http://localhost:8081',
        'http://192.168.100.80:8080',
        'http://127.0.0.1:19006',
        'http://localhost:19006',
        'http://127.0.0.1',
        'http://localhost',
        'https://127.0.0.1',
        'https://localhost',
        'http://localhost:8000',
        'http://localhost:8001',
        'capacitor://localhost',
        'ionic://localhost',
        'http://frontend_swgpi.test',
        'http://127.0.0.1:8000',
        'https://swgpi.online',
        'https://www.swgpi.online',
        'https://frontsgwpi-production.up.railway.app',
    ], $frontendOrigins))),

    'allowed_origins_patterns' => [
        '#^https://([a-z0-9-]+\.)?swgpi\.online$#i',
        '#^https?://localhost(?::\d+)?$#i',
        '#^https?://127\.0\.0\.1(?::\d+)?$#i',
    ],

    'allowed_headers' => [
        'Authorization',
        'Content-Type',
        'Accept',
        'Origin',
        'X-Requested-With',
        'X-SGPI-Remember',
    ],

    'exposed_headers' => [],

    'max_age' => 600,

    'supports_credentials' => true,

];
```

</details>


## database.php

Fuente: [database.php](../../config/database.php).

<details>
<summary>Configuración declarada y valores predeterminados del código</summary>

```php
<?php

use Illuminate\Support\Str;
use Pdo\Mysql;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    'default' => env('DB_CONNECTION', 'sqlite'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                (PHP_VERSION_ID >= 80500 ? Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA) => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                (PHP_VERSION_ID >= 80500 ? Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA) => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('DB_SSLMODE', 'prefer'),
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

    ],

];
```

</details>


## filesystems.php

Fuente: [filesystems.php](../../config/filesystems.php).

<details>
<summary>Configuración declarada y valores predeterminados del código</summary>

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'swgpi_public' => [
            'driver' => 'local',
            'root' => env('SWGPI_PUBLIC_STORAGE_PATH', storage_path('app/public')),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => true,
            'report' => true,
        ],

        'swgpi_private' => [
            'driver' => 'local',
            'root' => env('SWGPI_PRIVATE_STORAGE_PATH', storage_path('app/private')),
            'visibility' => 'private',
            'throw' => true,
            'report' => true,
        ],

        'legacy_public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => true,
            'report' => true,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
```

</details>


## jwt.php

Fuente: [jwt.php](../../config/jwt.php).

<details>
<summary>Configuración declarada y valores predeterminados del código</summary>

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | JWT Authentication Secret
    |--------------------------------------------------------------------------
    |
    | This value signs the access tokens used by the API guard. It must be set
    | in every deployed environment. Generate it with: php artisan jwt:secret
    |
    */
    'secret' => env('JWT_SECRET'),

    'keys' => [
        'public' => env('JWT_PUBLIC_KEY'),
        'private' => env('JWT_PRIVATE_KEY'),
        'passphrase' => env('JWT_PASSPHRASE'),
    ],

    'ttl' => env('JWT_TTL', env('JWT_ABSOLUTE_TTL', 480)),
    'absolute_ttl' => env('JWT_ABSOLUTE_TTL', 480),
    'refresh_ttl' => env('JWT_REFRESH_TTL', 43200),
    'algo' => env('JWT_ALGO', Tymon\JWTAuth\Providers\JWT\Provider::ALGO_HS256),

    'required_claims' => [
        'iss',
        'iat',
        'exp',
        'nbf',
        'sub',
        'jti',
    ],

    'persistent_claims' => ['remember'],

    'lock_subject' => true,
    'leeway' => env('JWT_LEEWAY', 0),

    'blacklist_enabled' => env('JWT_BLACKLIST_ENABLED', true),
    'blacklist_grace_period' => env('JWT_BLACKLIST_GRACE_PERIOD', 0),
    'decrypt_cookies' => false,

    'providers' => [
        'jwt' => Tymon\JWTAuth\Providers\JWT\Lcobucci::class,
        'auth' => Tymon\JWTAuth\Providers\Auth\Illuminate::class,
        'storage' => Tymon\JWTAuth\Providers\Storage\Illuminate::class,
    ],
];
```

</details>


## logging.php

Fuente: [logging.php](../../config/logging.php).

<details>
<summary>Configuración declarada y valores predeterminados del código</summary>

```php
<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that is utilized to write
    | messages to your logs. The value provided here should match one of
    | the channels present in the list of "channels" configured below.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Laravel
    | utilizes the Monolog PHP logging library, which includes a variety
    | of powerful log handlers and formatters that you're free to use.
    |
    | Available drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog", "custom", "stack"
    |
    */

    'channels' => [

        'stack' => [
            'driver' => 'stack',
            'channels' => explode(',', (string) env('LOG_STACK', 'single')),
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => env('LOG_DAILY_DAYS', 14),
            'replace_placeholders' => true,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => env('LOG_SLACK_USERNAME', env('APP_NAME', 'Laravel')),
            'emoji' => env('LOG_SLACK_EMOJI', ':boom:'),
            'level' => env('LOG_LEVEL', 'critical'),
            'replace_placeholders' => true,
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => env('LOG_PAPERTRAIL_HANDLER', SyslogUdpHandler::class),
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
                'connectionString' => 'tls://'.env('PAPERTRAIL_URL').':'.env('PAPERTRAIL_PORT'),
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'handler_with' => [
                'stream' => 'php://stderr',
            ],
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
            'facility' => env('LOG_SYSLOG_FACILITY', LOG_USER),
            'replace_placeholders' => true,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

    ],

];
```

</details>


## mail.php

Fuente: [mail.php](../../config/mail.php).

<details>
<summary>Configuración declarada y valores predeterminados del código</summary>

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message. All additional mailers can be configured within the
    | "mailers" array. Examples of each type of mailer are provided.
    |
    */

    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers that can be used
    | when delivering an email. You may specify which one you're using for
    | your mailers below. You may also add additional mailers if needed.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "resend", "log", "array",
    |            "failover", "roundrobin"
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => env('MAIL_TIMEOUT', 8),
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
            'retry_after' => 60,
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
            'retry_after' => 60,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Laravel')),
    ],

];
```

</details>


## queue.php

Fuente: [queue.php](../../config/queue.php).

<details>
<summary>Configuración declarada y valores predeterminados del código</summary>

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection Name
    |--------------------------------------------------------------------------
    |
    | Laravel's queue supports a variety of backends via a single, unified
    | API, giving you convenient access to each backend using identical
    | syntax for each. The default queue connection is defined below.
    |
    */

    'default' => env('QUEUE_CONNECTION', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Queue Connections
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection options for every queue backend
    | used by your application. An example configuration is provided for
    | each backend supported by Laravel. You're also free to add more.
    |
    | Drivers: "sync", "database", "beanstalkd", "sqs", "redis",
    |          "deferred", "background", "failover", "null"
    |
    */

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_QUEUE_CONNECTION'),
            'table' => env('DB_QUEUE_TABLE', 'jobs'),
            'queue' => env('DB_QUEUE', 'default'),
            'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 90),
            'after_commit' => false,
        ],

        'beanstalkd' => [
            'driver' => 'beanstalkd',
            'host' => env('BEANSTALKD_QUEUE_HOST', 'localhost'),
            'queue' => env('BEANSTALKD_QUEUE', 'default'),
            'retry_after' => (int) env('BEANSTALKD_QUEUE_RETRY_AFTER', 90),
            'block_for' => 0,
            'after_commit' => false,
        ],

        'sqs' => [
            'driver' => 'sqs',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'prefix' => env('SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id'),
            'queue' => env('SQS_QUEUE', 'default'),
            'suffix' => env('SQS_SUFFIX'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'after_commit' => false,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 90),
            'block_for' => null,
            'after_commit' => false,
        ],

        'deferred' => [
            'driver' => 'deferred',
        ],

        'background' => [
            'driver' => 'background',
        ],

        'failover' => [
            'driver' => 'failover',
            'connections' => [
                'database',
                'deferred',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Job Batching
    |--------------------------------------------------------------------------
    |
    | The following options configure the database and table that store job
    | batching information. These options can be updated to any database
    | connection and table which has been defined by your application.
    |
    */

    'batching' => [
        'database' => env('DB_CONNECTION', 'sqlite'),
        'table' => 'job_batches',
    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Queue Jobs
    |--------------------------------------------------------------------------
    |
    | These options configure the behavior of failed queue job logging so you
    | can control how and where failed jobs are stored. Laravel ships with
    | support for storing failed jobs in a simple file or in a database.
    |
    | Supported drivers: "database-uuids", "dynamodb", "file", "null"
    |
    */

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'sqlite'),
        'table' => 'failed_jobs',
    ],

];
```

</details>


## services.php

Fuente: [services.php](../../config/services.php).

<details>
<summary>Configuración declarada y valores predeterminados del código</summary>

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
        'from' => env('RESEND_FROM_EMAIL', env('MAIL_FROM_ADDRESS', 'onboarding@resend.dev')),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
```

</details>


## session.php

Fuente: [session.php](../../config/session.php).

<details>
<summary>Configuración declarada y valores predeterminados del código</summary>

```php
<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Session Driver
    |--------------------------------------------------------------------------
    |
    | This option determines the default session driver that is utilized for
    | incoming requests. Laravel supports a variety of storage options to
    | persist session data. Database storage is a great default choice.
    |
    | Supported: "file", "cookie", "database", "memcached",
    |            "redis", "dynamodb", "array"
    |
    */

    'driver' => env('SESSION_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Session Lifetime
    |--------------------------------------------------------------------------
    |
    | Here you may specify the number of minutes that you wish the session
    | to be allowed to remain idle before it expires. If you want them
    | to expire immediately when the browser is closed then you may
    | indicate that via the expire_on_close configuration option.
    |
    */

    'lifetime' => (int) env('SESSION_LIFETIME', 120),

    'expire_on_close' => env('SESSION_EXPIRE_ON_CLOSE', false),

    /*
    |--------------------------------------------------------------------------
    | Session Encryption
    |--------------------------------------------------------------------------
    |
    | This option allows you to easily specify that all of your session data
    | should be encrypted before it's stored. All encryption is performed
    | automatically by Laravel and you may use the session like normal.
    |
    */

    'encrypt' => env('SESSION_ENCRYPT', false),

    /*
    |--------------------------------------------------------------------------
    | Session File Location
    |--------------------------------------------------------------------------
    |
    | When utilizing the "file" session driver, the session files are placed
    | on disk. The default storage location is defined here; however, you
    | are free to provide another location where they should be stored.
    |
    */

    'files' => storage_path('framework/sessions'),

    /*
    |--------------------------------------------------------------------------
    | Session Database Connection
    |--------------------------------------------------------------------------
    |
    | When using the "database" or "redis" session drivers, you may specify a
    | connection that should be used to manage these sessions. This should
    | correspond to a connection in your database configuration options.
    |
    */

    'connection' => env('SESSION_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Session Database Table
    |--------------------------------------------------------------------------
    |
    | When using the "database" session driver, you may specify the table to
    | be used to store sessions. Of course, a sensible default is defined
    | for you; however, you're welcome to change this to another table.
    |
    */

    'table' => env('SESSION_TABLE', 'sessions'),

    /*
    |--------------------------------------------------------------------------
    | Session Cache Store
    |--------------------------------------------------------------------------
    |
    | When using one of the framework's cache driven session backends, you may
    | define the cache store which should be used to store the session data
    | between requests. This must match one of your defined cache stores.
    |
    | Affects: "dynamodb", "memcached", "redis"
    |
    */

    'store' => env('SESSION_STORE'),

    /*
    |--------------------------------------------------------------------------
    | Session Sweeping Lottery
    |--------------------------------------------------------------------------
    |
    | Some session drivers must manually sweep their storage location to get
    | rid of old sessions from storage. Here are the chances that it will
    | happen on a given request. By default, the odds are 2 out of 100.
    |
    */

    'lottery' => [2, 100],

    /*
    |--------------------------------------------------------------------------
    | Session Cookie Name
    |--------------------------------------------------------------------------
    |
    | Here you may change the name of the session cookie that is created by
    | the framework. Typically, you should not need to change this value
    | since doing so does not grant a meaningful security improvement.
    |
    */

    'cookie' => env(
        'SESSION_COOKIE',
        Str::slug((string) env('APP_NAME', 'laravel')).'-session'
    ),

    /*
    |--------------------------------------------------------------------------
    | Session Cookie Path
    |--------------------------------------------------------------------------
    |
    | The session cookie path determines the path for which the cookie will
    | be regarded as available. Typically, this will be the root path of
    | your application, but you're free to change this when necessary.
    |
    */

    'path' => env('SESSION_PATH', '/'),

    /*
    |--------------------------------------------------------------------------
    | Session Cookie Domain
    |--------------------------------------------------------------------------
    |
    | This value determines the domain and subdomains the session cookie is
    | available to. By default, the cookie will be available to the root
    | domain without subdomains. Typically, this shouldn't be changed.
    |
    */

    'domain' => env('SESSION_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | HTTPS Only Cookies
    |--------------------------------------------------------------------------
    |
    | By setting this option to true, session cookies will only be sent back
    | to the server if the browser has a HTTPS connection. This will keep
    | the cookie from being sent to you when it can't be done securely.
    |
    */

    'secure' => env('SESSION_SECURE_COOKIE'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Access Only
    |--------------------------------------------------------------------------
    |
    | Setting this value to true will prevent JavaScript from accessing the
    | value of the cookie and the cookie will only be accessible through
    | the HTTP protocol. It's unlikely you should disable this option.
    |
    */

    'http_only' => env('SESSION_HTTP_ONLY', true),

    /*
    |--------------------------------------------------------------------------
    | Same-Site Cookies
    |--------------------------------------------------------------------------
    |
    | This option determines how your cookies behave when cross-site requests
    | take place, and can be used to mitigate CSRF attacks. By default, we
    | will set this value to "lax" to permit secure cross-site requests.
    |
    | See: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Set-Cookie#samesitesamesite-value
    |
    | Supported: "lax", "strict", "none", null
    |
    */

    'same_site' => env('SESSION_SAME_SITE', 'lax'),

    /*
    |--------------------------------------------------------------------------
    | Partitioned Cookies
    |--------------------------------------------------------------------------
    |
    | Setting this value to true will tie the cookie to the top-level site for
    | a cross-site context. Partitioned cookies are accepted by the browser
    | when flagged "secure" and the Same-Site attribute is set to "none".
    |
    */

    'partitioned' => env('SESSION_PARTITIONED_COOKIE', false),

    /*
    |--------------------------------------------------------------------------
    | Session Serialization
    |--------------------------------------------------------------------------
    |
    | This value controls the serialization strategy for session data, which
    | is JSON by default. Setting this to "php" allows the storage of PHP
    | objects in the session but can make an application vulnerable to
    | "gadget chain" serialization attacks if the APP_KEY is leaked.
    |
    | Supported: "json", "php"
    |
    */

    'serialization' => 'json',

];
```

</details>


## uploads.php

Fuente: [uploads.php](../../config/uploads.php).

<details>
<summary>Configuración declarada y valores predeterminados del código</summary>

```php
<?php

return [
    'private_uploads' => (bool) env('SWGPI_PRIVATE_UPLOADS', false),
    'x_accel_downloads' => (bool) env('SWGPI_X_ACCEL_DOWNLOADS', false),
    'legacy_public_fallback' => (bool) env('SWGPI_LEGACY_PUBLIC_FALLBACK', true),

    'temporary_disk' => env('SWGPI_TEMPORARY_DISK', 'local'),
    'private_disk' => 'swgpi_private',
    'public_disk' => 'swgpi_public',
    'legacy_disk' => 'legacy_public',
    'write_disk' => env('SWGPI_PRIVATE_UPLOADS', false) ? 'swgpi_private' : 'legacy_public',

    'temporary_directory' => '.temporary/uploads',
    'x_accel_prefix' => '/_swgpi_private',
    'max_size_mb' => (int) env('SWGPI_MAX_UPLOAD_MB', 50),

    'default_extensions' => [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip',
        'txt', 'jpg', 'jpeg', 'png', 'webp', 'epub', 'rar', '7z',
    ],

    'executable_extensions' => [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar',
        'cgi', 'pl', 'py', 'sh', 'bash', 'exe', 'dll', 'com', 'bat', 'cmd',
        'msi', 'js', 'mjs', 'html', 'htm', 'svg', 'jar', 'scr', 'vbs',
    ],
];
```

</details>

