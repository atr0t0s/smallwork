<?php
// src/Core/Logger.php
declare(strict_types=1);
namespace Smallwork\Core;

class Logger
{
    // PSR-3 log levels in order of severity
    public const EMERGENCY = 'emergency';
    public const ALERT     = 'alert';
    public const CRITICAL  = 'critical';
    public const ERROR     = 'error';
    public const WARNING   = 'warning';
    public const NOTICE    = 'notice';
    public const INFO      = 'info';
    public const DEBUG     = 'debug';

    /** @var array<string, int> Level priority (higher = more severe) */
    private const LEVEL_PRIORITY = [
        self::DEBUG     => 0,
        self::INFO      => 1,
        self::NOTICE    => 2,
        self::WARNING   => 3,
        self::ERROR     => 4,
        self::CRITICAL  => 5,
        self::ALERT     => 6,
        self::EMERGENCY => 7,
    ];

    private string $logDir;
    private int $minPriority;

    public function __construct(string $logDir = 'storage/logs', string $minLevel = 'debug')
    {
        $this->logDir = $logDir;
        $this->minPriority = self::LEVEL_PRIORITY[$minLevel]
            ?? throw new \InvalidArgumentException("Invalid log level: $minLevel");
    }

    public function log(string $level, string $message, array $context = []): void
    {
        $priority = self::LEVEL_PRIORITY[$level]
            ?? throw new \InvalidArgumentException("Invalid log level: $level");

        if ($priority < $this->minPriority) {
            return;
        }

        $interpolated = $this->interpolate($message, $context);

        $entry = json_encode([
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'level'     => $level,
            'message'   => $interpolated,
            'context'   => $context,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0777, true);
        }

        file_put_contents($this->logDir . '/app.log', $entry . "\n", FILE_APPEND | LOCK_EX);
    }

    public function emergency(string $message, array $context = []): void
    {
        $this->log(self::EMERGENCY, $message, $context);
    }

    public function alert(string $message, array $context = []): void
    {
        $this->log(self::ALERT, $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log(self::CRITICAL, $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log(self::ERROR, $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log(self::WARNING, $message, $context);
    }

    public function notice(string $message, array $context = []): void
    {
        $this->log(self::NOTICE, $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log(self::INFO, $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log(self::DEBUG, $message, $context);
    }

    /**
     * Replace {key} placeholders in message with context values.
     */
    private function interpolate(string $message, array $context): string
    {
        $replacements = [];
        foreach ($context as $key => $value) {
            if (is_string($value) || is_numeric($value) || (is_object($value) && method_exists($value, '__toString'))) {
                $replacements['{' . $key . '}'] = (string) $value;
            }
        }

        return strtr($message, $replacements);
    }
}
