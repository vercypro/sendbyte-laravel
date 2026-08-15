<?php

namespace Sendbyte\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the HMAC-SHA256 signature SendByte attaches to every webhook
 * request, using the raw request body (verification must happen before any
 * JSON re-encoding, or the computed hash will not match).
 *
 * Header names are configurable in config/sendbyte.php — double-check them
 * against the signing secret / endpoint settings shown in your SendByte
 * dashboard, since header naming can vary between providers.
 */
class VerifySendbyteWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('sendbyte.webhook.signing_secret');

        if (blank($secret)) {
            abort(500, 'SendByte webhook signing secret is not configured (SENDBYTE_WEBHOOK_SECRET).');
        }

        $signatureHeader = config('sendbyte.webhook.signature_header', 'X-Sendbyte-Signature');
        $signature = $request->header($signatureHeader);

        if (blank($signature)) {
            abort(401, "Missing SendByte webhook signature header ({$signatureHeader}).");
        }

        $timestampHeader = config('sendbyte.webhook.timestamp_header');
        $timestamp = $timestampHeader ? $request->header($timestampHeader) : null;

        if ($timestampHeader && $timestamp !== null) {
            $tolerance = (int) config('sendbyte.webhook.tolerance', 300);

            if (abs(time() - (int) $timestamp) > $tolerance) {
                abort(401, 'SendByte webhook timestamp is outside the allowed tolerance.');
            }
        }

        $signedContent = $timestamp !== null
            ? "{$timestamp}.{$request->getContent()}"
            : $request->getContent();

        $expected = hash_hmac('sha256', $signedContent, $secret);

        // Signature may arrive as a raw hex digest or prefixed, e.g. "sha256=...".
        $provided = str_starts_with($signature, 'sha256=')
            ? substr($signature, 7)
            : $signature;

        if (! hash_equals($expected, $provided)) {
            abort(401, 'Invalid SendByte webhook signature.');
        }

        return $next($request);
    }
}
