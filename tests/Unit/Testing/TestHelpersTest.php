<?php
// tests/Unit/Testing/TestHelpersTest.php
declare(strict_types=1);
namespace Tests\Unit\Testing;

use PHPUnit\Framework\TestCase;
use Smallwork\Core\App;
use Smallwork\Core\Request;
use Smallwork\Core\Response;
use Smallwork\AI\Gateway;
use Smallwork\Testing\TestCase as SmallworkTestCase;
use Smallwork\Testing\AIMock;

class TestHelpersTest extends TestCase
{
    // ── TestCase tests ──────────────────────────────────────────────

    public function test_create_app_returns_app_instance(): void
    {
        $tc = new ConcreteTestCase('test_create_app_returns_app_instance');
        $tc->setUp();

        $app = $tc->createApp();
        $this->assertInstanceOf(App::class, $app);

        $tc->tearDown();
    }

    public function test_get_request(): void
    {
        $tc = new ConcreteTestCase('test_get_request');
        $tc->setUp();

        $app = $tc->createApp();
        $app->router()->get('/hello', fn(Request $r) => Response::json(['msg' => 'hi']));

        $response = $tc->get('/hello');
        $this->assertEquals(200, $response->status());
        $this->assertStringContainsString('hi', $response->body());
    }

    public function test_post_request(): void
    {
        $tc = new ConcreteTestCase('test_post_request');
        $tc->setUp();

        $app = $tc->createApp();
        $app->router()->post('/submit', fn(Request $r) => Response::json([
            'received' => $r->input('name'),
        ]));

        $response = $tc->post('/submit', ['name' => 'Alice']);
        $this->assertEquals(200, $response->status());
        $this->assertStringContainsString('Alice', $response->body());
    }

    public function test_json_request(): void
    {
        $tc = new ConcreteTestCase('test_json_request');
        $tc->setUp();

        $app = $tc->createApp();
        $app->router()->post('/api/data', fn(Request $r) => Response::json([
            'echo' => $r->json('key'),
        ]));

        $response = $tc->json('POST', '/api/data', ['key' => 'value']);
        $this->assertEquals(200, $response->status());
        $this->assertStringContainsString('value', $response->body());
        $this->assertEquals('application/json', $response->header('Content-Type'));
    }

    public function test_json_get_request(): void
    {
        $tc = new ConcreteTestCase('test_json_get_request');
        $tc->setUp();

        $app = $tc->createApp();
        $app->router()->get('/api/info', fn(Request $r) => Response::json(['ok' => true]));

        $response = $tc->json('GET', '/api/info');
        $this->assertEquals(200, $response->status());
    }

    public function test_teardown_cleans_temp_directory(): void
    {
        $tc = new ConcreteTestCase('test_teardown_cleans_temp_directory');
        $tc->setUp();
        $dir = $tc->getFixtureDir();
        $this->assertDirectoryExists($dir);

        $tc->tearDown();
        $this->assertDirectoryDoesNotExist($dir);
    }

    // ── AIMock tests ────────────────────────────────────────────────

    public function test_ai_mock_chat_returns_gateway_with_response(): void
    {
        $gateway = AIMock::chat('Hello from mock');

        $this->assertInstanceOf(Gateway::class, $gateway);

        $result = $gateway->chat([['role' => 'user', 'content' => 'Hi']]);
        $this->assertEquals('Hello from mock', $result['content']);
    }

    public function test_ai_mock_chat_with_custom_usage(): void
    {
        $usage = ['prompt_tokens' => 5, 'completion_tokens' => 10, 'total_tokens' => 15];
        $gateway = AIMock::chat('Response', $usage);

        $result = $gateway->chat([['role' => 'user', 'content' => 'Hi']]);
        $this->assertEquals(15, $result['usage']['total_tokens']);
        $this->assertEquals(5, $result['usage']['prompt_tokens']);
    }

    public function test_ai_mock_embed_returns_gateway_with_vectors(): void
    {
        $vectors = [[0.1, 0.2, 0.3], [0.4, 0.5, 0.6]];
        $gateway = AIMock::embed($vectors);

        $this->assertInstanceOf(Gateway::class, $gateway);

        $result = $gateway->embed(['text1', 'text2']);
        $this->assertEquals($vectors, $result);
    }

    public function test_ai_mock_embed_single_input(): void
    {
        $vectors = [[0.1, 0.2, 0.3]];
        $gateway = AIMock::embed($vectors);

        $result = $gateway->embed('single text');
        $this->assertEquals([[0.1, 0.2, 0.3]], $result);
    }

    public function test_ai_mock_chat_stream(): void
    {
        $gateway = AIMock::chat('Streamed response');

        $chunks = [];
        $result = $gateway->streamChat(
            [['role' => 'user', 'content' => 'Hi']],
            function (string $chunk) use (&$chunks) { $chunks[] = $chunk; }
        );

        $this->assertEquals('Streamed response', $result['content']);
        $this->assertNotEmpty($chunks);
    }
}

/**
 * Concrete subclass to test SmallworkTestCase (which is abstract-like via setUp).
 */
class ConcreteTestCase extends SmallworkTestCase
{
    public function getFixtureDir(): string
    {
        return $this->fixtureDir;
    }
}
