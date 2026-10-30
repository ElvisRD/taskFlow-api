<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\StoreInvitationRequest;
use App\Http\Resources\WorkspaceInvitationResource;
use App\Http\Resources\WorkspaceMemberResource;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Notifications\WorkspaceInvitationNotification;
use App\WorkspaceInvitationStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

class WorkspaceInvitationController extends Controller
{
    public function index(Workspace $workspace): AnonymousResourceCollection
    {
        Gate::authorize('manageMembers', $workspace);

        return WorkspaceInvitationResource::collection(
            $workspace->invitations()
                ->with(['inviter', 'acceptedBy'])
                ->latest()
                ->get(),
        );
    }

    public function store(StoreInvitationRequest $request, Workspace $workspace): JsonResponse
    {
        $token = WorkspaceInvitation::generateToken();

        $invitation = $workspace->invitations()->create([
            'invited_by' => $request->user()->getKey(),
            'email' => $request->normalizedEmail(),
            'role' => $request->string('role')->toString(),
            'token' => WorkspaceInvitation::hashToken($token),
            'status' => WorkspaceInvitationStatus::Pending,
            'expires_at' => now()->addMinutes((int) config('auth.invitations.expire', 10080)),
        ]);

        $this->sendInvitation($invitation, $token);

        return (new WorkspaceInvitationResource($invitation->load(['inviter', 'acceptedBy'])))
            ->response()
            ->setStatusCode(201);
    }

    public function resend(Workspace $workspace, WorkspaceInvitation $invitation): WorkspaceInvitationResource
    {
        Gate::authorize('manageMembers', $workspace);

        $this->ensureBelongsToWorkspace($workspace, $invitation);

        if ($invitation->effectiveStatus() === WorkspaceInvitationStatus::Accepted) {
            abort(422, 'La invitación ya fue aceptada.');
        }

        if ($invitation->effectiveStatus() === WorkspaceInvitationStatus::Revoked) {
            abort(422, 'La invitación fue revocada.');
        }

        $member = User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($invitation->email)])
            ->first();

        if ($member !== null && $workspace->hasMember($member)) {
            abort(422, 'El usuario ya pertenece a este workspace.');
        }

        $token = $invitation->regenerateToken();

        $this->sendInvitation($invitation, $token);

        return new WorkspaceInvitationResource($invitation->load(['inviter', 'acceptedBy']));
    }

    public function destroy(Workspace $workspace, WorkspaceInvitation $invitation): Response
    {
        Gate::authorize('manageMembers', $workspace);

        $this->ensureBelongsToWorkspace($workspace, $invitation);

        if ($invitation->effectiveStatus() === WorkspaceInvitationStatus::Accepted) {
            abort(422, 'La invitación ya fue aceptada.');
        }

        if ($invitation->effectiveStatus() !== WorkspaceInvitationStatus::Revoked) {
            $invitation->markRevoked();
        }

        return response()->noContent();
    }

    public function accept(Request $request, string $token): WorkspaceMemberResource|JsonResponse
    {
        $invitation = WorkspaceInvitation::query()
            ->where('token', WorkspaceInvitation::hashToken($token))
            ->first();

        if ($invitation === null) {
            abort(404, 'La invitación no existe.');
        }

        $status = $invitation->effectiveStatus();

        if ($status === WorkspaceInvitationStatus::Accepted) {
            abort(409, 'La invitación ya fue aceptada.');
        }

        if ($status === WorkspaceInvitationStatus::Revoked) {
            abort(410, 'La invitación fue revocada.');
        }

        if ($status === WorkspaceInvitationStatus::Expired) {
            $invitation->markExpired();

            abort(410, 'La invitación ha expirado.');
        }

        $user = $request->user();

        if (! hash_equals(strtolower($invitation->email), strtolower($user->email))) {
            abort(403, 'Esta invitación no corresponde a tu correo electrónico.');
        }

        $workspace = $invitation->workspace;

        DB::transaction(function () use ($invitation, $workspace, $user): void {
            if (! $workspace->hasMember($user)) {
                $workspace->members()->syncWithoutDetaching([
                    $user->getKey() => ['role' => $invitation->role->value],
                ]);
            }

            $invitation->markAccepted($user);
        });

        return (new WorkspaceMemberResource($this->member($workspace, $user)))
            ->response()
            ->setStatusCode(200);
    }

    private function sendInvitation(WorkspaceInvitation $invitation, string $token): void
    {
        Notification::route('mail', $invitation->email)
            ->notify(new WorkspaceInvitationNotification($invitation, $token));
    }

    private function ensureBelongsToWorkspace(Workspace $workspace, WorkspaceInvitation $invitation): void
    {
        abort_unless(
            (int) $invitation->workspace_id === (int) $workspace->getKey(),
            404,
        );
    }

    private function member(Workspace $workspace, User $user): User
    {
        return $workspace->members()->whereKey($user->getKey())->firstOrFail();
    }
}
