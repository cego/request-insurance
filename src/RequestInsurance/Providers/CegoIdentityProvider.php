<?php

namespace Cego\RequestInsurance\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            return '';
        }

        return (string) ($user->name ?? $user->email ?? $user->getAuthIdentifier());
    }
}
