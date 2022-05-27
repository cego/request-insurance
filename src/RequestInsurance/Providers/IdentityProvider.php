<?php

namespace Cego\RequestInsurance\Providers;

class IdentityProvider
{
    /**
     * @param $request
     * @return string|null
     */
    public function getUser($request) : ?string
    {
        return null;
    }
}
