<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EmailProviderController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// routes/web.php

Route::get('/migrate', function () {
    \Artisan::call('migrate:fresh', [
        '--force' => true
    ]);
    return 'Migrations done!';
});

Route::middleware('web')->group(function () {
    Route::get('/api/email-provider/{provider}/redirect', [EmailProviderController::class, 'redirect']);
Route::get('/email-provider/{provider}/callback', [EmailProviderController::class, 'callback']);

});

// routes/web.php
Route::get('/debug-google-connection/{userId?}', function($userId = null) {
    if (!$userId) {
        // Get first user with Google connection
        $provider = \App\Models\EmailProvider::where('provider', 'google')->first();
        $userId = $provider ? $provider->user_id : 1;
    }
    
    $provider = \App\Models\EmailProvider::where('user_id', $userId)
        ->where('provider', 'google')
        ->first();
    
    if (!$provider) {
        return response()->json(['error' => 'No Google provider found']);
    }
    
    // Check token expiration
    $expiresAt = $provider->expires_at;
    $isExpired = false;
    $minutesLeft = 0;
    
    if ($expiresAt) {
        try {
            $carbonDate = $expiresAt instanceof \Carbon\Carbon 
                ? $expiresAt 
                : \Carbon\Carbon::parse($expiresAt);
            $isExpired = $carbonDate->isPast();
            $minutesLeft = now()->diffInMinutes($carbonDate, false);
        } catch (\Exception $e) {
            $isExpired = 'Error parsing date: ' . $e->getMessage();
        }
    }
    
    return response()->json([
        'database_record' => [
            'id' => $provider->id,
            'user_id' => $provider->user_id,
            'provider' => $provider->provider,
            'provider_email' => $provider->provider_email,
            'connected' => $provider->connected,
            'access_token_exists' => !empty($provider->access_token),
            'access_token_preview' => $provider->access_token ? substr($provider->access_token, 0, 50) . '...' : null,
            'refresh_token_exists' => !empty($provider->refresh_token),
            'refresh_token_preview' => $provider->refresh_token ? substr($provider->refresh_token, 0, 50) . '...' : null,
            'expires_at' => $provider->expires_at,
            'expires_at_type' => gettype($provider->expires_at),
            'is_expired' => $isExpired,
            'minutes_until_expiry' => $minutesLeft,
            'created_at' => $provider->created_at,
            'updated_at' => $provider->updated_at,
        ],
        'config_check' => [
            'google_client_id' => config('services.google.client_id'),
            'google_client_secret' => config('services.google.client_secret') ? 'Set (hidden)' : 'Not set',
            'google_redirect' => config('services.google.redirect'),
        ],
    ]);
});

// routes/web.php
Route::get('/test-token-refresh/{userId?}', function($userId = null) {
    if (!$userId) {
        $provider = \App\Models\EmailProvider::where('provider', 'google')->first();
        $userId = $provider ? $provider->user_id : 1;
    }
    
    $googleService = new \App\Services\GoogleService();
    
    \Log::info('Manual token refresh test for user: ' . $userId);
    
    try {
        // Try to refresh
        $result = $googleService->refreshTokenIfNeeded($userId);
        
        // Get updated provider
        $provider = \App\Models\EmailProvider::where('user_id', $userId)
            ->where('provider', 'google')
            ->first();
        
        return response()->json([
            'refresh_success' => $result,
            'updated_record' => $provider ? [
                'access_token_preview' => $provider->access_token ? substr($provider->access_token, 0, 30) . '...' : null,
                'expires_at' => $provider->expires_at,
                'updated_at' => $provider->updated_at,
            ] : null,
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
});


Route::get('/test-send-email', function() {
    try {
        // Get the user with Google connection (assuming user ID 1)
        $userId = 1;
        $toEmail = 'naveboot@gmail.com';
        
        $googleService = new \App\Services\GoogleService();
        
        \Log::info('=== TESTING EMAIL SEND ===', [
            'user_id' => $userId,
            'to_email' => $toEmail
        ]);
        
        // Send test email
        $result = $googleService->sendTestEmail($userId, $toEmail);
        
        \Log::info('Email send result:', $result);
        
        return response()->json([
            'success' => $result['success'] ?? false,
            'message' => $result['message'] ?? 'Unknown result',
            'debug' => [
                'user_id' => $userId,
                'to_email' => $toEmail,
                'timestamp' => now()->toDateTimeString(),
            ]
        ]);
        
    } catch (\Exception $e) {
        \Log::error('Test email failed:', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage(),
            'error_details' => $e->getTraceAsString()
        ]);
    }
});


//anas 1st email