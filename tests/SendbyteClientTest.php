<?php

namespace Sendbyte\Laravel\Tests;

use Illuminate\Support\Facades\Http;
use Sendbyte\Laravel\Exceptions\SendbyteException;
use Sendbyte\Laravel\Sendbyte;

class SendbyteClientTest extends TestCase
{
    public function test_it_sends_an_email(): void
    {
        Http::fake([
            'api.sendbyte.africa/v1/emails' => Http::response(['id' => 'em_123'], 200),
        ]);

        $result = $this->app->make(Sendbyte::class)->sendEmail([
            'from' => 'You <you@yourapp.ng>',
            'to' => 'anyone@example.ng',
            'subject' => 'Hello',
            'html' => '<p>Hi</p>',
        ], idempotencyKey: 'order-1');

        $this->assertSame('em_123', $result['id']);

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer sk_test_dummy')
                && $request->hasHeader('Idempotency-Key', 'order-1')
                && $request['subject'] === 'Hello';
        });
    }

    public function test_it_throws_a_descriptive_exception_on_failure(): void
    {
        Http::fake([
            'api.sendbyte.africa/v1/emails' => Http::response([
                'error' => [
                    'code' => 'validation_error',
                    'message' => 'The `to` field must be a valid email address.',
                    'docs_url' => 'https://docs.sendbyte.africa/errors/validation_error',
                ],
            ], 422),
        ]);

        $this->expectException(SendbyteException::class);
        $this->expectExceptionMessage('validation_error');

        $this->app->make(Sendbyte::class)->sendEmail([
            'from' => 'You <you@yourapp.ng>',
            'to' => 'not-an-email',
            'subject' => 'Hello',
        ]);
    }
}
