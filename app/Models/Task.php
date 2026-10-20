<?php

namespace App\Models;

use App\TaskPriority;
use Database\Factories\TaskFactory;
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
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    /**
     * Get the list that owns the task.
     *
     * @return BelongsTo<BoardList, $this>
     */
    public function list(): BelongsTo
    {
        return $this->belongsTo(BoardList::class, 'board_list_id');
    }

    /**
     * Get the user that created the task.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user the task is assigned to.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the comments that belong to the task.
     *
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->latest();
    }

    /**
     * Get the attachments that belong to the task.
     *
     * @return HasMany<Attachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class)->latest();
    }

    /**
     * Get the board the task belongs to through its list.
     */
    public function board(): Board
    {
        return $this->list->board;
    }

    /**
     * Determine whether the task is completed.
     */
    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * Limit the query to tasks that match the given search term.
     */
    #[Scope]
    protected function search(Builder $query, string $term): Builder
    {
        $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(function (Builder $query) use ($term): void {
            $query->where('title', 'like', $term)
                ->orWhere('description', 'like', $term);
        });
    }

    /**
     * Limit the query to the tasks assigned to the given user.
     */
    #[Scope]
    protected function assignedTo(Builder $query, User $user): Builder
    {
        return $query->where('assigned_to', $user->getKey());
    }

    /**
     * Limit the query to the tasks with the given priority.
     */
    #[Scope]
    protected function priority(Builder $query, TaskPriority $priority): Builder
    {
        return $query->where('priority', $priority->value);
    }

    /**
     * Limit the query to completed or pending tasks.
     */
    #[Scope]
    protected function completed(Builder $query, bool $completed = true): Builder
    {
        return $completed
            ? $query->whereNotNull('completed_at')
            : $query->whereNull('completed_at');
    }

    /**
     * Limit the query to tasks whose due date is in the past.
     */
    #[Scope]
    protected function overdue(Builder $query): Builder
    {
        return $query->whereNull('completed_at')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString());
    }

    /**
     * Order the query by the business weight of each priority.
     */
    #[Scope]
    protected function orderByPriority(Builder $query, string $direction = 'asc'): Builder
    {
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        $cases = collect(TaskPriority::cases())
            ->map(fn (TaskPriority $priority): string => "WHEN '{$priority->value}' THEN {$priority->weight()}")
            ->implode(' ');

        return $query->orderByRaw("CASE priority {$cases} ELSE 0 END {$direction}");
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => TaskPriority::class,
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }
}
