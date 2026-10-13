<?php

namespace Inertia;

use Closure;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Response as ResponseFactory;
use Illuminate\Support\Facades\View;

class Response implements Responsable
{
    protected string $rootView = 'app';

    public function __construct(
        protected string $component,
        protected array|Arrayable $props,
        protected string $rootViewName = 'app',
        protected ?string $version = null
    ) {
        $this->rootView = $rootViewName;
    }

    public function rootView(string $rootView): self
    {
        $this->rootView = $rootView;
        return $this;
    }

    public function toResponse($request)
    {
        $only = array_filter(explode(',', $request->header('X-Inertia-Partial-Data', '')));

        $props = ($this->props instanceof Arrayable) ? $this->props->toArray() : $this->props;

        $shared = Inertia::getShared();
        $props = array_merge($shared, $props);

        // Resolve lazy props & closures
        $isPartial = $request->header('X-Inertia-Partial-Component') === $this->component;

        foreach ($props as $key => $value) {
            if ($isPartial && !empty($only)) {
                if (!in_array($key, $only, true)) {
                    unset($props[$key]);
                    continue;
                }
            } elseif ($value instanceof LazyProp) {
                unset($props[$key]);
                continue;
            }

            if ($value instanceof Closure) {
                $props[$key] = App::call($value);
            } elseif ($value instanceof LazyProp) {
                $props[$key] = App::call($value);
            } elseif ($value instanceof Arrayable) {
                $props[$key] = $value->toArray();
            }
        }

        $url = $request->getBaseUrl() . $request->getRequestUri();

        $page = [
            'component' => $this->component,
            'props' => $props,
            'url' => $url,
            'version' => $this->version ?? Inertia::getVersion(),
        ];

        if ($request->header('X-Inertia')) {
            return new JsonResponse($page, 200, [
                'Vary' => 'Accept',
                'X-Inertia' => 'true',
            ]);
        }

        return ResponseFactory::view($this->rootView, [
            'page' => $page,
        ]);
    }
}
