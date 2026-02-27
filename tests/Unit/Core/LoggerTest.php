<?php
// tests/Unit/Core/LoggerTest.php
declare(strict_types=1);
namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Smallwork\Core\Logger;

class LoggerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/smallwork_logger_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        // Clean up temp files
        $files = glob($this->tempDir . '/*');
        foreach ($files as $file) {
            unlink($file);
        }
        rmdir($this->tempDir);
    }

    public function test_writes_json_log_entry_with_correct_structure(): void
    {
        $logger = new Logger($this->tempDir);
        $logger->info('User logged in', ['user_id' => 42]);

        $logFile = $this->tempDir . '/app.log';
        $this->assertFileExists($logFile);

        $line = trim(file_get_contents($logFile));
        $entry = json_decode($line, true);

        $this->assertIsArray($entry);
        $this->assertArrayHasKey('timestamp', $entry);
        $this->assertArrayHasKey('level', $entry);
        $this->assertArrayHasKey('message', $entry);
        $this->assertArrayHasKey('context', $entry);
        $this->assertSame('info', $entry['level']);
        $this->assertSame('User logged in', $entry['message']);
        $this->assertSame(['user_id' => 42], $entry['context']);
    }

    public function test_all_log_level_methods_work(): void
    {
        $logger = new Logger($this->tempDir, 'debug');

        $levels = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];
        foreach ($levels as $level) {
            $logger->$level("Test $level message");
        }

        $logFile = $this->tempDir . '/app.log';
        $lines = array_filter(explode("\n", trim(file_get_contents($logFile))));
        $this->assertCount(8, $lines);

        foreach ($lines as $i => $line) {
            $entry = json_decode($line, true);
            $this->assertSame($levels[$i], $entry['level']);
            $this->assertSame("Test {$levels[$i]} message", $entry['message']);
        }
    }

    public function test_generic_log_method_works(): void
    {
        $logger = new Logger($this->tempDir);
        $logger->log('error', 'Something broke', ['code' => 500]);

        $logFile = $this->tempDir . '/app.log';
        $entry = json_decode(trim(file_get_contents($logFile)), true);

        $this->assertSame('error', $entry['level']);
        $this->assertSame('Something broke', $entry['message']);
        $this->assertSame(['code' => 500], $entry['context']);
    }

    public function test_minimum_level_filtering(): void
    {
        $logger = new Logger($this->tempDir, 'warning');

        $logger->debug('should be ignored');
        $logger->info('should be ignored');
        $logger->notice('should be ignored');
        $logger->warning('should appear');
        $logger->error('should appear');
        $logger->critical('should appear');

        $logFile = $this->tempDir . '/app.log';
        $lines = array_filter(explode("\n", trim(file_get_contents($logFile))));
        $this->assertCount(3, $lines);

        $entries = array_map(fn($line) => json_decode($line, true), $lines);
        $this->assertSame('warning', $entries[0]['level']);
        $this->assertSame('error', $entries[1]['level']);
        $this->assertSame('critical', $entries[2]['level']);
    }

    public function test_message_interpolation_with_context(): void
    {
        $logger = new Logger($this->tempDir);
        $logger->info('User {name} performed {action}', [
            'name' => 'Alice',
            'action' => 'login',
        ]);

        $logFile = $this->tempDir . '/app.log';
        $entry = json_decode(trim(file_get_contents($logFile)), true);

        $this->assertSame('User Alice performed login', $entry['message']);
        $this->assertSame(['name' => 'Alice', 'action' => 'login'], $entry['context']);
    }

    public function test_log_file_created_if_not_exists(): void
    {
        $nestedDir = $this->tempDir . '/nested/logs';
        $logger = new Logger($nestedDir);
        $logger->info('test');

        $logFile = $nestedDir . '/app.log';
        $this->assertFileExists($logFile);

        // Clean up nested dirs
        unlink($logFile);
        rmdir($nestedDir);
        rmdir($this->tempDir . '/nested');
    }

    public function test_timestamp_is_iso8601_format(): void
    {
        $logger = new Logger($this->tempDir);
        $logger->info('test');

        $logFile = $this->tempDir . '/app.log';
        $entry = json_decode(trim(file_get_contents($logFile)), true);

        // Verify timestamp parses as valid date
        $dt = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $entry['timestamp']);
        $this->assertNotFalse($dt);
    }

    public function test_default_minimum_level_is_debug(): void
    {
        $logger = new Logger($this->tempDir);
        $logger->debug('debug message');

        $logFile = $this->tempDir . '/app.log';
        $this->assertFileExists($logFile);
        $entry = json_decode(trim(file_get_contents($logFile)), true);
        $this->assertSame('debug', $entry['level']);
    }

    public function test_empty_context_defaults_to_empty_array(): void
    {
        $logger = new Logger($this->tempDir);
        $logger->info('no context');

        $logFile = $this->tempDir . '/app.log';
        $entry = json_decode(trim(file_get_contents($logFile)), true);
        $this->assertSame([], $entry['context']);
    }
}
