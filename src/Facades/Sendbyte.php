<?php

namespace Sendbyte\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array sendEmail(array $payload, ?string $idempotencyKey = null)
 * @method static array getEmail(string $emailId)
 * @method static array listEmails(array $query = [])
 * @method static array request(string $method, string $uri, array $options = [])
 *
 * @see \Sendbyte\Laravel\Sendbyte
 */
class Sendbyte extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Sendbyte\Laravel\Sendbyte::class;
    }
}
