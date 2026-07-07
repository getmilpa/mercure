<?php

/**
 * This file is part of Milpa Mercure — the Mercure hub publisher of the Milpa PHP framework.
 *
 * (c) TeamX Agency — https://teamx.agency <hola@teamx.agency>
 *
 * @license Apache-2.0
 *
 * @link    https://github.com/getmilpa/mercure
 */

declare(strict_types=1);

namespace Milpa\Mercure;

use Milpa\Mercure\Tests\Support\CurlStub;

/**
 * Namespaced cURL shims, loaded only for tests via composer.json's
 * autoload-dev "files" entry.
 *
 * PHP resolves an unqualified function call against the *calling*
 * namespace before falling back to the global namespace. MercureService
 * lives in Milpa\Mercure and calls curl_init()/curl_setopt_array()/
 * curl_exec()/curl_getinfo()/curl_error()/curl_close() unqualified, so
 * defining same-named functions here intercepts every call it makes
 * without touching production code or requiring a mocking extension
 * (uopz/runkit are not available in this environment).
 */
function curl_init(?string $url = null): string|false
{
    if (CurlStub::$initShouldFail) {
        return false;
    }

    CurlStub::$lastUrl = $url;

    return 'stub-curl-handle';
}

/**
 * @param array<int, mixed> $options
 */
function curl_setopt_array($handle, array $options): bool
{
    CurlStub::$lastOptions = $options;

    return true;
}

function curl_exec($handle): string|false
{
    return CurlStub::$execReturn;
}

function curl_getinfo($handle, ?int $option = null): mixed
{
    return CurlStub::$httpCode;
}

function curl_error($handle): string
{
    return CurlStub::$curlError;
}

function curl_close($handle): void
{
}
