<?php
// src/Core/HealthCheck.php
declare(strict_types=1);
namespace Smallwork\Core;

class HealthCheck
{
    /** @var array<string, callable> */
    private array $checks = [];

    public function addCheck(string $name, callable $check): void
    {
        $this->checks[$name] = $check;
    }

    public function run(): array
    {
        $results = [];
        $allOk = true;

        foreach ($this->checks as $name => $check) {
            $start = hrtime(true);
            $result = $check();
            $elapsed = (hrtime(true) - $start) / 1_000_000;

            if ($result['status'] !== 'ok') {
                $allOk = false;
            }

            $results[$name] = [
                'status' => $result['status'],
                'message' => $result['message'],
                'latency_ms' => $elapsed,
            ];
        }

        return [
            'status' => $allOk ? 'healthy' : 'degraded',
            'checks' => $results,
            'timestamp' => date('c'),
        ];
    }

    public function toResponse(): Response
    {
        $data = $this->run();
        $status = $data['status'] === 'healthy' ? 200 : 503;

        return Response::json($data, $status);
    }
}
