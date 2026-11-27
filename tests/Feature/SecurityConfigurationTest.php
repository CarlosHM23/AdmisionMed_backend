<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityConfigurationTest extends TestCase
{
    // Ejecutar con `php artisan test --filter=SecurityConfigurationTest`; evidencia headers, CORS acotado y ausencia de secretos en la respuesta.
    public function test_health_response_has_security_headers_and_does_not_expose_sensitive_configuration(): void
    {
        config([
            'app.key' => 'synthetic-app-key',
            'database.connections.sqlite.password' => 'synthetic-db-password',
            'jwt.secret' => 'synthetic-jwt-secret',
        ]);

        $response = $this->get('/up');

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $this->assertStringNotContainsString('synthetic-app-key', $response->getContent());
        $this->assertStringNotContainsString('synthetic-db-password', $response->getContent());
        $this->assertStringNotContainsString('synthetic-jwt-secret', $response->getContent());
    }

    public function test_cors_does_not_allow_arbitrary_origins(): void
    {
        $origins = config('cors.allowed_origins', []);

        $this->assertNotContains('*', $origins);
        $this->assertSame([], config('cors.allowed_origins_patterns', []));

        $origin = $origins[0];
        $response = $this->call('OPTIONS', '/api/login', [], [], [], [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);

        $response->assertHeader('Access-Control-Allow-Origin', $origin)
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }
}
