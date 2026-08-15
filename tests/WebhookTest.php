<?php

namespace Sendbyte\Laravel\Tests;

use Illuminate\Support\Facades\Event;
use Sendbyte\Laravel\Events\EmailDelivered;
use Sendbyte\Laravel\Events\SendbyteWebhookReceived;

class WebhookTest extends TestCase
{
    protected function signedPost(array $payload, string $secret = 'whsec_dummy')
    {
        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $body, $secret);

        return $this
            ->withHeader('X-Sendbyte-Signature', $signature)
            ->call('POST', '/webhooks/sendbyte', [], [], [], [
                'CONTENT_TYPE' => 'application/json',
            ], $body);
    }

    public function test_it_rejects_requests_without_a_valid_signature(): void
    {
        $response = $this
            ->withHeader('X-Sendbyte-Signature', 'not-the-real-signature')
            ->postJson('/webhooks/sendbyte', ['type' => 'email.delivered', 'data' => []]);

        $response->assertStatus(401);
    }

    public function test_it_dispatches_events_for_a_verified_webhook(): void
    {
        Event::fake();

        $payload = [
            'type' => 'email.delivered',
            'created_at' => '2026-06-12T20:14:07Z',
            'data' => [
                'email_id' => 'em_8f2a91c4',
                'to' => 'anyone@example.ng',
                'subject' => 'Receipt',
            ],
        ];

        $response = $this->signedPost($payload);

        $response->assertOk();
        $response->assertJson(['received' => true]);

        Event::assertDispatched(SendbyteWebhookReceived::class, fn ($event) => $event->type === 'email.delivered');
        Event::assertDispatched(EmailDelivered::class, fn ($event) => $event->emailId() === 'em_8f2a91c4');
    }
}
