# Official Apex Toolbox SDK for Symfony

[![Tests](https://img.shields.io/badge/tests-45%20passed-brightgreen)](https://github.com/apextoolbox/symfony)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D7.4-blue)](https://php.net)
[![Symfony](https://img.shields.io/badge/symfony-5.4%20%7C%206.x%20%7C%207.x-green)](https://symfony.com)

This is the official Symfony SDK for [Apex Toolbox](https://apextoolbox.com/) - Automatic logging, error tracking, and request monitoring for Symfony applications.

## Getting Started

### Install

```bash
composer require apextoolbox/symfony
```

### Configure

Add your token to `.env`:

```env
APEX_TOOLBOX_TOKEN=your_token_here
```

### Usage

That's it! All logs, exceptions, and HTTP requests are automatically tracked:

```php
// Logs are automatically sent
$logger->info('User created', ['user_id' => 123]);
$logger->error('Payment failed', ['order_id' => 456]);

// Exceptions are automatically captured
throw new Exception('Something went wrong');

// HTTP requests are automatically monitored (api/* routes by default)
```

## What's Tracked Automatically

- **All logs** - Via Monolog integration
- **Exceptions** - With full stack traces and code context
- **HTTP requests** - Request/response data, performance metrics
- **Security** - Sensitive data (passwords, tokens) filtered by default

## Configuration

The bundle is configured in `config/packages/apex_toolbox.yaml` (auto-created):

```yaml
apex_toolbox:
    token: '%env(APEX_TOOLBOX_TOKEN)%'
    enabled: true

    # Customize path filtering
    path_filters:
        include: ['api/*']       # Track API routes
        exclude: ['api/health']  # Skip health checks

    # Security filtering (defaults provided)
    body:
        exclude: ['password', 'token', 'secret']
        mask: ['email', 'phone', 'ssn']
```

### Advanced Options

- **Path filtering** - Control which routes are tracked with wildcards
- **Data masking** - Mask vs exclude sensitive fields (PII, credentials)
- **Console tracking** - Monitor Symfony console commands
- **Queue tracking** - Track Messenger queue jobs

Full configuration options available in `config/packages/apex_toolbox.yaml` after installation.

## Requirements

- PHP 7.4 or higher
- Symfony 5.4, 6.x, or 7.x
- Monolog (installed automatically)

## Resources

- [Documentation](https://apextoolbox.com/docs)
- [Issues](https://github.com/apextoolbox/symfony/issues)

## License

Licensed under the [MIT License](https://github.com/apextoolbox/symfony/blob/main/LICENSE).
