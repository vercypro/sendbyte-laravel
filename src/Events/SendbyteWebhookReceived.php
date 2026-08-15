<?php

namespace Sendbyte\Laravel\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired for every verified webhook SendByte sends, regardless of type.
 * Listen to this if you want a single catch-all handler; listen to the
 * specific Email* events below if you only care about certain stages.
 */
class SendbyteWebhookReceived
{
    use Dispatchable;

    public function __construct(
        public readonly ?string $type,
        public readonly array $data,
        public readonly array $payload,
    ) {
    }
}
