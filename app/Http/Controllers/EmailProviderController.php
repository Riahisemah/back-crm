<?php

namespace App\Http\Controllers;

use App\Models\EmailProvider;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use App\Services\GoogleService;


class EmailProviderController extends Controller
{
    // Add this method to disconnect from Google OAuth
  public function disconnect(string $provider, Request $request)
{
    try {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
        
        \Log::info('=== GOOGLE OAUTH DISCONNECT STARTED ===', [
            'user_id' => $user->id,
            'provider' => $provider,
        ]);
        
        // Find the email provider record
        $emailProvider = EmailProvider::where('user_id', $user->id)
            ->where('provider', $provider)
            ->first();
            
        if (!$emailProvider) {
            \Log::warning('No email provider found to disconnect', [
                'user_id' => $user->id,
                'provider' => $provider,
            ]);
            return response()->json([
                'success' => true, // Already disconnected
                'message' => 'Already disconnected'
            ]);
        }
        
        \Log::info('Found email provider to disconnect:', [
            'provider_id' => $emailProvider->id,
            'provider_email' => $emailProvider->provider_email,
            'access_token_exists' => !empty($emailProvider->access_token),
        ]);
        
        // Revoke the token with Google API
        $accessToken = $emailProvider->access_token;
        if ($accessToken) {
            try {
                // Call Google OAuth revoke endpoint
                $client = new \GuzzleHttp\Client([
                    'timeout' => 10,
                    'verify' => false, // Only for local development
                ]);
                
                $response = $client->request('POST', 'https://oauth2.googleapis.com/revoke', [
                    'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                    'form_params' => [
                        'token' => $accessToken
                    ],
                ]);
                
                \Log::info('Google token revoked successfully', [
                    'status_code' => $response->getStatusCode(),
                ]);
            } catch (\Exception $e) {
                \Log::warning('Failed to revoke Google token, but continuing with local disconnect', [
                    'error' => $e->getMessage(),
                ]);
                // Continue with local disconnect even if token revocation fails
            }
        }
        
        // Store the email for logging purposes before deleting
        $providerEmail = $emailProvider->provider_email;
        
        // Delete the email provider record
        $deleted = $emailProvider->delete();
        
        if ($deleted) {
            \Log::info('Successfully disconnected from Google OAuth', [
                'user_id' => $user->id,
                'provider' => $provider,
                'provider_email' => $providerEmail,
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Successfully disconnected from Google OAuth'
            ]);
        } else {
            \Log::error('Failed to delete email provider record', [
                'user_id' => $user->id,
                'provider' => $provider,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to disconnect'
            ], 500);
        }
        
    } catch (\Exception $e) {
        \Log::error('=== GOOGLE OAUTH DISCONNECT FAILED ===', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Failed to disconnect: ' . $e->getMessage()
        ], 500);
    }
}

    // Add this method to get connection status
public function getConnectionStatus(string $provider, Request $request)
{
    $user = $request->user();

    if (!$user) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    $googleService = new GoogleService();
    $result = $googleService->checkConnection($user->id);

    \Log::info('Connection status result:', [
        'result_keys' => array_keys($result),
        'has_email_key' => isset($result['email']),
        'email_value' => $result['email'] ?? 'NOT SET',
        'has_provider_email_key' => isset($result['provider_email']),
    ]);

    // Use 'email' from the result, not 'provider_email'
    return response()->json([
        'connected' => $result['connected'],
        'provider_email' => $result['email'] ?? null,  // Changed from 'provider_email' to 'email'
        'expires_at' => $result['expires_at'] ?? null,
        'provider' => $provider,
    ]);
}


    // Add this method to test the connection
    public function testConnection(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        
        $googleService = new GoogleService();
        $result = $googleService->checkConnection($user->id);
        
        return response()->json([
            'success' => $result['connected'],
            'data' => $result
        ]);
    }

    // Add this method to send a test email
    public function sendTestEmail(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        
        $request->validate([
            'to_email' => 'nullable|email'
        ]);
        
        $googleService = new GoogleService();
        $result = $googleService->sendTestEmail($user->id, $request->to_email);
        
        return response()->json($result);
    }

    // Add this method to refresh token manually
    public function refreshToken(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        
        $googleService = new GoogleService();
        $success = $googleService->refreshTokenIfNeeded($user->id);
        
        return response()->json([
            'success' => $success,
            'message' => $success ? 'Token refreshed successfully' : 'Failed to refresh token'
        ]);
    }

    /**
     * Redirect the user to the provider's authentication page.
     *
     * @param string $provider
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function redirect(string $provider, Request $request)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Create state with token
        $state = urlencode($token);
        
        // Get the Google driver
        $driver = Socialite::driver($provider);
        
        // IMPORTANT: Add ALL required scopes
        $url = $driver
            ->scopes([
                'https://www.googleapis.com/auth/gmail.send',
                'https://www.googleapis.com/auth/gmail.readonly',
                'https://www.googleapis.com/auth/userinfo.email',
                'https://www.googleapis.com/auth/userinfo.profile',
                'openid',
            ])
            ->stateless()
            ->with([
                'access_type' => 'offline',
                'prompt' => 'consent',
                'state' => $state
            ])
            ->redirect()
            ->getTargetUrl();

        \Log::info('Google OAuth redirect URL generated', [
            'has_access_type_offline' => strpos($url, 'access_type=offline') !== false,
            'has_prompt_consent' => strpos($url, 'prompt=consent') !== false,
            'scopes_in_url' => true,
            'url_preview' => substr($url, 0, 200) . '...',
        ]);

        return response()->json(['url' => $url]);
    }

    /**
     * Handle the OAuth callback from Google.
     *
     * @param string $provider
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function callback(string $provider, Request $request)
{
    try {
        // Log incoming request parameters
        \Log::info('=== GOOGLE OAUTH CALLBACK STARTED ===');
        \Log::info('Callback URL: ' . $request->fullUrl());
        \Log::info('Provider: ' . $provider);

        // Get user from Google
        \Log::info('Attempting to get user from Socialite...');
        $providerUser = Socialite::driver($provider)->stateless()->user();
        
        // Log Socialite response
        \Log::info('=== SOCIALITE RESPONSE ===');
        \Log::info('Google User Data:', [
            'has_token' => !empty($providerUser->token) ? 'Yes (' . strlen($providerUser->token) . ' chars)' : 'No',
            'has_refresh_token' => !empty($providerUser->refreshToken) ? 'Yes (' . strlen($providerUser->refreshToken) . ' chars)' : 'No',
            'expires_in' => $providerUser->expiresIn,
            'expires_at_calculated' => now()->addSeconds($providerUser->expiresIn)->toDateTimeString(),
            'email' => $providerUser->getEmail(),
            'name' => $providerUser->getName(),
        ]);

        // Extract token from state
        $token = urldecode($request->state) ?? null;
        \Log::info('=== TOKEN PARSING ===');
        \Log::info('State token: ' . ($token ? substr($token, 0, 50) . '...' : 'NULL'));
        
        if (!$token) {
            \Log::error('Missing state token');
            abort(401, 'Missing state token');
        }

        // Parse token
        $tokenParts = explode('|', $token);
        \Log::info('Token parsed into ' . count($tokenParts) . ' parts');
        
        // Find user by token
        \Log::info('=== FINDING USER BY TOKEN ===');
        $user = \App\Models\User::whereHas('tokens', function($query) use ($tokenParts) {
            if (count($tokenParts) === 2) {
                $tokenId = $tokenParts[0];
                \Log::info('Searching by token ID: ' . $tokenId);
                $query->where('id', $tokenId);
            } else {
                \Log::info('Searching by hashed token');
                $query->where('token', hash('sha256', $token));
            }
        })->first();
        
        if (!$user) {
            \Log::error('User not found for token', [
                'token_preview' => substr($token, 0, 100),
                'token_parts_count' => count($tokenParts),
            ]);
            
            abort(401, 'Invalid token');
        }
        
        \Log::info('User found:', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_name' => $user->name,
        ]);

        // Store or update the email provider
        \Log::info('=== STORING/UPDATING EMAIL PROVIDER ===');

        // Create data array that matches your database schema
        $dataToSave = [
            'user_id' => $user->id,
            'provider' => $provider,
            'access_token' => $providerUser->token,
            'refresh_token' => $providerUser->refreshToken,
            'expires_at' => now()->addSeconds($providerUser->expiresIn),
            'provider_email' => $providerUser->getEmail(),
            'connected' => true,
        ];

        \Log::info('Data to save (matching DB schema):', [
            'fields' => array_keys($dataToSave),
            'provider_email' => $dataToSave['provider_email'],
            'has_access_token' => !empty($dataToSave['access_token']),
            'has_refresh_token' => !empty($dataToSave['refresh_token']),
        ]);

        // Get the email provider first
        $emailProvider = EmailProvider::where('user_id', $user->id)
            ->where('provider', $provider)
            ->first();

        if ($emailProvider) {
            // Update existing record
            $emailProvider->fill($dataToSave);
            $saved = $emailProvider->save();
            
            \Log::info('Updated existing provider', [
                'saved' => $saved,
                'provider_id' => $emailProvider->id,
                'changes' => $emailProvider->getChanges(),
            ]);
        } else {
            // Create new record - ONLY with columns that exist in your DB
            $emailProvider = EmailProvider::create($dataToSave);
            
            \Log::info('Created new provider', [
                'id' => $emailProvider->id,
                'was_saved' => !is_null($emailProvider->id),
            ]);
        }
                
        \Log::info('Email provider saved successfully:', [
            'provider_id' => $emailProvider->id,
            'user_id' => $emailProvider->user_id,
            'provider' => $emailProvider->provider,
            'provider_email' => $emailProvider->provider_email,
            'access_token_set' => !empty($emailProvider->access_token) ? 'Yes' : 'No',
            'refresh_token_set' => !empty($emailProvider->refresh_token) ? 'Yes' : 'No',
            'expires_at' => $emailProvider->expires_at,
            'connected' => $emailProvider->connected,
        ]);

        // Verify the record was saved
        $verifyProvider = EmailProvider::find($emailProvider->id);
        \Log::info('Database verification:', [
            'record_exists' => $verifyProvider ? 'Yes' : 'No',
            'provider_email_match' => $verifyProvider && $verifyProvider->provider_email === $providerUser->getEmail() ? 'Yes' : 'No',
        ]);

        // Redirect to your React frontend
        $frontendUrl = 'http://localhost:5173';
        $redirectUrl = $frontendUrl . '/integrations?connected=success&provider=' . $provider;
        
        \Log::info('=== REDIRECTING TO FRONTEND ===');
        \Log::info('Redirect URL: ' . $redirectUrl);
        \Log::info('=== GOOGLE OAUTH CALLBACK COMPLETED SUCCESSFULLY ===');
        
        return \Illuminate\Support\Facades\Redirect::away($redirectUrl);
        
    } catch (\Exception $e) {
        \Log::error('=== GOOGLE OAUTH CALLBACK FAILED ===');
        \Log::error('Error Message: ' . $e->getMessage());
        \Log::error('Error Code: ' . $e->getCode());
        \Log::error('File: ' . $e->getFile());
        \Log::error('Line: ' . $e->getLine());
        \Log::error('Trace:', ['trace' => $e->getTraceAsString()]);
        
        // Log request details for debugging
        \Log::error('Request details on error:', [
            'full_url' => $request->fullUrl(),
            'state' => $request->state,
            'code_present' => !empty($request->code),
            'provider' => $provider,
        ]);
        
        // Redirect to frontend with error
        $frontendUrl = 'http://localhost:5173';
        $errorMessage = urlencode($e->getMessage());
        
        \Log::info('Redirecting to frontend with error: ' . $errorMessage);
        return \Illuminate\Support\Facades\Redirect::away($frontendUrl . '/integrations?connected=error&message=' . $errorMessage);
    }
}
}