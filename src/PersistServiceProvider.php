<?php

namespace Mateusjatenee\Persist;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Mateusjatenee\Persist\Commands\PersistCommand;

class PersistServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-persist')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel-persist_table')
            ->hasCommand(PersistCommand::class);
    }
}
