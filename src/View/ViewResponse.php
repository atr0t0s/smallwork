<?php
// src/View/ViewResponse.php
declare(strict_types=1);
namespace Smallwork\View;

use Smallwork\Core\Response;

class ViewResponse
{
    public function __construct(private Engine $engine) {}

    public function make(string $view, array $data = [], int $status = 200): Response
    {
        $html = $this->engine->render($view, $data);
        return Response::html($html, $status);
    }
}
