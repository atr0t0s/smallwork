<?php
// tests/Unit/Core/HealthCheckTest.php
declare(strict_types=1);
namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Smallwork\Core\HealthCheck;
use Smallwork\Core\Response;

class HealthCheckTest extends TestCase
{
    public function test_no_checks_returns_healthy(): void
    {
        $hc = new HealthCheck();
        $result = $hc->run();

        $this->assertSame('healthy', $result['status']);
        $this->assertSame([], $result['checks']);
        $this->assertArrayHasKey('timestamp', $result);
    }

    public function test_all_checks_pass_returns_healthy(): void
    {
        $hc = new HealthCheck();
        $hc->addCheck('db', fn() => ['status' => 'ok', 'message' => 'Connected']);
        $hc->addCheck('cache', fn() => ['status' => 'ok', 'message' => 'Available']);

        $result = $hc->run();

        $this->assertSame('healthy', $result['status']);
        $this->assertSame('ok', $result['checks']['db']['status']);
        $this->assertSame('Connected', $result['checks']['db']['message']);
        $this->assertSame('ok', $result['checks']['cache']['status']);
        $this->assertSame('Available', $result['checks']['cache']['message']);
    }

    public function test_one_check_fails_returns_degraded(): void
    {
        $hc = new HealthCheck();
        $hc->addCheck('db', fn() => ['status' => 'ok', 'message' => 'Connected']);
        $hc->addCheck('cache', fn() => ['status' => 'error', 'message' => 'Timeout']);

        $result = $hc->run();

        $this->assertSame('degraded', $result['status']);
        $this->assertSame('ok', $result['checks']['db']['status']);
        $this->assertSame('error', $result['checks']['cache']['status']);
        $this->assertSame('Timeout', $result['checks']['cache']['message']);
    }

    public function test_latency_is_measured(): void
    {
        $hc = new HealthCheck();
        $hc->addCheck('slow', function () {
            usleep(5000); // 5ms
            return ['status' => 'ok', 'message' => 'Done'];
        });

        $result = $hc->run();

        $this->assertArrayHasKey('latency_ms', $result['checks']['slow']);
        $this->assertIsFloat($result['checks']['slow']['latency_ms']);
        $this->assertGreaterThan(0.0, $result['checks']['slow']['latency_ms']);
    }

    public function test_to_response_returns_json_response(): void
    {
        $hc = new HealthCheck();
        $hc->addCheck('db', fn() => ['status' => 'ok', 'message' => 'Up']);

        $response = $hc->toResponse();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->status());
        $this->assertSame('application/json', $response->header('Content-Type'));

        $data = json_decode($response->body(), true);
        $this->assertSame('healthy', $data['status']);
    }

    public function test_to_response_returns_503_when_degraded(): void
    {
        $hc = new HealthCheck();
        $hc->addCheck('db', fn() => ['status' => 'error', 'message' => 'Down']);

        $response = $hc->toResponse();

        $this->assertSame(503, $response->status());
        $data = json_decode($response->body(), true);
        $this->assertSame('degraded', $data['status']);
    }
}
