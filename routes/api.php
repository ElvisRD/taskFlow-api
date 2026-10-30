<?php

use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\GoogleLoginController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutAllController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\Auth\SendVerificationEmailController;
use App\Http\Controllers\Api\V1\Auth\TokenController;
use App\Http\Controllers\Api\V1\Auth\UpdatePasswordController;
use App\Http\Controllers\Api\V1\Auth\VerifyEmailController;
use App\Http\Controllers\Api\V1\BoardController;
use App\Http\Controllers\Api\V1\BoardListController;
use App\Http\Controllers\Api\V1\CommentController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\NotificationMuteController;
use App\Http\Controllers\Api\V1\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\TaskActivityController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\WorkspaceController;
use App\Http\Controllers\Api\V1\WorkspaceInvitationController;
use App\Http\Controllers\Api\V1\WorkspaceMemberController;
use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('auth/register', RegisterController::class)
        ->middleware('throttle:register')
        ->name('auth.register');
    Route::post('auth/login', LoginController::class)
        ->middleware('throttle:login')
        ->name('auth.login');
    Route::post('auth/google', GoogleLoginController::class)
        ->middleware('throttle:google-login')
        ->name('auth.google');
    Route::post('auth/forgot-password', ForgotPasswordController::class)
        ->middleware('throttle:password-email')
        ->name('auth.password.email');
    Route::post('auth/reset-password', ResetPasswordController::class)
        ->middleware('throttle:password-reset')
        ->name('auth.password.reset');
    Route::get('auth/email/verify/{id}/{hash}', VerifyEmailController::class)
        ->name('verification.verify');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('auth/logout', LogoutController::class)->name('auth.logout');
        Route::post('auth/logout-all', LogoutAllController::class)->name('auth.logout-all');
        Route::get('auth/me', MeController::class)->name('auth.me');
        Route::put('auth/password', UpdatePasswordController::class)->name('auth.password.update');
        Route::post('auth/email/verification-notification', SendVerificationEmailController::class)
            ->middleware('throttle:verification-email')
            ->name('verification.send');

        Route::get('auth/tokens', [TokenController::class, 'index'])->name('auth.tokens.index');
        Route::delete('auth/tokens/{token}', [TokenController::class, 'destroy'])->name('auth.tokens.destroy');
        Route::delete('auth/tokens', [TokenController::class, 'destroyOthers'])->name('auth.tokens.destroy-others');

        Route::middleware('verified')->group(function (): void {
            Route::post('broadcasting/auth', [BroadcastController::class, 'authenticate'])
                ->name('broadcasting.auth');

            Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
            Route::get('notifications/preferences', [NotificationPreferenceController::class, 'index'])
                ->name('notifications.preferences.index');
            Route::patch('notifications/preferences/{type}', [NotificationPreferenceController::class, 'update'])
                ->name('notifications.preferences.update');
            Route::post('notifications/mutes', [NotificationMuteController::class, 'store'])
                ->name('notifications.mutes.store');
            Route::delete('notifications/mutes/{mute}', [NotificationMuteController::class, 'destroy'])
                ->name('notifications.mutes.destroy');
            Route::patch('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
            Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

            Route::apiResource('workspaces', WorkspaceController::class);

            Route::get('workspaces/{workspace}/members', [WorkspaceMemberController::class, 'index'])
                ->name('workspaces.members.index');
            Route::post('workspaces/{workspace}/members', [WorkspaceMemberController::class, 'store'])
                ->name('workspaces.members.store');
            Route::patch('workspaces/{workspace}/members/{user}', [WorkspaceMemberController::class, 'update'])
                ->name('workspaces.members.update');
            Route::delete('workspaces/{workspace}/members/{user}', [WorkspaceMemberController::class, 'destroy'])
                ->name('workspaces.members.destroy');

            Route::get('workspaces/{workspace}/invitations', [WorkspaceInvitationController::class, 'index'])
                ->name('workspaces.invitations.index');
            Route::post('workspaces/{workspace}/invitations', [WorkspaceInvitationController::class, 'store'])
                ->name('workspaces.invitations.store');
            Route::post('workspaces/{workspace}/invitations/{invitation}/resend', [WorkspaceInvitationController::class, 'resend'])
                ->name('workspaces.invitations.resend');
            Route::delete('workspaces/{workspace}/invitations/{invitation}', [WorkspaceInvitationController::class, 'destroy'])
                ->name('workspaces.invitations.destroy');
            Route::post('workspace-invitations/{token}/accept', [WorkspaceInvitationController::class, 'accept'])
                ->name('workspace-invitations.accept');

            Route::patch('workspaces/{workspace}/boards/reorder', [BoardController::class, 'reorder'])
                ->name('workspaces.boards.reorder');

            Route::apiResource('workspaces.boards', BoardController::class)
                ->scoped()
                ->only(['index', 'store', 'show', 'update', 'destroy']);

            Route::patch('boards/{board}/lists/reorder', [BoardListController::class, 'reorder'])
                ->name('boards.lists.reorder');

            Route::apiResource('boards.lists', BoardListController::class)
                ->scoped()
                ->only(['store', 'update', 'destroy']);

            Route::get('boards/{board}/tasks', [TaskController::class, 'index'])->name('boards.tasks.index');
            Route::post('lists/{list}/tasks', [TaskController::class, 'store'])->name('lists.tasks.store');
            Route::get('tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
            Route::patch('tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
            Route::patch('tasks/{task}/move', [TaskController::class, 'move'])->name('tasks.move');
            Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');

            Route::get('tasks/{task}/activities', [TaskActivityController::class, 'index'])->name('tasks.activities.index');

            Route::get('tasks/{task}/comments', [CommentController::class, 'index'])->name('tasks.comments.index');
            Route::post('tasks/{task}/comments', [CommentController::class, 'store'])->name('tasks.comments.store');
            Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

            Route::get('tasks/{task}/attachments', [AttachmentController::class, 'index'])->name('tasks.attachments.index');
            Route::post('tasks/{task}/attachments', [AttachmentController::class, 'store'])->name('tasks.attachments.store');
            Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('attachments.download');
            Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');
        });
    });
});
