<?php

namespace Cego\RequestInsurance\Providers;

use Illuminate\Http\Request;

class IdentityProvider
{
    /**
     * @param Request $request
     * @return string|null
     */
    public function getUser(Request $request) : ?string
    {
        return $request->header('remote-user', '');
    }
}
