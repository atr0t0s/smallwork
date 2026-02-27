<?php
// src/View/HtmxHelper.php
declare(strict_types=1);
namespace Smallwork\View;

use Smallwork\Core\Request;
use Smallwork\Core\Response;

class HtmxHelper
{
    public static function isHtmxRequest(Request $request): bool
    {
        return $request->header('HX-Request') === 'true';
    }

    public static function partial(string $html, int $status = 200): Response
    {
        return Response::html($html, $status);
    }

    public static function trigger(Response $response, string|array $events): Response
    {
        if (is_array($events)) {
            $value = json_encode(array_fill_keys($events, true));
        } else {
            $value = $events;
        }
        return $response->withHeader('HX-Trigger', $value);
    }

    public static function redirect(string $url): Response
    {
        return Response::html('', 200)->withHeader('HX-Redirect', $url);
    }

    public static function refresh(): Response
    {
        return Response::html('', 200)->withHeader('HX-Refresh', 'true');
    }

    public static function retarget(Response $response, string $selector): Response
    {
        return $response->withHeader('HX-Retarget', $selector);
    }

    public static function reswap(Response $response, string $strategy): Response
    {
        return $response->withHeader('HX-Reswap', $strategy);
    }

    public static function pushUrl(Response $response, string $url): Response
    {
        return $response->withHeader('HX-Push-Url', $url);
    }
}
