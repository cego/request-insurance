<?php

namespace Cego\RequestInsurance\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\AuthenticationException;

class CegoIdentityProvider implements IdentityProvider
{
    /**
     * Identify the acting admin user from the authenticated session.
     *
     * @param Request $request
     *
     * @return string
     */
    public function getUser(Request $request): string
    {
        $user = Auth::user();

        if ($user === null) {
            throw new AuthenticationException('Request Insurance could not resolve an authenticated admin identity.');
        }

        $identity = trim((string) ($user->name ?? $user->email ?? $user->getAuthIdentifier()));

        if ($identity === '') {
            throw new AuthenticationException('Request Insurance resolved an empty authenticated admin identity.');
        }

        return $identity;
    }
}
