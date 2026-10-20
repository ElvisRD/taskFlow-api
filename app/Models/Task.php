<?php

namespace App\Models;

use App\TaskPriority;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'board_list_id',
    'created_by',
    'assigned_to',
    'title',
    'description',
    'position',
    'priority',
    'due_date',
    'completed_at',
])]
class Task extends Model
{
    use HasFactory;

    public function list(): BelongsTo
    {
        return $this->belongsTo(BoardList::class, 'board_list_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->latest();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class)->latest();
    }

    public function board(): Board
    {
        return $this->list->board;
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    #[Scope]
    protected function search(Builder $query, string $term): Builder
    {
        $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(function (Builder $query) use ($term): void {
            $query->where('title', 'like', $term)
                ->orWhere('description', 'like', $term);
        });
    }

    #[Scope]
    protected function assignedTo(Builder $query, User $user): Builder
    {
        return $query->where('assigned_to', $user->getKey());
    }

    #[Scope]
    protected function priority(Builder $query, TaskPriority $priority): Builder
    {
        return $query->where('priority', $priority->value);
    }

    #[Scope]
    protected function completed(Builder $query, bool $completed = true): Builder
    {
        return $completed
            ? $query->whereNotNull('completed_at')
            : $query->whereNull('completed_at');
    }

    #[Scope]
    protected function overdue(Builder $query): Builder
    {
        return $query->whereNull('completed_at')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString());
    }

    #[Scope]
    protected function orderByPriority(Builder $query, string $direction = 'asc'): Builder
    {
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        $cases = collect(TaskPriority::cases())
            ->map(fn (TaskPriority $priority): string => "WHEN '{$priority->value}' THEN {$priority->weight()}")
            ->implode(' ');

        return $query->orderByRaw("CASE priority {$cases} ELSE 0 END {$direction}");
    }

    protected function casts(): array
    {
        return [
            'priority' => TaskPriority::class,
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }
}
