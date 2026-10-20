<?php

namespace App\Auth;

/**
 * Identidad de una cuenta de Google ya verificada.
 */
final readonly class GoogleIdentity
{
    public function __construct(
        public string $id,
        public string $email,
        public string $name,
        public ?string $avatar = null,
    ) {}
}
