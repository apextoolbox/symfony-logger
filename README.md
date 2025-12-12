# Apex Toolbox for Symfony

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.0-blue)](https://php.net)
[![Symfony](https://img.shields.io/badge/symfony-5.4%20%7C%206.x%20%7C%207.x-green)](https://symfony.com)

Automatic error tracking, logging, and performance monitoring for Symfony applications.

## Installation

```bash
composer require apextoolbox/symfony-logger
```

Register the bundle in `config/bundles.php` (if not auto-registered):

```php
return [
    // ...
    ApexToolbox\SymfonyLogger\ApexToolboxBundle::class => ['all' => true],
];
```

Add to `.env`:

```env
APEX_TOOLBOX_ENABLED=true
APEX_TOOLBOX_TOKEN=your_token_here
```

Create `config/packages/apex_toolbox.yaml`:

```yaml
apex_toolbox:
    enabled: '%env(bool:APEX_TOOLBOX_ENABLED)%'
    token: '%env(resolve:APEX_TOOLBOX_TOKEN)%'
```

Done! The SDK automatically captures exceptions, logs, and database queries.

## Configuration

### Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `APEX_TOOLBOX_TOKEN` | Your project token | Required |
| `APEX_TOOLBOX_ENABLED` | Enable/disable tracking | `true` |

### Path Filtering

```yaml
apex_toolbox:
    path_filters:
        include: ['*']
        exclude: ['_profiler/*', '_wdt/*']
```

### Sensitive Data

Sensitive fields like `password`, `token`, `authorization` are automatically excluded from logs.

## Requirements

- PHP 8.0+
- Symfony 5.4, 6.x, or 7.x

## License

MIT
