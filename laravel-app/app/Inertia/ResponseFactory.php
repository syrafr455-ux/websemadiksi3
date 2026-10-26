<?php

namespace Inertia;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Arr;

class ResponseFactory
{
    protected string $rootView = 'app';

    protected array $sharedProps = [];

    protected string|Closure|null $version = null;

    public function setRootView(string $name): void
    {
        $this->rootView = $name;
    }

    public function share(string|array $key, mixed $value = null): void
    {
        if (is_array($key)) {
            $this->sharedProps = array_merge($this->sharedProps, $key);
        } else {
            Arr::set($this->sharedProps, $key, $value);
        }
    }

    public function getShared(?string $key = null, mixed $default = null): mixed
    {
        if ($key) {
            return Arr::get($this->sharedProps, $key, $default);
        }

        return $this->sharedProps;
    }

    public function flushShared(): void
    {
        $this->sharedProps = [];
    }

    public function version(string|Closure|null $version = null): ?string
    {
        if (is_null($version)) {
            return $this->version instanceof Closure ? call_user_func($this->version) : $this->version;
        }

        $this->version = $version;

        return null;
    }

    public function getVersion(): ?string
    {
        return $this->version instanceof Closure ? call_user_func($this->version) : $this->version;
    }

    public function render(string $component, array|Arrayable $props = []): Response
    {
        return new Response($component, $props, $this->rootView, $this->getVersion());
    }

    public function lazy(Closure $callback): LazyProp
    {
        return new LazyProp($callback);
    }

    public function location(string|RedirectResponse $url): HttpResponse
    {
        if ($url instanceof RedirectResponse) {
            $url = $url->getTargetUrl();
        }

        return new HttpResponse('', 409, ['X-Inertia-Location' => $url]);
    }
}
