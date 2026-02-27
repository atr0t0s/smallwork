<?php
// tests/Unit/View/HtmxHelperTest.php
declare(strict_types=1);
namespace Tests\Unit\View;

use PHPUnit\Framework\TestCase;
use Smallwork\View\HtmxHelper;
use Smallwork\Core\Request;
use Smallwork\Core\Response;

class HtmxHelperTest extends TestCase
{
    public function test_is_htmx_request(): void
    {
        $htmxReq = Request::create('GET', '/', headers: ['HX-Request' => 'true']);
        $normalReq = Request::create('GET', '/');

        $this->assertTrue(HtmxHelper::isHtmxRequest($htmxReq));
        $this->assertFalse(HtmxHelper::isHtmxRequest($normalReq));
    }

    public function test_partial_response(): void
    {
        $response = HtmxHelper::partial('<div>Updated</div>');
        $this->assertEquals(200, $response->status());
        $this->assertEquals('<div>Updated</div>', $response->body());
        $this->assertEquals('text/html; charset=UTF-8', $response->header('Content-Type'));
    }

    public function test_trigger_event(): void
    {
        $response = HtmxHelper::partial('<p>Done</p>');
        $response = HtmxHelper::trigger($response, 'messageAdded');
        $this->assertEquals('messageAdded', $response->header('HX-Trigger'));
    }

    public function test_trigger_multiple_events(): void
    {
        $response = HtmxHelper::partial('<p>Done</p>');
        $response = HtmxHelper::trigger($response, ['messageAdded', 'chatUpdated']);
        $header = $response->header('HX-Trigger');
        $this->assertStringContainsString('messageAdded', $header);
        $this->assertStringContainsString('chatUpdated', $header);
    }

    public function test_redirect(): void
    {
        $response = HtmxHelper::redirect('/dashboard');
        $this->assertEquals('/dashboard', $response->header('HX-Redirect'));
    }

    public function test_refresh(): void
    {
        $response = HtmxHelper::refresh();
        $this->assertEquals('true', $response->header('HX-Refresh'));
    }

    public function test_retarget(): void
    {
        $response = HtmxHelper::partial('<div>New</div>');
        $response = HtmxHelper::retarget($response, '#sidebar');
        $this->assertEquals('#sidebar', $response->header('HX-Retarget'));
    }

    public function test_reswap(): void
    {
        $response = HtmxHelper::partial('<div>New</div>');
        $response = HtmxHelper::reswap($response, 'outerHTML');
        $this->assertEquals('outerHTML', $response->header('HX-Reswap'));
    }

    public function test_push_url(): void
    {
        $response = HtmxHelper::partial('<div>Page</div>');
        $response = HtmxHelper::pushUrl($response, '/new-page');
        $this->assertEquals('/new-page', $response->header('HX-Push-Url'));
    }
}
