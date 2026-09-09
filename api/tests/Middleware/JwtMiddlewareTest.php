<?php

declare(strict_types=1);

namespace Tests\Middleware;

use App\Middleware\JwtMiddleware;
use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Headers;
use Slim\Psr7\Request as SlimRequest;
use Slim\Psr7\Response;
use Slim\Psr7\Uri;

class JwtMiddlewareTest extends PHPUnitTestCase
{
    private const SECRET = 'test-secret-that-is-at-least-32-bytes!';

    private JwtMiddleware $middleware;

    protected function setUp(): void
    {
        $this->middleware = new JwtMiddleware(self::SECRET);
    }

    private function requestWithAuthHeader(string $header): ServerRequestInterface
    {
        $uri = new Uri('http', 'localhost', 80, '/api/v1/alumni/count');
        $handle = fopen('php://temp', 'w+');
        $stream = (new \Slim\Psr7\Factory\StreamFactory())->createStreamFromResource($handle);

        $request = new SlimRequest('GET', $uri, new Headers(), [], [], $stream);
        return $request->withHeader('Authorization', $header);
    }

    private function handler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public Response $capturedResponse;
            public ServerRequestInterface $capturedRequest;

            public function handle(ServerRequestInterface $request): Response
            {
                $this->capturedRequest = $request;
                return new Response();
            }
        };
    }

    public function testMissingAuthorizationHeaderReturns401(): void
    {
        $uri = new Uri('http', 'localhost', 80, '/api/v1/alumni/count');
        $handle = fopen('php://temp', 'w+');
        $stream = (new \Slim\Psr7\Factory\StreamFactory())->createStreamFromResource($handle);
        $request = new SlimRequest('GET', $uri, new Headers(), [], [], $stream);

        $response = $this->middleware->process($request, $this->handler());

        $this->assertSame(401, $response->getStatusCode());
        $this->assertStringContainsString('Authorization header is missing', (string) $response->getBody());
    }

    public function testMalformedAuthorizationHeaderReturns401(): void
    {
        $request = $this->requestWithAuthHeader('Basic abc123');
        $response = $this->middleware->process($request, $this->handler());

        $this->assertSame(401, $response->getStatusCode());
        $this->assertStringContainsString('Bearer <token>', (string) $response->getBody());
    }

    public function testInvalidTokenReturns401(): void
    {
        $request = $this->requestWithAuthHeader('Bearer not-a-real-jwt');
        $response = $this->middleware->process($request, $this->handler());

        $this->assertSame(401, $response->getStatusCode());
        $this->assertStringContainsString('Invalid token', (string) $response->getBody());
    }

    public function testExpiredTokenReturns401(): void
    {
        $expired = JWT::encode(
            ['iss' => 'test', 'sub' => 1, 'iat' => time() - 7200, 'exp' => time() - 3600],
            self::SECRET,
            'HS256'
        );
        $request = $this->requestWithAuthHeader('Bearer ' . $expired);
        $response = $this->middleware->process($request, $this->handler());

        $this->assertSame(401, $response->getStatusCode());
        $this->assertStringContainsString('expired', (string) $response->getBody());
    }

    public function testValidTokenPassesAndSetsJwtPayloadAttribute(): void
    {
        $token = JWT::encode(
            ['iss' => 'test', 'sub' => 7, 'iat' => time(), 'exp' => time() + 3600],
            self::SECRET,
            'HS256'
        );
        $handler = $this->handler();
        $request = $this->requestWithAuthHeader('Bearer ' . $token);

        $response = $this->middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
        $payload = $handler->capturedRequest->getAttribute('jwt_payload');
        $this->assertIsArray($payload);
        $this->assertSame(7, (int) $payload['sub']);
    }

    public function testTokenSignedWithDifferentSecretReturns401(): void
    {
        $token = JWT::encode(
            ['iss' => 'test', 'sub' => 1, 'exp' => time() + 3600],
            'another-secret-that-is-still-32-bytes-ok!',
            'HS256'
        );
        $request = $this->requestWithAuthHeader('Bearer ' . $token);
        $response = $this->middleware->process($request, $this->handler());

        $this->assertSame(401, $response->getStatusCode());
    }
}
