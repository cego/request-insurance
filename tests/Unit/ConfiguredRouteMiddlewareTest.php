<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\Gate;

class ConfiguredRouteMiddlewareTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('request-insurance.middleware', ['web']);
    }

    public function test_the_application_can_replace_the_middleware_stack(): void
    {
        Gate::define('tool-admin', fn () => false);

        $this->get(route('request-insurances.index'))->assertOk();
    }
}
