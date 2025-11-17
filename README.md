# Official Apex Toolbox SDK for Symfony

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.0-blue)](https://php.net)
[![Symfony](https://img.shields.io/badge/symfony-5.4%20%7C%206.x%20%7C%207.x-green)](https://symfony.com)

This is the official Symfony SDK for [Apex Toolbox](https://apextoolbox.com/) - Automatic logging, error tracking, and request monitoring for Symfony applications.

## Getting Started

### Install

```bash
composer require apextoolbox/symfony-logger
```

### Configure

1. Register the bundle in `config/bundles.php` (if not auto-registered):

```php
return [
    // ...
    ApexToolbox\SymfonyLogger\ApexToolboxBundle::class => ['all' => true],
];
```

2. Add your token to `.env`:

```env
APEX_TOOLBOX_TOKEN=your_token_here
```

3. Create `config/packages/apex_toolbox.yaml`:

```yaml
apex_toolbox:
    enabled: true
    token: '%env(resolve:APEX_TOOLBOX_TOKEN)%'

    path_filters:
        include: ['*']           # Track all routes
        exclude: ['_profiler/*'] # Exclude Symfony profiler
```

### Usage

That's it! All logs, exceptions, HTTP requests, and database queries are automatically tracked:

```php
// Standard Monolog logging - automatically sent to ApexToolbox
$logger->info('User created', ['user_id' => 123]);
$logger->error('Payment failed', ['order_id' => 456]);
$logger->warning('Slow query detected', ['duration' => 2.5]);

// Uncaught exceptions are automatically captured
throw new Exception('Something went wrong');

// HTTP requests are automatically monitored
// GET /api/users - tracked with full request/response data

// Console commands are tracked
// php bin/console app:process-orders - all logs correlated with request_id

// Queue jobs are tracked
// All Messenger handler logs are captured and correlated
```

## What's Tracked Automatically

- **All logs** - Via Monolog integration with source file/line/class tracking
- **HTTP requests** - Request/response data, headers, payload, performance metrics
- **Exceptions** - Full stack traces with code context
- **Database queries** - SQL queries with N+1 detection (Doctrine DBAL)
- **Console commands** - All command execution logs
- **Queue jobs** - Messenger handler execution logs
- **Correlation** - All data linked via UUID v7 request_id
- **Security** - Sensitive data (passwords, tokens, API keys) filtered by default

## Configuration

The bundle is configured in `config/packages/apex_toolbox.yaml`:

```yaml
apex_toolbox:
    enabled: true
    token: '%env(resolve:APEX_TOOLBOX_TOKEN)%'

    # Path filtering - which routes to track
    path_filters:
        include: ['*']            # Track all routes (or 'api/*' for API only)
        exclude: ['_profiler/*']  # Skip Symfony profiler

    # Request/response security filtering
    headers:
        exclude: ['authorization', 'cookie', 'x-api-key']
        include_sensitive: false

    body:
        exclude: ['password', 'password_confirmation', 'token', 'secret', 'api_key']
        mask: ['email', 'phone', 'ssn']
        max_size: 10240  # Maximum body size in bytes

    response:
        exclude: ['password', 'token', 'secret']
        mask: ['email', 'phone', 'ssn']
```

## Key Features

### Automatic Request Correlation
All logs, exceptions, and queries from the same request/command/job share a unique `request_id` (UUID v7):
- HTTP requests get a request_id on kernel.request
- Console commands get a request_id on console.command
- Queue jobs get a request_id on message received
- All related data is linked for easy debugging

### Database Query Tracking
If Doctrine DBAL is installed, queries are automatically tracked:
- SQL queries with bindings
- Query duration
- Duplicate query detection
- **N+1 query detection** - automatic pattern-based detection
- Source location (file, line)

### Source Introspection
All logs include source information via Monolog's IntrospectionProcessor:
- File path
- Line number
- Class name
- Function name

### Independent Data Transmission
Each type of data is sent independently but correlated:
- Logs can be sent without HTTP requests
- Exceptions tracked even outside HTTP context
- Console/queue logs work standalone
- Everything linked via request_id for correlation

## Requirements

- **PHP 8.0 or higher**
- **Symfony 5.4, 6.x, or 7.x**
- Monolog 2.x or 3.x (installed automatically)
- Optional: Doctrine DBAL for query tracking

## Resources

- [Documentation](https://apextoolbox.com/docs)
- [Issues](https://github.com/apextoolbox/symfony/issues)

## License

Licensed under the [MIT License](https://github.com/apextoolbox/symfony/blob/main/LICENSE).
