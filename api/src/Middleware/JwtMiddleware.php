<?php

declare(strict_types=1);

namespace App\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

class JwtMiddleware implements MiddlewareInterface
{
    private string $secret;

    public function __construct(string $secret)
    {
        $this->secret = $secret;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $authHeader = $request->getHeaderLine('Authorization');

        if (empty($authHeader)) {
            return $this->unauthorizedResponse('Authorization header is missing');
        }

        // Expect "Bearer <token>"
        if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return $this->unauthorizedResponse('Invalid Authorization header format. Use: Bearer <token>');
        }

        $token = $matches[1];

        try {
            $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
            $request = $request->withAttribute('jwt_payload', (array) $decoded);
            return $handler->handle($request);
        } catch (\Firebase\JWT\ExpiredException $e) {
            return $this->unauthorizedResponse('Token has expired');
        } catch (\Exception $e) {
            return $this->unauthorizedResponse('Invalid token: ' . $e->getMessage());
        }
    }

    private function unauthorizedResponse(string $message): Response
    {
        $response = new SlimResponse();
        $response->getBody()->write(json_encode([
            'status' => 'error',
            'message' => $message,
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(401);
    }
}