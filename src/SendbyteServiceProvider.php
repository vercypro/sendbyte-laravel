<?php

namespace Sendbyte\Laravel;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Sendbyte\Laravel\Http\Controllers\SendbyteWebhookController;
use Sendbyte\Laravel\Http\Middleware\VerifySendbyteWebhookSignature;
use Sendbyte\Laravel\Mail\SendbyteTransport;

class SendbyteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sendbyte.php', 'sendbyte');

        $this->app->singleton(Sendbyte::class, function ($app) {
            $config = $app['config']->get('sendbyte');

            return new Sendbyte(
                apiKey: $config['api_key'] ?? '',
                baseUrl: $config['base_url'] ?? 'https://api.sendbyte.africa/v1',
                timeout: (int) ($config['timeout'] ?? 10),
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/sendbyte.php' => config_path('sendbyte.php'),
            ], 'sendbyte-config');
        }

        $this->registerMailTransport();
        $this->registerWebhookRoute();
    }

    protected function registerMailTransport(): void
    {
        // Safe to call in boot(): all service providers have registered by
        // now, and MailManager resolves transports lazily on first use.
        $this->app->make('mail.manager')->extend('sendbyte', function () {
            return new SendbyteTransport($this->app->make(Sendbyte::class));
        });
    }

    protected function registerWebhookRoute(): void
    {
        $config = $this->app['config']->get('sendbyte.webhook.route', []);

        if (! ($config['enabled'] ?? true)) {
            return;
        }

        Route::middleware(array_merge(
            $config['middleware'] ?? ['api'],
            [VerifySendbyteWebhookSignature::class]
        ))
            ->post($config['path'] ?? 'webhooks/sendbyte', [SendbyteWebhookController::class, 'handle'])
            ->name('sendbyte.webhook');
    }
}
