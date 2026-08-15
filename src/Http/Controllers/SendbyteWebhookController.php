<?php

namespace Sendbyte\Laravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Sendbyte\Laravel\Events;

class SendbyteWebhookController extends Controller
{
    /**
     * Map of SendByte event `type` -> the specific event class to dispatch.
     * Adjust these keys if SendByte's actual type strings differ from this
     * guess once you can see real payloads in your dashboard's webhook log.
     *
     * @var array<string, class-string<Events\EmailLifecycleEvent>>
     */
    protected array $eventMap = [
        'email.queued' => Events\EmailQueued::class,
        'email.sent' => Events\EmailSent::class,
        'email.delivered' => Events\EmailDelivered::class,
        'email.delivery_delayed' => Events\EmailDeliveryDelayed::class,
        'email.bounced' => Events\EmailBounced::class,
        'email.complained' => Events\EmailComplained::class,
        'email.opened' => Events\EmailOpened::class,
        'email.clicked' => Events\EmailClicked::class,
        'email.failed' => Events\EmailFailed::class,
    ];

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->json()->all();
        $type = $payload['type'] ?? null;
        $data = $payload['data'] ?? [];

        event(new Events\SendbyteWebhookReceived($type, $data, $payload));

        if ($type !== null && isset($this->eventMap[$type])) {
            $eventClass = $this->eventMap[$type];
            event(new $eventClass($data, $payload));
        }

        return response()->json(['received' => true]);
    }
}
