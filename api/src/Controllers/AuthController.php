<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Application\Settings\SettingsInterface;
use App\Database;
use Firebase\JWT\JWT;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController
{
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * POST /api/v1/auth/login
     */
    public function login(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($email) || empty($password)) {
            return $this->jsonResponse($response, [
                'status' => 'error',
                'message' => 'Email and password are required',
            ], 400);
        }

        $settings = $this->container->get(SettingsInterface::class);
        $dbSettings = $settings->get('db');
        $db = Database::getConnection($dbSettings);

        // Check if alumnus exists
        $stmt = $db->prepare('SELECT id, first_name, last_name, email, password FROM alumni WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $alumnus = $stmt->fetch();

        if (!$alumnus) {
            return $this->jsonResponse($response, [
                'status' => 'error',
                'message' => 'Invalid email or password',
            ], 401);
        }

        // Verify password
        // If password field is null/empty (existing data), use a default check for backward compatibility
        $passwordValid = false;
        if (!empty($alumnus['password']) && password_verify($password, $alumnus['password'])) {
            $passwordValid = true;
        } elseif (empty($alumnus['password']) && $password === 'alumni2026') {
            // Default password for existing seeded alumni
            $passwordValid = true;
        }

        if (!$passwordValid) {
            return $this->jsonResponse($response, [
                'status' => 'error',
                'message' => 'Invalid email or password',
            ], 401);
        }

        // Generate JWT
        $jwtSettings = $settings->get('jwt');
        $now = time();
        $payload = [
            'iss' => $jwtSettings['issuer'],
            'iat' => $now,
            'exp' => $now + $jwtSettings['expire'],
            'sub' => (int) $alumnus['id'],
            'name' => $alumnus['first_name'] . ' ' . $alumnus['last_name'],
            'email' => $alumnus['email'],
        ];

        $token = JWT::encode($payload, $jwtSettings['secret'], 'HS256');

        return $this->jsonResponse($response, [
            'status' => 'success',
            'token' => $token,
            'data' => [
                'id' => (int) $alumnus['id'],
                'first_name' => $alumnus['first_name'],
                'last_name' => $alumnus['last_name'],
                'email' => $alumnus['email'],
            ],
        ]);
    }

    /**
     * POST /api/v1/alumni/ (Register new alumnus)
     */
    public function register(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        // Validate required fields
        $required = ['first_name', 'last_name', 'email', 'password', 'enrollment_year', 'graduation_year'];
        $missing = [];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            return $this->jsonResponse($response, [
                'status' => 'error',
                'message' => 'Missing required fields: ' . implode(', ', $missing),
            ], 400);
        }

        // Validate email format
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->jsonResponse($response, [
                'status' => 'error',
                'message' => 'Invalid email format',
            ], 400);
        }

        $settings = $this->container->get(SettingsInterface::class);
        $db = Database::getConnection($settings->get('db'));

        // Check if email already exists
        $stmt = $db->prepare('SELECT id FROM alumni WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $data['email']]);
        if ($stmt->fetch()) {
            return $this->jsonResponse($response, [
                'status' => 'error',
                'message' => 'Email already registered',
            ], 409);
        }

        // Hash password
        $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);

        // Insert new alumnus
        $stmt = $db->prepare(
            'INSERT INTO alumni (first_name, last_name, email, password, enrollment_year, graduation_year) 
             VALUES (:first_name, :last_name, :email, :password, :enrollment_year, :graduation_year)'
        );
        $stmt->execute([
            ':first_name' => $data['first_name'],
            ':last_name' => $data['last_name'],
            ':email' => $data['email'],
            ':password' => $hashedPassword,
            ':enrollment_year' => (int) $data['enrollment_year'],
            ':graduation_year' => (int) $data['graduation_year'],
        ]);

        $newId = (int) $db->lastInsertId();

        return $this->jsonResponse($response, [
            'status' => 'success',
            'message' => 'Alumnus registered successfully',
            'data' => [
                'id' => $newId,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
            ],
        ], 201);
    }

    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}