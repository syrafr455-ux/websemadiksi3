<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use ReflectionMethod;
use Tests\TestCase;

class NamedRoutesAndControllersTest extends TestCase
{
    public function test_all_named_routes_point_to_valid_controllers_methods_and_views(): void
    {
        $routes = Route::getRoutes()->getRoutesByName();

        $this->assertNotEmpty($routes, 'Route collection should not be empty.');

        foreach ($routes as $name => $route) {
            $action = $route->getAction();

            // Skip closure routes or internal framework routes
            if (! isset($action['controller']) || ! is_string($action['controller'])) {
                continue;
            }

            [$controllerClass, $method] = explode('@', $action['controller']);

            $this->assertTrue(
                class_exists($controllerClass),
                "Route [{$name}] points to non-existent controller [{$controllerClass}]."
            );

            $this->assertTrue(
                method_exists($controllerClass, $method),
                "Route [{$name}] points to non-existent method [{$method}] on [{$controllerClass}]."
            );

            // Reflection to check referenced views in controller method
            $reflection = new ReflectionMethod($controllerClass, $method);
            $fileName = $reflection->getFileName();

            if ($fileName && file_exists($fileName)) {
                $lines = file($fileName);
                $start = $reflection->getStartLine() - 1;
                $end = $reflection->getEndLine();
                $body = implode('', array_slice($lines, $start, $end - $start));

                // Find view('...') or view("...")
                if (preg_match_all("/view\(\s*['\"]([a-zA-Z0-9_\-\.]+)['\"]/", $body, $matches)) {
                    foreach ($matches[1] as $viewName) {
                        $this->assertTrue(
                            View::exists($viewName),
                            "Method [{$controllerClass}@{$method}] referenced by route [{$name}] returns non-existent Blade view [{$viewName}]."
                        );
                    }
                }

                // Find Inertia::render('...')
                if (preg_match_all("/Inertia::render\(\s*['\"]([a-zA-Z0-9_\-\/\.]+)['\"]/", $body, $inertiaMatches)) {
                    foreach ($inertiaMatches[1] as $component) {
                        $componentPathJsx = resource_path("js/Pages/{$component}.jsx");
                        $componentPathJs = resource_path("js/Pages/{$component}.js");
                        $this->assertTrue(
                            file_exists($componentPathJsx) || file_exists($componentPathJs),
                            "Method [{$controllerClass}@{$method}] referenced by route [{$name}] renders non-existent Inertia component [{$component}]."
                        );
                    }
                }
            }
        }
    }
}
