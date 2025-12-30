<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrganisationController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\OpportunityController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Api\NotificationsController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\EmailCampaignController;
use App\Http\Controllers\EmailProviderController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/



Route::middleware(['auth:sanctum', 'web'])->group(function () {
    Route::prefix('email-provider')->group(function () {
        Route::get('/{provider}/redirect', [EmailProviderController::class, 'redirect']);
        // Keep callback in web routes for redirect
    });
});


// 🔓 Public routes
Route::post('signup', [AuthController::class, 'signup']);
Route::post('login', [AuthController::class, 'login']);

// 🔐 Protected routes (require Sanctum token)
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::get('/user', fn(Request $request) => $request->user());

    // User Email Provider Connection
    Route::get('/user/email-provider', [UserController::class, 'getEmailProvider']);
    Route::delete('/user/email-provider', [UserController::class, 'deleteEmailProvider']);

    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    // Organisations / Users
    Route::get('/organisation/users', [UserController::class, 'getOrganisationUsers']);
    Route::get('organisations/{organisation}/users', [UserController::class, 'getByOrganisation']);
    Route::post('/organisations/{organisation}/add-user-by-email', [UserController::class, 'addUserByEmail']);
    Route::post('/users/add-to-organisation', [UserController::class, 'addUserToOrganisation']);
    Route::put('users/{user}/assign-organisation', [UserController::class, 'assignOrganisation']);
    Route::apiResource('users', UserController::class)->except(['create', 'edit']);

    // Invitations
    Route::post('/organisations/{organisation}/invite', [InvitationController::class, 'sendInvite']);
    Route::post('/invitations/accept', [InvitationController::class, 'acceptInvite']);

    // Tasks
    Route::get('organisations/{organisation}/tasks', [TaskController::class, 'getByOrganisation']);
    Route::get('users/{user}/tasks', [TaskController::class, 'getByAssignee']);
    Route::apiResource('tasks', TaskController::class)->except(['create', 'edit']);

    // Opportunities
    Route::get('/organisations/{organisation}/opportunities', [OpportunityController::class, 'getByOrganisation']);
    Route::get('/users/{user}/opportunities', [OpportunityController::class, 'getByCreator']);
    Route::apiResource('opportunities', OpportunityController::class)->except(['create', 'edit']);

    // Contacts
    Route::get('organisations/{organisation}/contacts', [ContactController::class, 'getByOrganisation']);
    Route::apiResource('contacts', ContactController::class)->except(['create', 'edit']);

    // Organisations
    Route::apiResource('organisations', OrganisationController::class)->except(['create', 'edit']);

    // Appointments
    Route::apiResource('appointments', AppointmentController::class)->except(['create', 'edit']);
    Route::get('/users/{user}/appointments', fn(\App\Models\User $user) => response()->json($user->appointments));



Route::middleware('auth:sanctum')->group(function () {
    Route::get('notifications', [NotificationsController::class, 'index']);
    Route::get('notifications/unread-count', [NotificationsController::class, 'unreadCount']);
    Route::post('notifications/{id}/read', [NotificationsController::class, 'markAsRead']);
    Route::post('notifications/mark-all-read', [NotificationsController::class, 'markAllRead']);
});


Route::middleware('auth:sanctum')->group(function () {
    Route::get('/leads', [LeadController::class, 'index']);
    Route::post('/leads', [LeadController::class, 'store']);
    Route::get('/leads/{lead}', [LeadController::class, 'show']);
    Route::put('/leads/{lead}', [LeadController::class, 'update']);
    Route::delete('/leads/{lead}', [LeadController::class, 'destroy']);
    Route::get('/organisations/{organisation}/leads', [LeadController::class, 'getByOrganisation']);
    Route::post('/leads/filter', [LeadController::class, 'filter']);

// To this:
Route::patch('/leads/{lead}/treated', [LeadController::class, 'markAsTreated']);
Route::patch('/leads/{lead}/untreated', [LeadController::class, 'markAsUntreated']);


});

Route::middleware('auth:sanctum')->group(function () {
    // Add these to your existing email-provider routes
    Route::prefix('email-provider')->group(function () {
        Route::get('/test-connection', [EmailProviderController::class, 'testConnection']);
        Route::post('/send-test-email', [EmailProviderController::class, 'sendTestEmail']);
        Route::post('/refresh-token', [EmailProviderController::class, 'refreshToken']);
    });
});

    Route::post('/email-campaigns', [EmailCampaignController::class, 'store']);


Route::middleware('auth:sanctum')->group(function () {    
    // Google OAuth routes
    Route::get('/email-providers/{provider}/redirect', [EmailProviderController::class, 'redirect']);
    Route::get('/email-providers/{provider}/callback', [EmailProviderController::class, 'callback']);
    Route::post('/email-providers/{provider}/disconnect', [EmailProviderController::class, 'disconnect']);
    Route::get('/email-providers/{provider}/status', [EmailProviderController::class, 'getConnectionStatus']);
    // Test routes
    Route::get('/email-providers/test-connection', [EmailProviderController::class, 'testConnection']);
    Route::post('/email-providers/send-test-email', [EmailProviderController::class, 'sendTestEmail']);
    Route::post('/email-providers/refresh-token', [EmailProviderController::class, 'refreshToken']);
});


});
