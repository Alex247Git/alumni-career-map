<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Application\Settings\SettingsInterface;
use App\Database;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class JobController
{
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * POST /api/v1/alumni/{id}/jobs
     * Endpoint: Add a new job for an alumnus
     */
    public function create(Request $request, Response $response, array $args): Response
    {
        $alumnusId = (int) ($args['id'] ?? 0);

        if ($alumnusId <= 0) {
            return $this->jsonResponse($response, [
                'status' => 'error',
                'message' => 'Invalid alumnus ID',
            ], 400);
        }

        // Authorization: only the alumnus themselves can add their jobs
        $jwtPayload = $request->getAttribute('jwt_payload');
        $authenticatedUserId = (int) ($jwtPayload['sub'] ?? 0);
        if ($authenticatedUserId !== $alumnusId) {
            return $this->jsonResponse($response, [
                'status' => 'error',
                'message' => 'Forbidden: You can only add jobs to your own profile',
            ], 403);
        }

        $data = $request->getParsedBody();

        // Validate required fields
        $required = ['company_name', 'job_title', 'country', 'city', 'latitude', 'longitude', 'start_date'];
        $missing = [];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            return $this->jsonResponse($response, [
                'status' => 'error',
                'message' => 'Missing required fields: ' . implode(', ', $missing),
            ], 400);
        }

        // Validate coordinates are numeric
        if (!is_numeric($data['latitude']) || !is_numeric($data['longitude'])) {
            return $this->jsonResponse($response, [
                'status' => 'error',
                'message' => 'Latitude and longitude must be numeric',
            ], 400);
        }

        $settings = $this->container->get(SettingsInterface::class);
        $db = Database::getConnection($settings->get('db'));

        // Check if alumnus exists
        $stmt = $db->prepare('SELECT id FROM alumni WHERE id = :alumnus_id LIMIT 1');
        $stmt->execute([':alumnus_id' => $alumnusId]);
        if (!$stmt->fetch()) {
            return $this->jsonResponse($response, [
                'status' => 'error',
                'message' => 'Alumnus not found',
            ], 404);
        }

        // Insert the job
        $stmt = $db->prepare(
            'INSERT INTO jobs (alumnus_id, company_name, job_title, country, city,
             latitude, longitude, is_current, start_date)
             VALUES (:alumnus_id, :company_name, :job_title, :country, :city,
             :latitude, :longitude, :is_current, :start_date)'
        );
        $stmt->execute([
            ':alumnus_id' => $alumnusId,
            ':company_name' => (string) $data['company_name'],
            ':job_title' => (string) $data['job_title'],
            ':country' => (string) $data['country'],
            ':city' => (string) $data['city'],
            ':latitude' => (float) $data['latitude'],
            ':longitude' => (float) $data['longitude'],
            ':is_current' => !empty($data['is_current']) ? 1 : 0,
            ':start_date' => (string) $data['start_date'],
        ]);

        $newJobId = (int) $db->lastInsertId();

        // Fetch created job
        $stmt = $db->prepare('SELECT * FROM jobs WHERE id = :job_id');
        $stmt->execute([':job_id' => $newJobId]);
        $createdJob = $stmt->fetch();

        return $this->jsonResponse($response, [
            'status' => 'success',
            'message' => 'Job created successfully',
            'data' => $createdJob,
        ], 201);
    }

    /**
     * DELETE /api/v1/alumni/{id}/jobs/{jobId}
     * Endpoint #6: Delete a job from an alumnus
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $alumnusId = (int) ($args['id'] ?? 0);
        $jobId = (int) ($args['jobId'] ?? 0);

        if ($alumnusId <= 0 || $jobId <= 0) {
            return $this->jsonResponse($response, [
                'status' => 'error',
                'message' => 'Invalid alumnus ID or job ID',
            ], 400);
        }

        // Authorization: only the alumnus themselves can delete their jobs
        $jwtPayload = $request->getAttribute('jwt_payload');
        $authenticatedUserId = (int) ($jwtPayload['sub'] ?? 0);
        if ($authenticatedUserId !== $alumnusId) {
            return $this->jsonResponse($response, [
                'status' => 'error',
                'message' => 'Forbidden: You can only delete your own jobs',
            ], 403);
        }

        $settings = $this->container->get(SettingsInterface::class);
        $db = Database::getConnection($settings->get('db'));

        // Check if job exists and belongs to this alumnus
        $stmt = $db->prepare('SELECT id FROM jobs WHERE id = :job_id AND alumnus_id = :alumnus_id LIMIT 1');
        $stmt->execute([':job_id' => $jobId, ':alumnus_id' => $alumnusId]);

        if (!$stmt->fetch()) {
            return $this->jsonResponse($response, [
                'status' => 'error',
                'message' => 'Job not found or does not belong to this alumnus',
            ], 404);
        }

        // Delete the job
        $stmt = $db->prepare('DELETE FROM jobs WHERE id = :job_id');
        $stmt->execute([':job_id' => $jobId]);

        return $this->jsonResponse($response, [
            'status' => 'success',
            'message' => 'Job deleted successfully',
        ]);
    }

    /**
     * PUT /api/v1/alumni/{id}/jobs/{jobId}
     * Endpoint #7: Update a job for an alumnus
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $alumnusId = (int) ($args['id'] ?? 0);
        $jobId = (int) ($args['jobId'] ?? 0);

        if ($alumnusId <= 0 || $jobId <= 0) {
            return $this->jsonResponse($response, [
                'status' => 'error',
                'message' => 'Invalid alumnus ID or job ID',
            ], 400);
        }

        // Authorization: only the alumnus themselves can update their jobs
        $jwtPayload = $request->getAttribute('jwt_payload');
        $authenticatedUserId = (int) ($jwtPayload['sub'] ?? 0);
        if ($authenticatedUserId !== $alumnusId) {
            return $this->jsonResponse($response, [
                'status' => 'error',
                'message' => 'Forbidden: You can only update your own jobs',
            ], 403);
        }

        $settings = $this->container->get(SettingsInterface::class);
        $db = Database::getConnection($settings->get('db'));

        // Check if job exists and belongs to this alumnus
        $stmt = $db->prepare('SELECT id FROM jobs WHERE id = :job_id AND alumnus_id = :alumnus_id LIMIT 1');
        $stmt->execute([':job_id' => $jobId, ':alumnus_id' => $alumnusId]);

        if (!$stmt->fetch()) {
            return $this->jsonResponse($response, [
                'status' => 'error',
                'message' => 'Job not found or does not belong to this alumnus',
            ], 404);
        }

        $data = $request->getParsedBody();

        // Build update fields dynamically
        $allowedFields = [
            'company_name', 'job_title', 'country', 'city',
            'latitude', 'longitude', 'is_current', 'start_date',
        ];
        $updates = [];
        $bindings = [':job_id' => $jobId];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = :$field";
                $bindings[":$field"] = $data[$field];
            }
        }

        if (empty($updates)) {
            return $this->jsonResponse($response, [
                'status' => 'error',
                'message' => 'No valid fields to update',
            ], 400);
        }

        $sql = 'UPDATE jobs SET ' . implode(', ', $updates) . ' WHERE id = :job_id';
        $stmt = $db->prepare($sql);
        $stmt->execute($bindings);

        // Fetch updated job
        $stmt = $db->prepare('SELECT * FROM jobs WHERE id = :job_id');
        $stmt->execute([':job_id' => $jobId]);
        $updatedJob = $stmt->fetch();

        return $this->jsonResponse($response, [
            'status' => 'success',
            'message' => 'Job updated successfully',
            'data' => $updatedJob,
        ]);
    }

    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
