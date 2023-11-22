# Laravel Persist 

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mateusjatenee/laravel-persist.svg?style=flat-square)](https://packagist.org/packages/mateusjatenee/laravel-persist)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/mateusjatenee/laravel-persist/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/mateusjatenee/laravel-persist/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/mateusjatenee/laravel-persist/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/mateusjatenee/laravel-persist/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/mateusjatenee/laravel-persist.svg?style=flat-square)](https://packagist.org/packages/mateusjatenee/laravel-persist)

## Installation

You can install the package via composer:

```bash
composer require mateusjatenee/laravel-persist
```

## Usage
Simply add the `Persist` trait to your models. For example:   

```php
namespace App\Models;

class Order extends Model
{
    use Persist;
}
```

Now you'll be able to persist the entire object graph using the `persist` method. For example:

```php
class ProcessCheckoutHandler
{
    public function __construct(
        private DatabaseManager $database,
    ) {
    }

    public function handle(ProcessCheckout $command)
    {
        $order = Order::startForCustomer($command->customer->id);
        $order->lines->push($command->cartItems->toOrderLines());
        
        $charge = $command->gateway->pay($command->pendingPayment);
        
        $order->payment = Payment::fromCharge($charge);
        $order->payment->customer = $command->customer;

        $order->persist();
        
        return $order;
    }
}
```

In the example above, 4 entities will be persisted to the database: `Order`, `OrderLine`, `Payment`, and `Customer`.  
`persist` runs, by default, within a transaction, so that if any queries fail, the entire transaction is rolled back.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Credits

- [Mateus Guimarães](https://github.com/mateusjatenee)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
