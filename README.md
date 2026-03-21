# Apex Toolbox for Symfony

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.0-blue)](https://php.net)
[![Symfony](https://img.shields.io/badge/symfony-5.4%20%7C%206.x%20%7C%207.x-green)](https://symfony.com)

Automatic error tracking, logging, and performance monitoring for Symfony applications. Part of [ApexToolbox](https://apextoolbox.com/).

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
APEXTOOLBOX_ENABLED=true
APEXTOOLBOX_TOKEN=your_token_here
```

Create `config/packages/apextoolbox.yaml` with the full configuration (all filtering options show their **default values** — you only need to override the sections you want to customize):

```yaml
apextoolbox:
    enabled: '%env(bool:APEXTOOLBOX_ENABLED)%'
    token: '%env(resolve:APEXTOOLBOX_TOKEN)%'

    # Paths to include/exclude from logging (supports wildcards)
    path_filters:
        include:
            - '*'
        exclude:
            - '_profiler/*'
            - '_wdt/*'
            - 'api/health'
            - 'api/ping'

    # Headers filtering
    # 'exclude' removes headers entirely, 'mask' replaces values with '*******'
    headers:
        exclude:
            - authorization
            - x-api-key
            - cookie
            - x-auth-token
            - x-access-token
            - x-refresh-token
            - bearer
            - x-secret
            - x-private-key
            - authentication
        mask:
            - ssn
            - social_security
            - phone
            - email
            - address
            - postal_code
            - zip_code

    # Request body filtering
    # 'exclude' removes fields entirely, 'mask' replaces values with '*******'
    body:
        exclude:
            - password
            - password_confirmation
            - token
            - access_token
            - refresh_token
            - api_key
            - secret
            - private_key
            - auth
            - authorization
            - social_security
            - credit_card
            - card_number
            - cvv
            - pin
            - otp
        mask:
            - ssn
            - social_security
            - phone
            - email
            - address
            - postal_code
            - zip_code

    # Response body filtering
    # 'exclude' removes fields entirely, 'mask' replaces values with '*******'
    response:
        exclude:
            - password
            - password_confirmation
            - token
            - access_token
            - refresh_token
            - api_key
            - secret
            - private_key
            - auth
            - authorization
            - social_security
            - credit_card
            - card_number
            - cvv
            - pin
            - otp
        mask:
            - ssn
            - social_security
            - phone
            - email
            - address
            - postal_code
            - zip_code
```

Done! The SDK automatically captures exceptions, logs, and database queries.

## Configuration

### Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `APEXTOOLBOX_TOKEN` | Your project token | Required |
| `APEXTOOLBOX_ENABLED` | Enable/disable tracking | `true` |

## Requirements

- PHP 8.0+
- Symfony 5.4, 6.x, or 7.x

## License

MIT
