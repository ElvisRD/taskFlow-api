<?php

namespace App;

enum WorkspaceRole: string
{
    case Admin = 'admin';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Member => 'Miembro',
        };
    }

    public function managesWorkspace(): bool
    {
        return $this === self::Admin;
    }
}
