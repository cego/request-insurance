<?php

namespace Cego\RequestInsurance\Providers;

use Illuminate\Http\Request;

class CegoIdentityProvider implements IdentityProvider
{
    /**
     * Returns the username of the authenticated user, falling back to the remote-user header
     * set by an authenticating proxy
     *
     * @param Request $request
     *
     * @return string
     */
    public function getUser(Request $request): string
    {
        $username = (string) data_get($request->user(), 'username', '');

        if ($username !== '') {
            return $username;
        }

        return (string) $request->header('remote-user', '');
    }
}
