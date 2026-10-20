<?php

namespace App\Auth;

interface GoogleTokenVerifier
{
    /**
     * Verify a Google ID token and return the identity it belongs to.
     *
     * @throws InvalidGoogleIdentityToken
     */
    public function verify(string $idToken): GoogleIdentity;
}
