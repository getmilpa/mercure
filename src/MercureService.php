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

/**
 * Publishes updates to a Mercure hub and mints the self-signed JWTs the hub
 * expects for both server-side publish and browser-side subscribe.
 *
 * The hub is not called via any HTTP client abstraction — publish() drives
 * cURL directly, so no other Milpa package is required to use this class.
 */
class MercureService
{
    /**
     * @param string $hubUrl        Internal hub URL used for server-side POST /.well-known/mercure
     * @param string $publicUrl     Public hub URL the browser connects to for EventSource/SSE
     * @param string $publisherKey  HMAC secret used to sign publisher JWTs
     * @param string $subscriberKey HMAC secret used to sign subscriber JWTs
     */
    public function __construct(
        private readonly string $hubUrl,
        private readonly string $publicUrl,
        private readonly string $publisherKey,
        private readonly string $subscriberKey
    ) {
    }

    // =========================================================================
    // PUBLISH
    // =========================================================================

    /**
     * Publish data to a Mercure topic.
     *
     * @param string               $topic Topic URI (e.g. "conversations/123/messages")
     * @param array<string, mixed> $data  Payload to send as JSON
     */
    public function publish(string $topic, array $data): void
    {
        $jwt = $this->generatePublisherJwt([$topic]);

        $postData = http_build_query([
            'topic' => $topic,
            'data' => json_encode($data, JSON_THROW_ON_ERROR),
        ]);

        $ch = curl_init($this->hubUrl);
        if ($ch === false) {
            throw new \RuntimeException('Failed to initialize cURL for Mercure publish');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $jwt,
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($result === false || $httpCode >= 400) {
            throw new \RuntimeException(
                "Mercure publish failed (HTTP {$httpCode}): {$error}"
            );
        }
    }

    // =========================================================================
    // SUBSCRIBER JWT (for browser)
    // =========================================================================

    /**
     * Generate a subscriber JWT for the browser EventSource connection.
     *
     * @param string[] $topics Topics the subscriber can listen to
     */
    public function generateSubscriberJwt(array $topics): string
    {
        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'HS256',
            'typ' => 'JWT',
        ], JSON_THROW_ON_ERROR));

        $payload = $this->base64UrlEncode(json_encode([
            'mercure' => [
                'subscribe' => $topics,
            ],
            'exp' => time() + 300, // 5 min TTL
        ], JSON_THROW_ON_ERROR));

        $signature = $this->base64UrlEncode(
            hash_hmac('sha256', "{$header}.{$payload}", $this->subscriberKey, true)
        );

        return "{$header}.{$payload}.{$signature}";
    }

    // =========================================================================
    // PUBLIC URL
    // =========================================================================

    /**
     * The public hub URL browsers should open their EventSource connection against.
     */
    public function getPublicUrl(): string
    {
        return $this->publicUrl;
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Generate a publisher JWT for server-side POST to Hub.
     *
     * @param string[] $topics
     */
    private function generatePublisherJwt(array $topics): string
    {
        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'HS256',
            'typ' => 'JWT',
        ], JSON_THROW_ON_ERROR));

        $payload = $this->base64UrlEncode(json_encode([
            'mercure' => [
                'publish' => $topics,
            ],
            'exp' => time() + 60, // 1 min TTL (short-lived for publish)
        ], JSON_THROW_ON_ERROR));

        $signature = $this->base64UrlEncode(
            hash_hmac('sha256', "{$header}.{$payload}", $this->publisherKey, true)
        );

        return "{$header}.{$payload}.{$signature}";
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
