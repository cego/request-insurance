<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route as RouteFacade;

class RouteMiddlewareTest extends TestCase
{
    public function test_middleware_defaults_to_web_and_the_tool_admin_gate(): void
    {
        $this->assertSame(['web', 'can:tool-admin'], config('request-insurance.middleware'));
    }

    public function test_every_package_route_applies_the_configured_middleware(): void
    {
        $routes = $this->packageRoutes();

        $this->assertNotEmpty($routes);

        foreach ($routes as $route) {
            $this->assertSame(['web', 'can:tool-admin'], $route->gatherMiddleware(), sprintf('Route %s is not gated', $route->uri()));
        }
    }

    public function test_requests_are_forbidden_when_the_gate_denies(): void
    {
        Gate::define('tool-admin', fn () => false);

        $this->get(route('request-insurances.index'))->assertForbidden();
        $this->get(route('request-insurances.monitor'))->assertForbidden();
    }

    /**
     * @return array<int, Route>
     */
    private function packageRoutes(): array
    {
        return array_values(array_filter(
            RouteFacade::getRoutes()->getRoutes(),
            fn (Route $route) => str_starts_with($route->uri(), 'vendor/request-insurances')
        ));
    }
}
