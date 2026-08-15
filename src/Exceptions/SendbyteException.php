<?php

namespace Sendbyte\Laravel\Exceptions;

use Exception;
use Illuminate\Http\Client\Response;

/**
 * Thrown for any non-2xx SendByte API response.
 *
 * SendByte's error envelope looks like:
 * { "error": { "code": "validation_error", "message": "...", "docs_url": "..." } }
 */
class SendbyteException extends Exception
{
    protected array $errorPayload = [];

    protected ?string $errorCode = null;

    protected ?string $docsUrl = null;

    public static function fromResponse(Response $response): self
    {
        $body = $response->json() ?? [];
        $error = $body['error'] ?? [];

        $code = $error['code'] ?? null;
        $docsUrl = $error['docs_url'] ?? null;
        $message = $error['message'] ?? 'The SendByte API request failed.';

        $fullMessage = $code ? "[{$code}] {$message}" : $message;
        if ($docsUrl) {
            $fullMessage .= " ({$docsUrl})";
        }

        $exception = new self($fullMessage, $response->status());
        $exception->errorPayload = $body;
        $exception->errorCode = $code;
        $exception->docsUrl = $docsUrl;

        return $exception;
    }

    public function errorPayload(): array
    {
        return $this->errorPayload;
    }

    public function errorCode(): ?string
    {
        return $this->errorCode;
    }

    public function docsUrl(): ?string
    {
        return $this->docsUrl;
    }
}
