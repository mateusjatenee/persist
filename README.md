# Laravel Persist 

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mateusjatenee/laravel-persist.svg?style=flat-square)](https://packagist.org/packages/mateusjatenee/laravel-persist)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/mateusjatenee/laravel-persist/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/mateusjatenee/laravel-persist/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/mateusjatenee/laravel-persist/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/mateusjatenee/laravel-persist/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/mateusjatenee/laravel-persist.svg?style=flat-square)](https://packagist.org/packages/mateusjatenee/laravel-persist)

## Introduction
The package offers an extension on top of Eloquent, enabling developers to handle the persistence of entire object graphs as a single unit of work. This package simplifies managing complex data models with multiple interrelated entities, ensuring a more efficient and reliable data handling process.

It works similarly to the native `push` method, but `push` **does not persist** any records for the first time. Therefore, you cannot build an object with its relationships and use `push` to save everything.  

Persist works by hooking on two specific pieces of the lifecycle:
1. When you assign a property (e.g `$post->owner = $user`), the package checks whether that property is a relation, and if so, calls `setRelation` to properly set it.
2. When you call the `persist` method, it works in a similar fashion to `push`, but it adds hooks to persist the related objects _before or after_ the base object. There are subtle differences on persistence order for different relationship types.

On top of that, Persist also runs the entire operation inside a database transaction. This means that if any part of the object graph fails to persist, the entire operation will be rolled back, maintaining database integrity.

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
        $order->lines->push($command->cartItems->toOrderLines()); // Pushes an object to the "lines" relationship, which is a HasMany relation.
        
        $charge = $command->gateway->pay($command->pendingPayment);
        
        $order->payment = Payment::fromCharge($charge); // Sets the payment relationship, a "BelongsTo" relation.
        $order->payment->customer = $command->customer; // Sets the customer relationship "2-levels deep".

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
