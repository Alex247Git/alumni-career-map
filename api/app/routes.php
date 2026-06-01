<?php

declare(strict_types=1);

use App\Controllers\AlumniController;
use App\Controllers\AuthController;
use App\Controllers\JobController;
use App\Middleware\JwtMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;

return function (App $app) {
    // CORS Pre-Flight OPTIONS Handler
    $app->options('/{routes:.*}', function (Request $request, Response $response) {
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    });

    // Health check
    $app->get('/', function (Request $request, Response $response) {
        $response->getBody()->write(json_encode([
            'status' => 'success',
            'message' => 'Alumni REST API is running',
            'version' => 'v1',
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // ============ PUBLIC ROUTES (no JWT required) ============

    // Endpoint #1: Register new alumnus (POST /api/v1/alumni/) — PUBLIC
    $app->post('/api/v1/alumni', [AuthController::class, 'register']);

    // Endpoint #2: Login
    $app->post('/api/v1/auth/login', [AuthController::class, 'login']);

    // ============ PROTECTED ROUTES (JWT required) ============

    $app->group('/api/v1', function (Group $group) {
        // Endpoint #3: Count alumni (GET /api/v1/alumni/count)
        // IMPORTANT: This route MUST be defined BEFORE the /alumni/{id} route
        // so that "count" is not interpreted as an id.
        $group->get('/alumni/count', [AlumniController::class, 'count']);

        // Endpoint #8: Search alumni (GET /api/v1/alumni/search)
        // IMPORTANT: This route MUST be defined BEFORE the /alumni/{id} route
        $group->get('/alumni/search', [AlumniController::class, 'search']);

        // Endpoint #5: Get all alumni (GET /api/v1/alumni/)
        $group->get('/alumni', [AlumniController::class, 'getAll']);

        // Endpoint #4: Get jobs of a specific alumnus (GET /api/v1/alumni/{id}/jobs)
        $group->get('/alumni/{id}/jobs', [AlumniController::class, 'getJobs']);

        // Endpoint #6: Delete a job (DELETE /api/v1/alumni/{id}/jobs/{jobId})
        $group->delete('/alumni/{id}/jobs/{jobId}', [JobController::class, 'delete']);

        // Endpoint #7: Update a job (PUT /api/v1/alumni/{id}/jobs/{jobId})
        $group->put('/alumni/{id}/jobs/{jobId}', [JobController::class, 'update']);
    })->add(JwtMiddleware::class);  // Apply JWT middleware to all routes in this group

    // Fallback for unmatched routes
    $app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function (Request $request, Response $response) {
        $response->getBody()->write(json_encode([
            'status' => 'error',
            'message' => 'Endpoint not found',
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    });
};