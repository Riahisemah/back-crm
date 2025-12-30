<?php

namespace App\Services;

use App\Models\EmailProvider;
use Google\Client;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;
use Illuminate\Support\Facades\Log;

class GoogleService
{
    /**
     * Refresh Google access token if expired or about to expire
     */
    public function refreshTokenIfNeeded($userId)
    {
        try {
            $provider = EmailProvider::where('user_id', $userId)
                ->where('provider', 'google')
                ->first();

            if (!$provider) {
                Log::warning('No Google provider found for user', ['user_id' => $userId]);
                return false;
            }

            if (!$provider->refresh_token) {
                Log::warning('No refresh token for Google provider', ['user_id' => $userId]);
                return false;
            }

            // Refresh token if:
            // - expires_at is missing
            // - OR already expired
            // - OR expires in the next 5 minutes
            if (
                !$provider->expires_at ||
                $provider->expires_at->isPast() ||
                $provider->expires_at->diffInMinutes(now()) <= 5
            ) {
                Log::info('Refreshing Google token...', [
                    'user_id' => $userId,
                    'expires_at' => $provider->expires_at
                ]);

                $client = $this->getGoogleClient();
                $client->refreshToken($provider->refresh_token);

                $newToken = $client->getAccessToken();

                if (!isset($newToken['access_token'])) {
                    Log::error('Failed to get new access token from Google', ['user_id' => $userId]);
                    return false;
                }

                $provider->update([
                    'access_token'  => $newToken['access_token'],
                    'expires_at'    => now()->addSeconds($newToken['expires_in']),
                    'refresh_token' => $newToken['refresh_token'] ?? $provider->refresh_token,
                    'connected'     => true,
                ]);

                Log::info('Google token refreshed successfully', [
                    'user_id' => $userId,
                    'new_expires_at' => $provider->expires_at
                ]);
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Error refreshing Google token', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString()
            ]);

            // If refresh token is revoked -> disconnect
            if (str_contains($e->getMessage(), 'invalid_grant')) {
                if (isset($provider)) {
                    $provider->update(['connected' => false]);
                }

                Log::warning('Marked Google provider as disconnected due to invalid grant', [
                    'user_id' => $userId
                ]);
            }

            return false;
        }
    }

    /**
     * Get authenticated Google Client for a user
     */
    public function getAuthenticatedClient($userId)
    {
        if (!$this->refreshTokenIfNeeded($userId)) {
            return null;
        }

        $provider = EmailProvider::where('user_id', $userId)
            ->where('provider', 'google')
            ->first();

        if (!$provider || !$provider->access_token) {
            return null;
        }

        $client = $this->getGoogleClient();
        $client->setAccessToken($provider->access_token);

        return $client;
    }

    /**
     * Send a test email via Gmail
     */
    public function sendTestEmail($userId, $toEmail = null)
    {
        try {
            $provider = EmailProvider::where('user_id', $userId)
                ->where('provider', 'google')
                ->first();

            if (!$provider) {
                return [
                    'success' => false,
                    'message' => 'No Google account connected'
                ];
            }

            $client = $this->getAuthenticatedClient($userId);
            if (!$client) {
                return [
                    'success' => false,
                    'message' => 'Failed to authenticate with Google'
                ];
            }

            $gmail = new Gmail($client);

            $toEmail = $toEmail ?: $provider->provider_email;

            $message = new Message();

            $rawMessage = "From: {$provider->provider_email}\r\n";
            $rawMessage .= "To: {$toEmail}\r\n";
            $rawMessage .= "Subject: Test Email from Your CRM\r\n";
            $rawMessage .= "Content-Type: text/html; charset=utf-8\r\n\r\n";
            $rawMessage .= "<h1>Test Email Successful!</h1>";
            $rawMessage .= "<p>This is a test email sent from your CRM integration with Google.</p>";
            $rawMessage .= "<p>If you received this email, your Gmail integration is working correctly!</p>";

            $message->setRaw(base64_encode($rawMessage));

            $result = $gmail->users_messages->send('me', $message);

            Log::info('Test email sent successfully', [
                'user_id' => $userId,
                'from' => $provider->provider_email,
                'to' => $toEmail,
                'message_id' => $result->getId()
            ]);

            return [
                'success' => true,
                'message' => 'Test email sent successfully!',
                'message_id' => $result->getId()
            ];

        } catch (\Exception $e) {
            Log::error('Error sending test email', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send email: ' . $e->getMessage()
            ];
        }
    }

    public function getGmailProfile($userId)
    {
        try {
            $client = $this->getAuthenticatedClient($userId);
            if (!$client) {
                return null;
            }

            $gmail = new Gmail($client);
            $profile = $gmail->users->getProfile('me');

            return [
                'email_address' => $profile->getEmailAddress(),
                'messages_total' => $profile->getMessagesTotal(),
                'threads_total' => $profile->getThreadsTotal(),
                'history_id' => $profile->getHistoryId(),
            ];

        } catch (\Exception $e) {
            Log::error('Error getting Gmail profile', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    public function checkConnection($userId)
    {
        try {
            Log::info('=== CHECKING GOOGLE CONNECTION ===', ['user_id' => $userId]);

            $provider = EmailProvider::where('user_id', $userId)
                ->where('provider', 'google')
                ->first();

            if (!$provider) {
                return [
                    'connected' => false,
                    'message' => 'No Google account connected'
                ];
            }

            $refreshResult = $this->refreshTokenIfNeeded($userId);

            if (!$refreshResult) {
                return [
                    'connected' => false,
                    'message' => 'Failed to refresh Google token'
                ];
            }

            $provider->refresh();

            $userInfo = $this->getUserInfo($userId);

            if ($userInfo) {
                return [
                    'connected' => true,
                    'message' => 'Connection valid',
                    'email' => $provider->provider_email,
                    'expires_at' => $provider->expires_at,
                    'user_info' => $userInfo
                ];
            }

            return [
                'connected' => false,
                'message' => 'Failed to verify connection'
            ];

        } catch (\Exception $e) {
            Log::error('Error checking Google connection:', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return [
                'connected' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    public function getUserInfo($userId)
    {
        try {
            $client = $this->getAuthenticatedClient($userId);

            if (!$client) {
                return null;
            }

            $oauth2 = new \Google\Service\Oauth2($client);
            $userInfo = $oauth2->userinfo->get();

            return [
                'email' => $userInfo->getEmail(),
                'name' => $userInfo->getName(),
                'picture' => $userInfo->getPicture(),
                'verified_email' => $userInfo->getVerifiedEmail(),
            ];

        } catch (\Exception $e) {
            Log::error('Error getting user info:', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    private function getGoogleClient()
    {
        $client = new Client();
        $client->setApplicationName(config('app.name', 'CRM Application'));
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(url('/email-provider/google/callback'));

        $client->setScopes([
            'https://www.googleapis.com/auth/gmail.send',
            'https://www.googleapis.com/auth/gmail.readonly',
            'https://www.googleapis.com/auth/userinfo.email',
            'https://www.googleapis.com/auth/userinfo.profile',
            'openid',
        ]);

        $client->setAccessType('offline');
        $client->setPrompt('consent');

        return $client;
    }
}
