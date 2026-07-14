<?php

/**
 * This file is part of Milpa Mercure — the Mercure hub publisher of the Milpa PHP framework.
 *
 * (c) Rodrigo Vicente - TeamX Agency — https://teamx.agency <hola@teamx.agency>
 *
 * @license Apache-2.0
 *
 * @link    https://github.com/getmilpa/mercure
 */

declare(strict_types=1);

namespace Milpa\Mercure\Tests\Support;

/**
 * Captures the cURL invocation performed by MercureService::publish() and
 * lets tests script the mocked HTTP response.
 *
 * MercureService talks to cURL directly with no HTTP client abstraction to
 * seam against, so interception happens one level down: see
 * tests/Support/curl_functions.php, which shadows the curl_* functions
 * inside the Milpa\Mercure namespace.
 */
final class CurlStub
{
    public static ?string $lastUrl = null;

    /** @var array<int, mixed>|null */
    public static ?array $lastOptions = null;

    public static bool $initShouldFail = false;

    public static string|false $execReturn = '{}';

    public static int $httpCode = 200;

    public static string $curlError = '';

    public static function reset(): void
    {
        self::$lastUrl = null;
        self::$lastOptions = null;
        self::$initShouldFail = false;
        self::$execReturn = '{}';
        self::$httpCode = 200;
        self::$curlError = '';
    }
}
