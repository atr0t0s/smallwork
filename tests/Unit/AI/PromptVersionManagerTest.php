<?php
// tests/Unit/AI/PromptVersionManagerTest.php
declare(strict_types=1);
namespace Tests\Unit\AI;

use PHPUnit\Framework\TestCase;
use Smallwork\AI\Prompts\VersionManager;
use RuntimeException;

class PromptVersionManagerTest extends TestCase
{
    private string $tmpDir;
    private VersionManager $manager;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/prompt_version_test_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
        $this->manager = new VersionManager($this->tmpDir);
    }

    protected function tearDown(): void
    {
        $files = glob($this->tmpDir . '/*');
        foreach ($files as $file) {
            unlink($file);
        }
        rmdir($this->tmpDir);
    }

    private function createPrompt(string $name, int $version, string $content): void
    {
        file_put_contents(
            $this->tmpDir . "/{$name}.v{$version}.prompt",
            $content
        );
    }

    public function testDiscoverVersionsOfPrompt(): void
    {
        $this->createPrompt('greeting', 1, 'Hello v1');
        $this->createPrompt('greeting', 2, 'Hello v2');
        $this->createPrompt('greeting', 3, 'Hello v3');

        $versions = $this->manager->versions('greeting');

        $this->assertSame([1, 2, 3], $versions);
    }

    public function testVersionsReturnedSorted(): void
    {
        $this->createPrompt('greeting', 3, 'v3');
        $this->createPrompt('greeting', 1, 'v1');
        $this->createPrompt('greeting', 5, 'v5');

        $versions = $this->manager->versions('greeting');

        $this->assertSame([1, 3, 5], $versions);
    }

    public function testLatestReturnsHighestVersion(): void
    {
        $this->createPrompt('greeting', 1, 'Hello v1');
        $this->createPrompt('greeting', 2, 'Hello v2');
        $this->createPrompt('greeting', 3, 'Hello v3');

        $content = $this->manager->latest('greeting');

        $this->assertSame('Hello v3', $content);
    }

    public function testGetSpecificVersion(): void
    {
        $this->createPrompt('greeting', 1, 'Hello v1');
        $this->createPrompt('greeting', 2, 'Hello v2');

        $this->assertSame('Hello v1', $this->manager->version('greeting', 1));
        $this->assertSame('Hello v2', $this->manager->version('greeting', 2));
    }

    public function testUnknownPromptThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Prompt "nonexistent" not found');

        $this->manager->latest('nonexistent');
    }

    public function testUnknownVersionThrowsException(): void
    {
        $this->createPrompt('greeting', 1, 'Hello v1');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Version 99 of prompt "greeting" not found');

        $this->manager->version('greeting', 99);
    }

    public function testVersionsThrowsForUnknownPrompt(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Prompt "missing" not found');

        $this->manager->versions('missing');
    }

    public function testMultiplePromptsCoexist(): void
    {
        $this->createPrompt('greeting', 1, 'Hi v1');
        $this->createPrompt('greeting', 2, 'Hi v2');
        $this->createPrompt('farewell', 1, 'Bye v1');
        $this->createPrompt('farewell', 2, 'Bye v2');
        $this->createPrompt('farewell', 3, 'Bye v3');

        $this->assertSame([1, 2], $this->manager->versions('greeting'));
        $this->assertSame([1, 2, 3], $this->manager->versions('farewell'));
        $this->assertSame('Hi v2', $this->manager->latest('greeting'));
        $this->assertSame('Bye v3', $this->manager->latest('farewell'));
    }
}
