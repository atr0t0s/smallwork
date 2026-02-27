<?php

declare(strict_types=1);

namespace Smallwork\Console;

abstract class Command
{
    abstract public function getName(): string;

    abstract public function getDescription(): string;

    abstract public function execute(array $args): int;
}
