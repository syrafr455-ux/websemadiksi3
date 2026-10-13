<?php

namespace Inertia;

use Closure;
use Illuminate\Contracts\Support\Arrayable;

class LazyProp
{
    public function __construct(protected Closure $callback)
    {
    }

    public function __invoke(): mixed
    {
        return call_user_func($this->callback);
    }
}
