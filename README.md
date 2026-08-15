# SendByte Laravel

Laravel integration for the [SendByte Africa](https://sendbyte.africa) transactional email API. Gives you three things:

1. A **Mail driver** — keep using `Mail::to(...)->send(...)` and Mailables, backed by SendByte.
2. An **API client / Facade** — call SendByte directly for anything the Mail layer doesn't cover.
3. **Webhook handling** — a route, signature verification, and Laravel events for SendByte's delivery lifecycle.

> This package talks to SendByte's REST API directly over Laravel's HTTP client — it doesn't depend on the official `sendbyte/sendbyte-php` SDK, so there's nothing extra to configure beyond your API key.

## Installation

```bash
composer require sendbyte/sendbyte-laravel
```

The service provider and `Sendbyte` facade are auto-discovered. Publish the config if you want to tweak it:

```bash
php artisan vendor:publish --tag=sendbyte-config
```

Add your key (from [app.sendbyte.africa](https://app.sendbyte.africa)) to `.env`:

```env
SENDBYTE_API_KEY=sk_test_xxxxxxxxxxxxxxxx
```

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

Then send like normal:

```php
Mail::to('anyone@example.ng')->send(new OrderReceipt($order));
```

Attachments, CC/BCC, reply-to, and HTML/text bodies are all translated to SendByte's payload shape automatically.

## Using the API client directly

For anything outside a Mailable — one-off sends, checking delivery status, idempotent retries:

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

Failures throw `Sendbyte\Laravel\Exceptions\SendbyteException`, which exposes `errorCode()`, `errorPayload()`, and `docsUrl()` from SendByte's error envelope.

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

### Double-check the webhook header names

I built signature verification against HMAC-SHA256 over the raw request body, which is how SendByte's docs describe their webhooks (signed, replayable, 9 lifecycle events) — but I couldn't confirm the *exact* header names SendByte uses for the signature and timestamp from their public docs. Before going live:

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

## Testing

```bash
composer install
vendor/bin/phpunit
```

The test suite uses Orchestra Testbench and fakes HTTP calls, so no live API key is needed.

## License

MIT.
