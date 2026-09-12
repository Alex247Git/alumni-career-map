<?php

declare(strict_types=1);

namespace Tests\Integration;

/**
 * Live integration tests for the jobs CRUD (create path added in this PR).
 * Skipped entirely when the Docker stack is not running.
 */
class JobsCrudTest extends LiveStackTestCase
{
    public function testCreateUpdateAndDeleteOwnJobLifecycle(): void
    {
        $payload = [
            'company_name' => 'PHPUnit Corp',
            'job_title' => 'Integration Test Job',
            'country' => 'Greece',
            'city' => 'Athens',
            'latitude' => 37.983810,
            'longitude' => 23.727539,
            'start_date' => '2026-01-01',
            'is_current' => 1,
        ];

        // CREATE (as alumnus 1 = gpapadop@example.com)
        $create = self::authed('POST', '/api/v1/alumni/1/jobs', $payload);
        $this->assertSame(201, $create['status'], 'create must return 201');
        $this->assertSame('success', $create['body']['status']);

        $jobId = (int) ($create['body']['data']['id'] ?? 0);
        $this->assertGreaterThan(0, $jobId, 'created job must return an id');

        try {
            // UPDATE it (ownership: same alumnus → allowed)
            $update = self::authed('PUT', "/api/v1/alumni/1/jobs/{$jobId}", [
                'job_title' => 'Updated Title',
            ]);
            $this->assertSame(200, $update['status']);
            $this->assertSame('Updated Title', $update['body']['data']['job_title']);
        } finally {
            // DELETE (cleanup — also verifies ownership delete)
            $delete = self::authed('DELETE', "/api/v1/alumni/1/jobs/{$jobId}");
            $this->assertSame(200, $delete['status']);
        }
    }

    public function testCreateWithMissingFieldsReturns400(): void
    {
        $res = self::authed('POST', '/api/v1/alumni/1/jobs', [
            'company_name' => 'Only Company',
        ]);

        $this->assertSame(400, $res['status']);
        $this->assertStringContainsString('Missing required fields', (string) $res['body']['message']);
    }

    public function testCreateWithNonNumericCoordinatesReturns400(): void
    {
        $res = self::authed('POST', '/api/v1/alumni/1/jobs', [
            'company_name' => 'Bad Coords Inc',
            'job_title' => 'X',
            'country' => 'Greece',
            'city' => 'Athens',
            'latitude' => 'not-a-number',
            'longitude' => 'also-not',
            'start_date' => '2026-01-01',
        ]);

        $this->assertSame(400, $res['status']);
        $this->assertStringContainsString('numeric', (string) $res['body']['message']);
    }

    public function testUpdateNonExistentJobReturns404(): void
    {
        $res = self::authed('PUT', '/api/v1/alumni/1/jobs/999999', ['job_title' => 'Ghost']);

        $this->assertSame(404, $res['status']);
    }

    public function testGetOwnJobsReturnsListWithJobs(): void
    {
        // Alumnus 1 owns job 1 (from seed) — reading your own jobs is allowed.
        $res = self::authed('GET', '/api/v1/alumni/1/jobs');

        $this->assertSame(200, $res['status']);
        $this->assertIsArray($res['body']['data']);
        $this->assertNotEmpty($res['body']['data'], 'seeded alumnus 1 should have at least one job');
    }

    public function testGetJobsForNonExistentAlumnusReturns404(): void
    {
        $res = self::authed('GET', '/api/v1/alumni/999999/jobs');

        $this->assertSame(404, $res['status']);
    }
}
