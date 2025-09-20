# Official Apex Toolbox SDK for Symfony

[![Tests](https://img.shields.io/badge/tests-45%20passed-brightgreen)](https://github.com/apextoolbox/symfony-logger)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue)](https://php.net)
[![Symfony](https://img.shields.io/badge/symfony-5.4%20%7C%206.x%20%7C%207.x-green)](https://symfony.com)

This is the official Symfony SDK for [Apex Toolbox](https://apextoolbox.com/).

## Installation

Install the bundle:

```bash
composer require apextoolbox/symfony-logger
```

Add your token to `.env`:

```env
APEX_TOOLBOX_TOKEN=your_token_here
```

Add the bundle to `config/bundles.php`:

```php
return [
    ApexToolbox\SymfonyLogger\ApexToolboxLoggerBundle::class => ['all' => true],
];
```

Configure Monolog in `config/packages/monolog.yaml`:

```yaml
monolog:
    handlers:
        apex_toolbox:
            type: service
            id: ApexToolbox\SymfonyLogger\Handler\ApexToolboxLogHandler
            level: debug
        main:
            type: stream
            path: "%kernel.logs_dir%/%kernel.environment%.log"
            level: debug
```

Create `config/packages/apex_toolbox_logger.yaml`:

```yaml
apex_toolbox_logger:
    token: '%env(APEX_TOOLBOX_TOKEN)%'
    enabled: true
```

## Usage

All your existing logs are automatically sent to Apex Toolbox:

```php
$logger->info('User created', ['user_id' => 123]);
$logger->error('Payment failed', ['order_id' => 456]);
```

## HTTP Request Tracking

HTTP requests are automatically tracked by the event listener. The bundle captures:

- Request/response data
- Exceptions with stack traces
- Log entries during request processing
- Performance metrics (duration, memory usage)

## Exception Handling

Exceptions are automatically captured with:

- Complete stack traces with code context
- Exception grouping via SHA-256 hashing
- Source code context (10 lines before, 5 after error)
- App vs vendor code identification
- Relative file paths for security

```php
// Exceptions are automatically captured - no additional code needed
throw new Exception('Something went wrong');
```

## Configuration

Create or update `config/packages/apex_toolbox_logger.yaml`:

```yaml
apex_toolbox_logger:
    token: '%env(APEX_TOOLBOX_TOKEN)%'
    enabled: true

    # Path filtering for HTTP tracking
    path_filters:
        include:
            - 'api/*'        # Track all API routes
            # - '*'          # Uncomment to track ALL routes
        exclude:
            - 'api/health'   # Skip health checks
            - 'api/ping'     # Skip ping endpoints

    # Header filtering
    headers:
        exclude:
            - 'authorization'
            - 'x-api-key'
            - 'cookie'

    # Request body filtering
    body:
        exclude:
            - 'password'
            - 'password_confirmation'
            - 'token'
            - 'secret'
            - 'access_token'
            - 'refresh_token'
            - 'api_key'
            - 'private_key'
        mask:
            - 'ssn'
            - 'social_security'
            - 'phone'
            - 'email'
            - 'address'
            - 'postal_code'
            - 'zip_code'

    # Response filtering
    response:
        exclude:
            - 'password'
            - 'token'
            - 'secret'
            - 'access_token'
            - 'refresh_token'
            - 'api_key'
            - 'private_key'
        mask:
            - 'ssn'
            - 'social_security'
            - 'phone'
            - 'email'

    # Universal logging (console commands, workers)
    universal_logging:
        enabled: true
        types:
            - 'http'
            - 'console'
            - 'queue'
```

## Security Configuration

**⚠️ IMPORTANT SECURITY NOTICE**: This package automatically filters sensitive data from logs to protect your users' privacy. The default configuration excludes common sensitive fields from headers, request bodies, and responses.

### Data Filtering Options

You have two options for protecting sensitive data:

**1. Exclude (Complete Removal)**
- Fields listed in `exclude` arrays are completely removed from logs
- Use for highly sensitive data like passwords, tokens, API keys
- Data structure changes (field disappears entirely)

**2. Mask (Value Replacement)**
- Fields listed in `mask` arrays are replaced with `'*******'`
- Use for PII that you want to track structurally but hide values
- Data structure preserved (field exists but value is masked)
- Works recursively in nested objects/arrays
- Case-insensitive matching (`SSN`, `ssn`, `Ssn` all match)

**Example:**
```php
// Input data
[
    'user' => [
        'name' => 'John Doe',
        'password' => 'secret123',      // Will be excluded (removed)
        'ssn' => '123-45-6789',        // Will be masked to '*******'
        'profile' => [
            'email' => 'john@test.com', // Will be masked to '*******'
            'token' => 'bearer-xyz'     // Will be excluded (removed)
        ]
    ]
]

// Logged data
[
    'user' => [
        'name' => 'John Doe',
        'ssn' => '*******',
        'profile' => [
            'email' => '*******'
        ]
    ]
]
```

### Priority Rules

- **Exclude takes precedence over mask**: If a field appears in both lists, it will be excluded (completely removed)
- **Case-insensitive matching**: `SSN`, `ssn`, and `Ssn` all match the same field
- **Recursive filtering**: Works on deeply nested arrays and objects

## Advanced Configuration

### Path Filtering

Use wildcards to control which routes are tracked:

```yaml
apex_toolbox_logger:
    path_filters:
        include:
            - 'api/*'           # Track all API routes
            - 'admin/*'         # Track admin routes
            - '*'               # Track everything (use with caution)
        exclude:
            - 'api/health'      # Skip specific endpoints
            - 'admin/debug/*'   # Skip debug routes
```

### Console Command Tracking

Enable tracking for console commands:

```yaml
apex_toolbox_logger:
    universal_logging:
        enabled: true
        types:
            - 'console'  # Track console commands
            - 'queue'    # Track queue jobs
            - 'http'     # Track HTTP requests
```

### Development Endpoint

For package development, you can override the API endpoint:

```env
APEX_TOOLBOX_DEV_ENDPOINT=https://dev.apextoolbox.com/api/v1/logs
```

## Features

- **🚀 Automatic Logging**: All logs automatically sent to Apex Toolbox
- **🔍 Exception Tracking**: Complete exception capture with stack traces
- **🛡️ Security First**: Sensitive data filtering with exclude/mask options
- **📊 HTTP Tracking**: Request/response monitoring with performance metrics
- **⚡ Performance**: Optimized with 2-second timeouts and batch processing
- **🔧 Flexible**: Extensive configuration options for all environments
- **🧪 Well Tested**: Comprehensive test suite with 45+ tests

## Troubleshooting

### No Logs Appearing

1. Check your token is set: `echo $APEX_TOOLBOX_TOKEN`
2. Verify bundle is registered in `config/bundles.php`
3. Ensure Monolog handler is configured
4. Check Symfony logs for errors: `tail -f var/log/dev.log`

### Performance Concerns

The bundle is designed for production use:
- 2-second timeout prevents blocking
- Automatic buffer flushing
- Silent failure handling
- Minimal memory footprint

### ⚠️ Security Disclaimer

**YOU ARE RESPONSIBLE** for configuring the sensitive data filters appropriately for your application. While this package provides sensible defaults to protect common sensitive fields, **you must review and customize the exclude lists** to ensure all sensitive data specific to your application is properly filtered.

**The package maintainers are NOT liable** for any sensitive data that may be logged if you:
- Modify or remove the default security filters
- Add custom sensitive fields without proper exclusion
- Disable the filtering mechanisms
- Misconfigure the security settings

Always review your logs to ensure no sensitive data is being transmitted before deploying to production.

## Requirements

- PHP 8.1 or higher
- Symfony 5.4, 6.x, or 7.x
- Monolog for logging

## License

MIT