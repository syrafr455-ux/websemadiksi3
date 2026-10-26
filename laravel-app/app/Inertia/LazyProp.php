<?php

namespace Inertia;

use Closure;

class LazyProp
{
    public function __construct(protected Closure $callback) {}

    public function __invoke(): mixed
    {
        return call_user_func($this->callback);
    }
}
