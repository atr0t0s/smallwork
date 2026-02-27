<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Smallwork\Core\App;

$app = App::create(__DIR__ . '/..');
$app->run();
