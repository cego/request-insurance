<?php

namespace Cego\RequestInsurance\Providers;

use Illuminate\Http\Request;

interface IdentityProvider
{
    /**
     * @param Request $request
     *
     * @return string
     */
    public function getUser(Request $request): string;
}
