<?php

namespace App\Auth;

interface GoogleTokenVerifier
{
    public function verify(string $idToken): GoogleIdentity;
}
