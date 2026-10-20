<?php

namespace App;

enum WorkspaceRole: string
{
    case Admin = 'admin';
    case Member = 'member';

    /**
     * Get the human readable label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Member => 'Miembro',
        };
    }

    /**
     * Determine whether the role can manage the workspace settings and content.
     */
    public function managesWorkspace(): bool
    {
        return $this === self::Admin;
    }
}
