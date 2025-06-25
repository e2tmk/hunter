# Hunter

[![Latest Version on Packagist](https://img.shields.io/packagist/v/e2tmk/hunter.svg?style=flat-square)](https://packagist.org/packages/e2tmk/hunter)
[![Total Downloads](https://img.shields.io/packagist/dt/e2tmk/hunter.svg?style=flat-square)](https://packagist.org/packages/e2tmk/hunter)

Powerful utility for finding and processing Eloquent model records with a fluent, chainable API.

Hunter provides a clean way to search for records based on specific criteria and execute multiple actions on them with comprehensive error handling, logging, and advanced flow control.

## Installation

You can install the package via Composer:

```bash
composer require e2tmk/hunter
```

## Quick Example

```php
use Hunter\Hunter;

// Process all pending orders
$result = hunter(Order::class)
    ->find('status', 'pending')
    ->then(function (Order $order) {
        $order->process();
    })
    ->hunt();

echo $result->summary(); // "Total: 15, Successful: 14, Failed: 1, Skipped: 0"
```

## Features

-   🔍 **Powerful Search**: Find records with flexible criteria
-   🔗 **Fluent API**: Chainable methods for clean code
-   🎯 **Multiple Actions**: Execute several actions per record
-   ⚡ **Flow Control**: Skip, fail, or stop processing gracefully
-   📊 **Comprehensive Reporting**: Detailed statistics and error tracking

## Documentation

📖 **[Complete Documentation](https://hunter.e2tmk.com)**

-   [Getting Started](https://hunter.e2tmk.com)
-   [Usage Examples](https://hunter.e2tmk.com/examples)
-   [API Reference](https://hunter.e2tmk.com/api)

## License

The project is licensed under the [MIT License](LICENSE.md).
