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

namespace Milpa\Mercure\Tests;

use Milpa\Mercure\MercureService;
use Milpa\Mercure\Tests\Support\CurlStub;
use PHPUnit\Framework\TestCase;

class MercureServiceTest extends TestCase
{
    private const HUB_URL = 'https://hub.internal/.well-known/mercure';
    private const PUBLIC_URL = 'https://hub.example.com/.well-known/mercure';
    private const PUBLISHER_KEY = 'publisher-secret';
    private const SUBSCRIBER_KEY = 'subscriber-secret';

    private MercureService $service;

    protected function setUp(): void
    {
        parent::setUp();
        CurlStub::reset();
        $this->service = new MercureService(
            self::HUB_URL,
            self::PUBLIC_URL,
            self::PUBLISHER_KEY,
            self::SUBSCRIBER_KEY
        );
    }

    // =========================================================================
    // PUBLIC URL
    // =========================================================================

    public function testGetPublicUrlReturnsConstructorValue(): void
    {
        $this->assertSame(self::PUBLIC_URL, $this->service->getPublicUrl());
    }

    // =========================================================================
    // SUBSCRIBER JWT
    // =========================================================================

    public function testGenerateSubscriberJwtHasThreeSegments(): void
    {
        $jwt = $this->service->generateSubscriberJwt(['conversations/1']);

        $this->assertCount(3, explode('.', $jwt));
    }

    public function testGenerateSubscriberJwtHeaderIsHs256(): void
    {
        $jwt = $this->service->generateSubscriberJwt(['conversations/1']);
        [$header] = explode('.', $jwt);

        $decoded = $this->decodeSegment($header);

        $this->assertSame(['alg' => 'HS256', 'typ' => 'JWT'], $decoded);
    }

    public function testGenerateSubscriberJwtPayloadCarriesSubscribeTopics(): void
    {
        $topics = ['conversations/1/messages', 'conversations/2/messages'];
        $jwt = $this->service->generateSubscriberJwt($topics);
        [, $payload] = explode('.', $jwt);

        $decoded = $this->decodeSegment($payload);

        $this->assertSame($topics, $decoded['mercure']['subscribe']);
    }

    public function testGenerateSubscriberJwtPayloadExpiresInAboutFiveMinutes(): void
    {
        $before = time();
        $jwt = $this->service->generateSubscriberJwt(['t']);
        $after = time();
        [, $payload] = explode('.', $jwt);

        $decoded = $this->decodeSegment($payload);

        $this->assertGreaterThanOrEqual($before + 300, $decoded['exp']);
        $this->assertLessThanOrEqual($after + 300, $decoded['exp']);
    }

    public function testGenerateSubscriberJwtSignatureIsHmacSha256WithSubscriberKey(): void
    {
        $jwt = $this->service->generateSubscriberJwt(['t']);
        [$header, $payload, $signature] = explode('.', $jwt);

        $expectedSignature = $this->encodeSegment(
            hash_hmac('sha256', "{$header}.{$payload}", self::SUBSCRIBER_KEY, true)
        );

        $this->assertSame($expectedSignature, $signature);
    }

    public function testGenerateSubscriberJwtSignatureChangesWithKey(): void
    {
        $otherService = new MercureService(self::HUB_URL, self::PUBLIC_URL, self::PUBLISHER_KEY, 'a-different-key');

        // Freeze both calls within the same second so only the key differs.
        $topics = ['t'];
        $jwt1 = $this->service->generateSubscriberJwt($topics);
        $jwt2 = $otherService->generateSubscriberJwt($topics);

        $this->assertNotSame($jwt1, $jwt2);
    }

    // =========================================================================
    // PUBLISH — request construction
    // =========================================================================

    public function testPublishPostsToTheHubUrl(): void
    {
        $this->service->publish('conversations/1', ['event' => 'message.created']);

        $this->assertSame(self::HUB_URL, CurlStub::$lastUrl);
    }

    public function testPublishSendsTopicAndJsonEncodedDataAsFormBody(): void
    {
        $data = ['event' => 'message.created', 'nested' => ['id' => 1, 'ok' => true]];

        $this->service->publish('conversations/1/messages', $data);

        $options = CurlStub::$lastOptions;
        $this->assertIsArray($options);
        parse_str($options[CURLOPT_POSTFIELDS], $parsed);

        $this->assertSame('conversations/1/messages', $parsed['topic']);
        $this->assertSame($data, json_decode($parsed['data'], true, 512, JSON_THROW_ON_ERROR));
    }

    public function testPublishSetsBearerAuthorizationHeaderWithAPublisherJwt(): void
    {
        $this->service->publish('conversations/1', ['a' => 1]);

        $options = CurlStub::$lastOptions;
        $headers = $options[CURLOPT_HTTPHEADER];
        $authHeader = current(array_filter($headers, static fn (string $h) => str_starts_with($h, 'Authorization: Bearer ')));

        $this->assertNotFalse($authHeader);

        $jwt = substr($authHeader, strlen('Authorization: Bearer '));
        [$header, $payload, $signature] = explode('.', $jwt);

        $this->assertSame(['alg' => 'HS256', 'typ' => 'JWT'], $this->decodeSegment($header));
        $this->assertSame(['conversations/1'], $this->decodeSegment($payload)['mercure']['publish']);

        $expectedSignature = $this->encodeSegment(
            hash_hmac('sha256', "{$header}.{$payload}", self::PUBLISHER_KEY, true)
        );
        $this->assertSame($expectedSignature, $signature);
    }

    public function testPublishSetsFormContentTypeHeader(): void
    {
        $this->service->publish('conversations/1', ['a' => 1]);

        $headers = CurlStub::$lastOptions[CURLOPT_HTTPHEADER];

        $this->assertContains('Content-Type: application/x-www-form-urlencoded', $headers);
    }

    public function testPublishConfiguresPostReturnTransferAndTimeout(): void
    {
        $this->service->publish('conversations/1', ['a' => 1]);

        $options = CurlStub::$lastOptions;

        $this->assertTrue($options[CURLOPT_POST]);
        $this->assertTrue($options[CURLOPT_RETURNTRANSFER]);
        $this->assertSame(5, $options[CURLOPT_TIMEOUT]);
    }

    public function testPublisherJwtExpiresInAboutOneMinute(): void
    {
        $before = time();
        $this->service->publish('conversations/1', ['a' => 1]);
        $after = time();

        $headers = CurlStub::$lastOptions[CURLOPT_HTTPHEADER];
        $authHeader = current(array_filter($headers, static fn (string $h) => str_starts_with($h, 'Authorization: Bearer ')));
        [, $payload] = explode('.', substr($authHeader, strlen('Authorization: Bearer ')));
        $decoded = $this->decodeSegment($payload);

        $this->assertGreaterThanOrEqual($before + 60, $decoded['exp']);
        $this->assertLessThanOrEqual($after + 60, $decoded['exp']);
    }

    // =========================================================================
    // PUBLISH — failure handling
    // =========================================================================

    public function testPublishThrowsWhenCurlInitFails(): void
    {
        CurlStub::$initShouldFail = true;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to initialize cURL for Mercure publish');

        $this->service->publish('conversations/1', ['a' => 1]);
    }

    public function testPublishThrowsWhenCurlExecFails(): void
    {
        CurlStub::$execReturn = false;
        CurlStub::$curlError = 'Connection refused';
        CurlStub::$httpCode = 0;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Connection refused');

        $this->service->publish('conversations/1', ['a' => 1]);
    }

    public function testPublishThrowsOnHttpErrorStatus(): void
    {
        CurlStub::$httpCode = 401;
        CurlStub::$curlError = '';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('HTTP 401');

        $this->service->publish('conversations/1', ['a' => 1]);
    }

    public function testPublishDoesNotThrowOnSuccessStatus(): void
    {
        CurlStub::$httpCode = 200;
        CurlStub::$execReturn = '';

        $this->service->publish('conversations/1', ['a' => 1]);

        $this->assertTrue(true); // Reaching here means no exception was thrown.
    }

    // =========================================================================
    // TEST HELPERS
    // =========================================================================

    /**
     * @return array<mixed>
     */
    private function decodeSegment(string $segment): array
    {
        $padded = str_pad(strtr($segment, '-_', '+/'), (int) (4 * ceil(strlen($segment) / 4)), '=', STR_PAD_RIGHT);

        return json_decode(base64_decode($padded), true, 512, JSON_THROW_ON_ERROR);
    }

    private function encodeSegment(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
