# Official Apex Toolbox SDK for Symfony

[![Tests](https://img.shields.io/badge/tests-28%20passed-brightgreen)](https://github.com/apextoolbox/symfony-logger)
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
```

## Usage

All your existing logs are automatically sent to Apex Toolbox:

```php
$logger->info('User created', ['user_id' => 123]);
$logger->error('Payment failed', ['order_id' => 456]);
```

## License

MIT