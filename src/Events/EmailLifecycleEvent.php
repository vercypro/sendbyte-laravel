<?php

namespace Sendbyte\Laravel\Events;

use Illuminate\Foundation\Events\Dispatchable;

abstract class EmailLifecycleEvent
{
    use Dispatchable;

    public function __construct(
        public readonly array $data,
        public readonly array $payload,
    ) {
    }

    public function emailId(): ?string
    {
        return $this->data['email_id'] ?? null;
    }

    public function to(): ?string
    {
        return $this->data['to'] ?? null;
    }
}
