<?php

namespace Sendbyte\Laravel\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Sendbyte\Laravel\SendbyteServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [SendbyteServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('sendbyte.api_key', 'sk_test_dummy');
        $app['config']->set('sendbyte.webhook.signing_secret', 'whsec_dummy');

        // Testbench's minimal HTTP kernel doesn't define an 'api' middleware
        // group, so keep the webhook route unencumbered by it here — the
        // signature-verification middleware is always applied regardless.
        $app['config']->set('sendbyte.webhook.route.middleware', []);
    }
}
