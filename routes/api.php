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

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/organisation/users', [UserController::class, 'getOrganisationUsers']);
});

Route::middleware('auth:sanctum')->get('/dashboard/stats', [DashboardController::class, 'stats']);
Route::get('/opportunities', [OpportunityController::class, 'index']);
Route::post('/opportunities', [OpportunityController::class, 'store']);
Route::get('/opportunities/{opportunity}', [OpportunityController::class, 'show']);
Route::put('/opportunities/{opportunity}', [OpportunityController::class, 'update']);
Route::delete('/opportunities/{opportunity}', [OpportunityController::class, 'destroy']);
Route::get('/organisations/{organisation}/opportunities', [OpportunityController::class, 'getByOrganisation']);
Route::get('/users/{user}/opportunities', [OpportunityController::class, 'getByCreator']);

Route::post('signup', [AuthController::class, 'signup']);
Route::post('login', [AuthController::class, 'login']);
Route::post('/auth/refresh', [AuthController::class, 'refresh']);

Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('organisations/{organisation}/tasks', [TaskController::class, 'getByOrganisation']);
Route::get('users/{user}/tasks', [TaskController::class, 'getByAssignee']);
Route::get('organisations/{organisation}/contacts', [ContactController::class, 'getByOrganisation']);
Route::get('organisations/{organisation}/users', [UserController::class, 'getByOrganisation']);
Route::get('tasks/{task}', [TaskController::class, 'show']);
Route::put('users/{user}/assign-organisation', [UserController::class, 'assignOrganisation']);

Route::post('/users', [UserController::class, 'store']);        
Route::put('/users/{user}', [UserController::class, 'update']); 
Route::delete('/users/{user}', [UserController::class, 'destroy']); 


Route::apiResource('appointments', AppointmentController::class);

Route::post('/organisations/{organisation}/add-user-by-email', [UserController::class, 'addUserByEmail']);

Route::post('/users/add-to-organisation', [UserController::class, 'addUserToOrganisation']);
Route::post('/organisations/{organisation}/invite', [InvitationController::class, 'sendInvite']);
Route::post('/invitations/accept', [InvitationController::class, 'acceptInvite']);

Route::apiResource('tasks', TaskController::class);
Route::apiResource('contacts', ContactController::class);
Route::apiResource('organisations', OrganisationController::class);
Route::apiResource('users', UserController::class);
//->middleware('auth:sanctum');

Route::get('/users/{user}/appointments', function (\App\Models\User $user) {
    return response()->json($user->appointments);
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


Route::middleware('auth:api')->group(function () {
});


Route::middleware('auth:sanctum')->get('/auth/me', [AuthController::class, 'me']);
