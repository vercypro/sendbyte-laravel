# SendByte Laravel by Vercy

[![Latest Version](https://img.shields.io/packagist/v/vercy/sendbyte-laravel.svg)](https://packagist.org/packages/vercy/sendbyte-laravel)
[![License](https://img.shields.io/packagist/l/vercy/sendbyte-laravel.svg)](https://packagist.org/packages/vercy/sendbyte-laravel)
[![Total Downloads](https://img.shields.io/packagist/dt/vercy/sendbyte-laravel.svg)](https://packagist.org/packages/vercy/sendbyte-laravel)

Laravel integration for the [SendByte Africa](https://sendbyte.africa) transactional email API. Gives you three things:

1. A **Mail driver** — keep using `Mail::to(...)->send(...)` and Mailables, backed by SendByte.
2. An **API client / Facade** — call SendByte directly for anything the Mail layer doesn't cover.
3. **Webhook handling** — a route, signature verification, and Laravel events for SendByte's delivery lifecycle.

> This package talks to SendByte's REST API directly over Laravel's HTTP client — it doesn't depend on the official `sendbyte/sendbyte-php` SDK, so there's nothing extra to configure beyond your API key.

## Requirements

- PHP `^8.1`
- Laravel `10.x`, `11.x`, `12.x`, or `13.x`

## Contents

- [Installation](#installation)
- [Sending mail via the Mail driver](#sending-mail-via-the-mail-driver)
- [Sending without a Mailable](#sending-without-a-mailable)
- [Using the API client directly](#using-the-api-client-directly)
- [Webhooks](#webhooks)
- [Verifying your setup](#verifying-your-setup)
- [Common errors](#common-errors)
- [Testing](#testing)
- [License](#license)

## Installation

```bash
composer require vercy/sendbyte-laravel
```

The service provider and `Sendbyte` facade are auto-discovered. Publish the config if you want to tweak it:

```bash
php artisan vendor:publish --tag=sendbyte-config
```

Add your key (from [app.sendbyte.africa](https://app.sendbyte.africa)) to `.env`:

```env
SENDBYTE_API_KEY=sk_test_xxxxxxxxxxxxxxxx
```

Use an `sk_test_...` key while you're getting set up — it doesn't require a verified sending domain. Switch to an `sk_live_...` key once your domain is verified and you're sending real traffic.

## Sending mail via the Mail driver

Add a mailer in `config/mail.php`:

```php
'mailers' => [
    'sendbyte' => [
        'transport' => 'sendbyte',
    ],
],
```

Point default mail at it (or use `->mailer('sendbyte')` per-send):

```env
MAIL_MAILER=sendbyte
MAIL_FROM_ADDRESS="you@yourapp.ng"
MAIL_FROM_NAME="Your App"
```

Then send like normal, using a Mailable:

```php
Mail::to('anyone@example.ng')->send(new OrderReceipt($order));
```

Attachments, CC/BCC, reply-to, and HTML/text bodies are all translated to SendByte's payload shape automatically.

## Sending without a Mailable

For quick or one-off sends, `Mail::raw()` and `Mail::html()` work too:

```php
// Plain text
Mail::mailer('sendbyte')->raw('Your order has shipped.', function ($message) {
    $message->to('anyone@example.ng')
             ->from('you@yourapp.ng')
             ->subject('Shipping update');
});

// HTML
Mail::mailer('sendbyte')->html('<h4>Order shipped</h4><p>Track it here.</p>', function ($message) {
    $message->to('anyone@example.ng')
             ->from('you@yourapp.ng')
             ->subject('Shipping update');
});
```

If `MAIL_MAILER=sendbyte` is already set in `.env`, you can drop `mailer('sendbyte')` and just call `Mail::raw(...)` / `Mail::html(...)` directly.

## Using the API client directly

For anything outside the Mail layer — checking delivery status, idempotent retries, listing sends:

```php
use Sendbyte\Laravel\Facades\Sendbyte;

$email = Sendbyte::sendEmail([
    'from'    => 'You <you@yourapp.ng>',
    'to'      => 'anyone@example.ng',
    'subject' => 'Your OTP',
    'html'    => '<p>Your code is 483920.</p>',
], idempotencyKey: "otp-{$user->id}-".now()->timestamp);

$status = Sendbyte::getEmail($email['id']);

Sendbyte::listEmails(['status' => 'bounced', 'limit' => 20]);
```

Failures throw `Sendbyte\Laravel\Exceptions\SendbyteException`:

```php
use Sendbyte\Laravel\Exceptions\SendbyteException;

try {
    Sendbyte::sendEmail([
        'from'    => 'You <you@yourapp.ng>',
        'to'      => 'anyone@example.ng',
        'subject' => 'Your OTP',
        'html'    => '<p>Your code is 483920.</p>',
    ]);
} catch (SendbyteException $e) {
    report($e);

    // $e->getMessage()   — human-readable message, includes SendByte's error code
    // $e->errorCode()    — e.g. "validation_error"
    // $e->errorPayload() — the full decoded error response
    // $e->docsUrl()      — link to SendByte's docs for this error, if provided
}
```

You can also resolve `Sendbyte\Laravel\Sendbyte` from the container instead of using the facade, e.g. for constructor injection.

## Webhooks

The package registers `POST /webhooks/sendbyte` automatically, guarded by HMAC signature verification. Set your signing secret (shown when you create the webhook endpoint in the SendByte dashboard):

```env
SENDBYTE_WEBHOOK_SECRET=whsec_xxxxxxxxxxxxxxxx
```

Then listen for whichever lifecycle stages you care about in `EventServiceProvider`:

```php
use Sendbyte\Laravel\Events\EmailDelivered;
use Sendbyte\Laravel\Events\EmailBounced;
use Sendbyte\Laravel\Events\EmailComplained;
use Sendbyte\Laravel\Events\SendbyteWebhookReceived;

protected $listen = [
    EmailDelivered::class => [MarkReceiptDelivered::class],
    EmailBounced::class => [SuppressBouncedAddress::class],
    EmailComplained::class => [SuppressBouncedAddress::class],

    // Or catch everything in one place:
    SendbyteWebhookReceived::class => [LogSendbyteEvent::class],
};
```

Every lifecycle event (`EmailQueued`, `EmailSent`, `EmailDelivered`, `EmailDeliveryDelayed`, `EmailBounced`, `EmailComplained`, `EmailOpened`, `EmailClicked`, `EmailFailed`) exposes `$event->data` (the event's `data` object), `$event->payload` (the full raw payload), `$event->emailId()`, and `$event->to()`.

### Webhook header names

Signature verification uses HMAC-SHA256 over the raw request body, matching how SendByte describes its webhooks (signed, replayable, 9 lifecycle events). Header names are configurable in `config/sendbyte.php` rather than hardcoded, since providers vary here — confirm the exact names against a real test event before relying on this in production:

1. Create a webhook endpoint in the SendByte dashboard and trigger a test event.
2. Check the actual header name(s) on the incoming request.
3. Update `signature_header` / `timestamp_header` in `config/sendbyte.php` (or the matching `.env` vars) to match — no code changes needed.

If SendByte signs a `timestamp.body` string rather than just the raw body, that's already handled as long as `timestamp_header` is set correctly. If they don't send a timestamp at all, set `SENDBYTE_WEBHOOK_TIMESTAMP_HEADER=null` to skip that check.

### Customizing the route

```php
// config/sendbyte.php
'webhook' => [
    'route' => [
        'enabled' => true,
        'path' => 'webhooks/sendbyte',
        'middleware' => ['api'],
    ],
],
```

Set `enabled` to `false` if you'd rather register the route and controller yourself.

## Verifying your setup

Quick smoke test after install — run `php artisan tinker`:

```php
Sendbyte\Laravel\Facades\Sendbyte::sendEmail([
    'from'    => 'you@yourdomain.com',
    'to'      => 'you@yourdomain.com',
    'subject' => 'Test from tinker',
    'html'    => '<p>It works</p>',
]);
```

A successful call returns an array containing an `id`. Anything else — an exception, a validation error — means something in your key, domain verification, or config needs attention before you wire it into real code.

## Common errors

**"Domain not verified" / send rejected** — the address in `from` must be on a domain you've verified in your SendByte dashboard. `sk_test_...` keys are more permissive; `sk_live_...` keys enforce this strictly.

**Composer can't resolve a version** — if you're installing straight off a `dev-main` branch with no tagged release, either require it explicitly (`composer require vercy/sendbyte-laravel:dev-main`) or, better, use a tagged version (`^1.0`) once one's published — dev branches can introduce breaking changes without a version bump.

**`SendbyteException` with no useful message** — call `$e->errorPayload()` to see SendByte's full raw error response; the top-level message is a summary and may omit field-level detail.

## Testing

```bash
composer install
vendor/bin/phpunit
```

The test suite uses Orchestra Testbench and fakes HTTP calls, so no live API key is needed.

## License

MIT.