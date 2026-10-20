<?php

namespace App\Notifications\Concerns;

trait IncludesTaskContext
{
    /**
     * Board and workspace identifiers so clients can navigate to the task.
     *
     * @return array{board_id?: int, workspace_id?: int}
     */
    protected function taskContext(): array
    {
        $list = $this->task->list;

        if ($list === null) {
            return [];
        }

        return array_filter([
            'board_id' => $list->board_id,
            'workspace_id' => $list->board?->workspace_id,
        ], fn (?int $value): bool => $value !== null);
    }
}
