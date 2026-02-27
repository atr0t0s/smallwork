<?php
// tests/Unit/AI/PromptTemplateEngineTest.php
declare(strict_types=1);
namespace Tests\Unit\AI;

use PHPUnit\Framework\TestCase;
use Smallwork\AI\Prompts\TemplateEngine;
use RuntimeException;

class PromptTemplateEngineTest extends TestCase
{
    private TemplateEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new TemplateEngine();
    }

    public function testSimpleVariableSubstitution(): void
    {
        $result = $this->engine->render('Hello, {{name}}!', ['name' => 'World']);
        $this->assertSame('Hello, World!', $result);
    }

    public function testMultipleVariables(): void
    {
        $template = '{{greeting}}, {{name}}! You are {{age}} years old.';
        $vars = ['greeting' => 'Hi', 'name' => 'Alice', 'age' => '30'];
        $result = $this->engine->render($template, $vars);
        $this->assertSame('Hi, Alice! You are 30 years old.', $result);
    }

    public function testSameVariableUsedMultipleTimes(): void
    {
        $result = $this->engine->render('{{x}} and {{x}}', ['x' => 'yes']);
        $this->assertSame('yes and yes', $result);
    }

    public function testMissingVariableThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('missing');
        $this->engine->render('Hello, {{name}}!', []);
    }

    public function testTemplateWithNoVariablesReturnsAsIs(): void
    {
        $template = 'No placeholders here.';
        $result = $this->engine->render($template, []);
        $this->assertSame($template, $result);
    }

    public function testRenderFileLoadsAndRenders(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'prompt_');
        file_put_contents($tmpFile, 'Dear {{recipient}}, welcome to {{app}}.');

        try {
            $result = $this->engine->renderFile($tmpFile, [
                'recipient' => 'Bob',
                'app' => 'Smallwork',
            ]);
            $this->assertSame('Dear Bob, welcome to Smallwork.', $result);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testRenderFileThrowsForMissingFile(): void
    {
        $this->expectException(RuntimeException::class);
        $this->engine->renderFile('/nonexistent/path/file.prompt', []);
    }

    public function testRenderFileThrowsForMissingVariable(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'prompt_');
        file_put_contents($tmpFile, 'Hello {{who}}');

        try {
            $this->expectException(RuntimeException::class);
            $this->engine->renderFile($tmpFile, []);
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testVariableWithSpacesInName(): void
    {
        $result = $this->engine->render('{{first name}}', ['first name' => 'George']);
        $this->assertSame('George', $result);
    }

    public function testEmptyStringSubstitution(): void
    {
        $result = $this->engine->render('Value: {{val}}', ['val' => '']);
        $this->assertSame('Value: ', $result);
    }
}
