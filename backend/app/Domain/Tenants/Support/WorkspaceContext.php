<?php

namespace App\Domain\Tenants\Support;

use App\Models\Workspace;

class WorkspaceContext
{
    private ?Workspace $workspace = null;

    public function setWorkspace(?Workspace $workspace): void
    {
        $this->workspace = $workspace;
    }

    public function getWorkspace(): ?Workspace
    {
        return $this->workspace;
    }

    public function getWorkspaceId(): ?string
    {
        return $this->workspace?->id;
    }
}
