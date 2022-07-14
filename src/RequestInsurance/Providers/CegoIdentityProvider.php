<?php

namespace Cego\RequestInsurance\Providers;

use Illuminate\Http\Request;

class CegoIdentityProvider implements IdentityProvider
{
    /**
     * @param Request $request
     *
     * @return string
     */
    public function getUser(Request $request): string
    {
        return $request->header('remote-user', '');
    }
}
