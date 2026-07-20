<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Auth\GenericUser;
use Illuminate\Auth\AuthenticationException;
use Cego\RequestInsurance\Providers\CegoIdentityProvider;

class CegoIdentityProviderTest extends TestCase
{
    public function test_it_requires_an_authenticated_identity(): void
    {
        $this->expectException(AuthenticationException::class);

        (new CegoIdentityProvider())->getUser(Request::create('/'));
    }

    public function test_it_returns_the_authenticated_identity(): void
    {
        $this->actingAs(new GenericUser(['id' => 1, 'name' => 'alice']));

        $this->assertSame('alice', (new CegoIdentityProvider())->getUser(Request::create('/')));
    }
}
