<?php

namespace Sendbyte\Laravel;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Sendbyte\Laravel\Exceptions\SendbyteException;

class Sendbyte
{
    public function __construct(
        protected string $apiKey,
        protected string $baseUrl = 'https://api.sendbyte.africa/v1',
        protected int $timeout = 10,
    ) {
    }

    /**
     * Send an email.
     *
     * @param  array  $payload  e.g. ['from' => ..., 'to' => ..., 'subject' => ..., 'html' => ...]
     * @param  string|null  $idempotencyKey  Retry safely: the same key sends exactly once.
     */
    public function sendEmail(array $payload, ?string $idempotencyKey = null): array
    {
        return $this->handle(
            $this->client($idempotencyKey)->post('/emails', $payload)
        );
    }

    /**
     * Retrieve a single email by id, including its event timeline.
     */
    public function getEmail(string $emailId): array
    {
        return $this->handle($this->client()->get("/emails/{$emailId}"));
    }

    /**
     * List sent emails, optionally filtered (e.g. ['limit' => 20, 'status' => 'delivered']).
     */
    public function listEmails(array $query = []): array
    {
        return $this->handle($this->client()->get('/emails', $query));
    }

    /**
     * Issue a raw authenticated request against any SendByte endpoint not
     * yet wrapped by a dedicated method above.
     */
    public function request(string $method, string $uri, array $options = []): array
    {
        return $this->handle(
            $this->client()->send($method, $uri, $options)
        );
    }

    protected function client(?string $idempotencyKey = null): PendingRequest
    {
        $client = Http::baseUrl($this->baseUrl)
            ->withToken($this->apiKey)
            ->acceptJson()
            ->timeout($this->timeout);

        if ($idempotencyKey !== null) {
            $client = $client->withHeaders(['Idempotency-Key' => $idempotencyKey]);
        }

        return $client;
    }

    protected function handle(Response $response): array
    {
        if ($response->failed()) {
            throw SendbyteException::fromResponse($response);
        }

        return $response->json() ?? [];
    }
}
