<?php

declare(strict_types=1);

namespace Smallwork\Core\Middleware;

use Smallwork\Core\Request;
use Smallwork\Core\Response;

class Pipeline
{
    public function handle(Request $request, array $middleware, callable $handler): Response
    {
        $pipeline = $handler;
        foreach (array_reverse($middleware) as $mw) {
            $next = $pipeline;
            $pipeline = function (Request $request) use ($mw, $next): Response {
                return $mw->handle($request, $next);
            };
        }
        return $pipeline($request);
    }
}
