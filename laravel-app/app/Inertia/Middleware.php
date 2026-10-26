<?php

namespace Inertia;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Middleware
{
    protected string $rootView = 'app';

    public function handle(Request $request, Closure $next): Response
    {
        Inertia::setRootView($this->rootView);
        Inertia::version(fn () => $this->version($request));
        Inertia::share($this->share($request));

        $response = $next($request);

        if (! $request->header('X-Inertia')) {
            return $response;
        }

        if ($request->method() === 'GET' && $request->header('X-Inertia-Version', '') !== Inertia::getVersion()) {
            if ($response->getStatusCode() === 200) {
                return Inertia::location($request->fullUrl());
            }
        }

        if ($response->getStatusCode() === 302 && in_array($request->method(), ['PUT', 'PATCH', 'DELETE'])) {
            $response->setStatusCode(303);
        }

        return $response;
    }

    public function version(Request $request): ?string
    {
        if (config('app.asset_url')) {
            return md5(config('app.asset_url'));
        }

        $manifest = public_path('build/manifest.json');
        if (file_exists($manifest)) {
            return md5_file($manifest);
        }

        return null;
    }

    public function share(Request $request): array
    {
        return [
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'nim' => $request->user()->nim,
                    'role' => $request->user()->role?->value ?? $request->user()->role,
                    'verification_status' => $request->user()->verification_status,
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'info' => fn () => $request->session()->get('info'),
            ],
            'errors' => fn () => $request->session()->get('errors')
                ? $request->session()->get('errors')->getBag('default')->getMessages()
                : (object) [],
        ];
    }
}
