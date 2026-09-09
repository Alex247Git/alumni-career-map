<?php

declare(strict_types=1);

namespace Tests\Integration;

/**
 * Live integration tests for auth + ownership endpoints.
 * Skipped entirely when the Docker stack is not running.
 */
class AuthAndOwnershipTest extends LiveStackTestCase
{
    public function testLoginWithValidCredentialsReturnsJwt(): void
    {
        $res = self::http('POST', '/api/v1/auth/login', [
            'email' => 'gpapadop@example.com',
            'password' => 'alumni2026',
        ]);

        $this->assertSame(200, $res['status']);
        $this->assertSame('success', $res['body']['status']);
        $this->assertNotEmpty($res['body']['token']);
    }

    public function testLoginWithWrongPasswordReturns401(): void
    {
        $res = self::http('POST', '/api/v1/auth/login', [
            'email' => 'gpapadop@example.com',
            'password' => 'wrong-password',
        ]);

        $this->assertSame(401, $res['status']);
        $this->assertSame('error', $res['body']['status']);
    }

    public function testLoginWithUnknownEmailReturns401(): void
    {
        $res = self::http('POST', '/api/v1/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'whatever',
        ]);

        $this->assertSame(401, $res['status']);
    }

    public function testProtectedEndpointWithoutTokenReturns401(): void
    {
        $res = self::http('GET', '/api/v1/alumni/count');
        $this->assertSame(401, $res['status']);
    }

    public function testProtectedEndpointWithTokenReturnsCount(): void
    {
        $res = self::authed('GET', '/api/v1/alumni/count');

        $this->assertSame(200, $res['status']);
        $this->assertGreaterThanOrEqual(20, (int) $res['body']);
    }

    public function testOwnershipCannotCreateJobForAnotherAlumnus(): void
    {
        $payload = [
            'company_name' => 'Hacker Corp',
            'job_title' => 'Intruder',
            'country' => 'Nowhere',
            'city' => 'Testville',
            'latitude' => 0.0,
            'longitude' => 0.0,
            'start_date' => '2026-01-01',
            'is_current' => 1,
        ];

        // We are logged in as alumnus 1; posting jobs "as" alumnus 2 must be forbidden.
        $res = self::authed('POST', '/api/v1/alumni/2/jobs', $payload);

        $this->assertSame(403, $res['status']);
        $this->assertSame('error', $res['body']['status']);
    }

    public function testOwnershipCannotUpdateAnotherAlumnussJob(): void
    {
        // Alumnus 2 owns job 2 (from seed); we are alumnus 1.
        $res = self::authed('PUT', '/api/v1/alumni/2/jobs/2', ['job_title' => 'Hacked']);

        $this->assertSame(403, $res['status']);
    }

    public function testOwnershipCannotDeleteAnotherAlumnussJob(): void
    {
        $res = self::authed('DELETE', '/api/v1/alumni/2/jobs/2');

        $this->assertSame(403, $res['status']);
    }
}
