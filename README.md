# Symfony Logger Bundle

[![Tests](https://img.shields.io/badge/tests-48%20passed-brightgreen)](https://github.com/apextoolbox/symfony-logger)
[![Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen)](https://github.com/apextoolbox/symfony-logger)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue)](https://php.net)
[![Symfony](https://img.shields.io/badge/symfony-5.4%20%7C%206.x%20%7C%207.x-black)](https://symfony.com)

Symfony bundle for logging HTTP requests and responses, sending data to ApexToolbox analytics platform.

## Features

- Event-driven request/response logging using Symfony EventDispatcher
- **Universal log capture**: HTTP requests, console commands, and background jobs
- **Source class detection**: Automatically captures the originating class/service name
- **Asynchronous sending**: Non-blocking log transmission to prevent performance impact
- Configurable path filtering (include/exclude patterns)
- Security-focused: filters sensitive headers and request fields
- Configurable payload size limits
- Silent failure - won't break your application
- Easy configuration management via Symfony config system
- Monolog integration for capturing application logs

## Requirements

- PHP 8.1+
- Symfony 5.4, 6.x, or 7.x

## Installation

Install via Composer:

```bash
composer require apextoolbox/symfony-logger
```

### Enable the Bundle

Add the bundle to your `config/bundles.php`:

```php
return [
    // ... other bundles
    ApexToolbox\SymfonyLogger\ApexToolboxLoggerBundle::class => ['all' => true],
];
```

## Configuration

Create a configuration file at `config/packages/apex_toolbox_logger.yaml`:

```yaml
apex_toolbox_logger:
    enabled: true
    token: '%env(APEX_TOOLBOX_TOKEN)%'
    
    path_filters:
        include:
            - 'api/*'           # Track all API routes
            - 'webhook/*'       # Track webhooks
        exclude:
            - 'api/health'      # Skip health checks
            - 'api/ping'        # Skip ping endpoints
    
    headers:
        include_sensitive: false  # Exclude sensitive headers by default
        exclude:
            - 'authorization'
            - 'x-api-key'
            - 'cookie'
    
    body:
        max_size: 10240          # 10KB limit
        exclude:
            - 'password'
            - 'password_confirmation'
            - 'token'
            - 'secret'
```

### Environment Variables

Add these to your `.env` file:

```env
# Required
APEX_TOOLBOX_TOKEN=your_apextoolbox_token

# Optional
APEX_TOOLBOX_ENABLED=true
```

## Usage

Once configured, the bundle will automatically:

1. **Track HTTP Requests/Responses**: All requests matching your path filters will be logged
2. **Capture Application Logs**: Monolog entries will be included with request data
3. **Universal Log Capture**: Console commands and background jobs can also be tracked
4. **Source Class Detection**: Each log entry includes the originating class/service name
5. **Send Data to ApexToolbox**: Data is sent asynchronously to your ApexToolbox dashboard

### Path Filtering Examples

```yaml
apex_toolbox_logger:
    path_filters:
        include:
            - '*'              # Track all routes
            - 'api/*'          # Track all API routes
            - 'admin/users/*'  # Track specific admin routes
        exclude:
            - 'api/health'     # Skip health checks
            - '*/ping'         # Skip all ping endpoints
            - 'debug/*'        # Skip debug routes
```

### Universal Logging Configuration

To enable logging for console commands and background jobs, add the universal logging configuration:

```yaml
apex_toolbox_logger:
    enabled: true
    token: '%env(APEX_TOOLBOX_TOKEN)%'
    
    # Enable universal logging for all contexts
    universal_logging:
        enabled: true
        types: ['http', 'console', 'queue']  # Which types to capture

    # Standard HTTP logging configuration remains the same
    path_filters:
        include: ['api/*']
        exclude: ['api/health']
```

### Advanced Configuration

```yaml
apex_toolbox_logger:
    enabled: '%env(bool:APEX_TOOLBOX_ENABLED)%'
    token: '%env(APEX_TOOLBOX_TOKEN)%'
    
    # Universal logging options
    universal_logging:
        enabled: true
        types: ['http', 'console']  # Only HTTP and console, skip queue jobs
    
    # Custom headers to exclude
    headers:
        exclude:
            - 'x-internal-secret'
            - 'x-admin-token'
    
    # Custom body fields to exclude
    body:
        max_size: 5120  # 5KB limit
        exclude:
            - 'credit_card'
            - 'ssn'
            - 'private_key'
```

### Monolog Handler Configuration

For comprehensive log capture via Monolog (recommended for console/queue contexts), configure Monolog in your `config/packages/monolog.yaml`:

```yaml
monolog:
    handlers:
        # Your existing handlers...
        
        apex_toolbox:
            type: service
            id: ApexToolbox\SymfonyLogger\Handler\ApexToolboxHandler
            level: debug  # Capture all log levels
```

## Data Collected

The bundle tracks:

**For HTTP Requests:**
- HTTP method and full URL
- Request headers (filtered for security)
- Request body/payload (filtered and size-limited)
- Response status code
- Response content (size-limited)
- Request duration
- Application logs (via Monolog integration)
- Timestamp

**For Console Commands & Background Jobs:**
- Log level (debug, info, warning, error, etc.)
- Log message and context
- **Source class name** (automatically detected)
- **Execution type** (`console` or `queue`)
- Timestamp

**Source Class Detection Examples:**
- `App\Command\ProcessOrdersCommand` for console commands
- `App\Service\NotificationService` for service classes
- `App\Job\SendEmailJob` for queue job classes

## Security Features

- **Sensitive Header Filtering**: Authorization, API keys, and cookies are excluded by default
- **Body Field Filtering**: Password fields and secrets are automatically filtered
- **Size Limitations**: Request/response bodies are limited to prevent memory issues
- **Silent Failures**: Network errors won't interrupt your application flow
- **No Local Storage**: Data is sent directly to ApexToolbox, not stored locally

## Bundle Architecture

- **ApexToolboxLoggerBundle**: Main bundle class
- **LoggerListener**: Handles HTTP request/response events
- **ApexToolboxHandler**: Monolog handler for universal log capture
- **ContextDetector**: Detects execution context (HTTP/console/queue)
- **SourceClassExtractor**: Extracts originating class names from logs
- **LogBuffer**: Thread-safe buffer for collecting log entries
- **Configuration**: Symfony configuration tree definition

## Performance Considerations

- **Asynchronous Sending**: All external requests use non-blocking HTTP calls
- Uses 1-second HTTP timeout for external requests  
- Failed requests are silently ignored to prevent application disruption
- **Console/Queue Logs**: Sent immediately (queue) or on shutdown (console) to avoid blocking
- Consider using path filters to exclude high-traffic, low-value endpoints
- Request/response bodies are size-limited to prevent memory issues
- **Source class detection** uses optimized backtrace analysis with minimal performance impact

## Troubleshooting

### No Data Being Sent

1. Verify `APEX_TOOLBOX_TOKEN` environment variable is set
2. Check bundle is registered in `config/bundles.php`
3. Ensure requests match your path filter patterns
4. Verify `enabled: true` in configuration

### Bundle Not Loading

1. Clear Symfony cache: `php bin/console cache:clear`
2. Check bundle is properly registered
3. Verify configuration syntax in YAML files

### Performance Issues

1. Add more specific path filters to reduce tracking overhead
2. Decrease `body.max_size` limit
3. Exclude high-traffic endpoints in path filters

## Testing Universal Logging

A complete test application is available to demonstrate all universal logging functionality:

**📁 Test Application**: `../symfony-app/`

The test app includes:
- ✅ Console commands with source class detection
- ✅ HTTP endpoints with comprehensive logging  
- ✅ Queue/background job processing
- ✅ Service integration across all contexts
- ✅ Error handling and performance testing

**Quick Test:**
```bash
cd ../symfony-app
composer install
echo "APEX_TOOLBOX_TOKEN=your_token" > .env.local
php bin/console app:test-logger
```

See the test app's README for complete testing instructions and examples.

## Development

## Testing

The bundle includes comprehensive tests covering all functionality:

```bash
# Run tests
composer test

# Run tests with coverage (requires Xdebug)
composer test-coverage
```

### Test Coverage

- **48 tests** covering all public and private methods
- **100% code coverage** across all classes:
  - `LogBuffer` - Static log entry management
  - `ApexToolboxLoggerBundle` - Bundle registration and extension handling
  - `Configuration` - Symfony configuration tree definition
  - `ApexToolboxLoggerExtension` - Dependency injection and service registration
  - `LoggerListener` - HTTP request/response tracking and filtering
  - `LogSubscriber` - Monolog entry capture and console command logging
- Tests include edge cases, error handling, and configuration scenarios

### Running Tests

```bash
composer install
./vendor/bin/phpunit
```

### Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Submit a pull request

## License

MIT License. See LICENSE file for details.

## Support

For support, please contact ApexToolbox or create an issue in the package repository.