<?php

namespace Inertia;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void setRootView(string $name)
 * @method static void share(string|array $key, mixed $value = null)
 * @method static mixed getShared(?string $key = null, mixed $default = null)
 * @method static void flushShared()
 * @method static ?string version(string|Closure|null $version = null)
 * @method static ?string getVersion()
 * @method static Response render(string $component, array|Arrayable $props = [])
 * @method static LazyProp lazy(Closure $callback)
 * @method static HttpResponse location(string|RedirectResponse $url)
 *
 * @see ResponseFactory
 */
class Inertia extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ResponseFactory::class;
    }
}
