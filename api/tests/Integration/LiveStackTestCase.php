<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Integration tests against the live Docker stack (http://localhost:8080).
 *
 * These exercise the real MySQL database and the real Slim app, so they only
 * run when the stack is up (docker compose up -d). In CI — where no stack
 * exists — they self-skip so the unit suite stays green.
 */
abstract class LiveStackTestCase extends PHPUnitTestCase
{
    private const BASE = 'http://localhost:8080';

    private const DEMO_EMAIL = 'gpapadop@example.com';
    private const DEMO_PASSWORD = 'alumni2026';

    protected static ?string $token = null;

    public static function setUpBeforeClass(): void
    {
        $ch = curl_init(self::BASE . '/');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_CONNECTTIMEOUT => 2,
        ]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) {
            self::markTestSkipped('Live stack not reachable at ' . self::BASE . ' — run `docker compose up -d`');
        }

        self::$token = self::login(self::DEMO_EMAIL, self::DEMO_PASSWORD);
    }

    private static function login(string $email, string $password): ?string
    {
        $res = self::http('POST', '/api/v1/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);
        return $res['body']['token'] ?? null;
    }

    /**
     * @return array{status: int, body: mixed}
     */
    protected static function http(
        string $method,
        string $path,
        ?array $json = null,
        ?string $token = null
    ): array {
        $ch = curl_init(self::BASE . $path);
        $headers = ['Content-Type: application/json'];
        if ($token !== null) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        if ($json !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json));
        }

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode((string) $raw, true);
        return ['status' => $status, 'body' => $decoded ?? $raw];
    }

    protected static function authed(string $method, string $path, ?array $json = null): array
    {
        return self::http($method, $path, $json, self::$token);
    }
}
