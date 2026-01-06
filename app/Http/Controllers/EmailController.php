<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Services\GoogleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;

class EmailController extends Controller
{
    protected $googleService;

    public function __construct(GoogleService $googleService)
    {
        $this->googleService = $googleService;
    }

    /**
     * Send an email to a lead
     */
    public function sendToLead(Request $request, Lead $lead)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Check if user has access to this lead
        if ($lead->organisation_id !== $user->organisation_id) {
            return response()->json(['message' => 'Unauthorized for this lead'], 403);
        }

        $validator = Validator::make($request->all(), [
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check if lead has email
            if (!$lead->email) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lead does not have an email address'
                ], 400);
            }

            // Send email via Google Service
            $result = $this->sendEmail($user->id, [
                'to' => $lead->email,
                'subject' => $request->subject,
                'body' => $request->body,
                'lead_id' => $lead->id,
            ]);

            if ($result['success']) {
                // Log the email sent in lead history with body
                $this->logEmailSent($lead, $request->subject, $result, $request->body);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Email sent successfully',
                    'data' => [
                        'message_id' => $result['message_id'] ?? null,
                        'lead' => [
                            'id' => $lead->id,
                            'name' => $lead->full_name,
                            'email' => $lead->email
                        ]
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 400);

        } catch (\Exception $e) {
            Log::error('Error sending email to lead', [
                'user_id' => $user->id,
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send email: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send bulk emails to multiple leads
     */
    public function sendBulkEmails(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'lead_ids' => 'required|array|min:1',
            'lead_ids.*' => 'exists:leads,id',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'personalize' => 'boolean',
            'batch_size' => 'integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Get leads that belong to user's organization
            $leads = Lead::whereIn('id', $request->lead_ids)
                ->where('organisation_id', $user->organisation_id)
                ->whereNotNull('email')
                ->get();

            if ($leads->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid leads found with email addresses'
                ], 400);
            }

            $results = [
                'success' => [],
                'failed' => []
            ];

            $batchSize = $request->batch_size ?? 10;
            $delay = 0;

            foreach ($leads->chunk($batchSize) as $leadBatch) {
                foreach ($leadBatch as $lead) {
                    try {
                        // Personalize email body if requested
                        $body = $this->personalizeEmail($request->body, $lead, $request->personalize ?? false);

                        $result = $this->sendEmail($user->id, [
                            'to' => $lead->email,
                            'subject' => $this->personalizeSubject($request->subject, $lead, $request->personalize ?? false),
                            'body' => $body,
                            'lead_id' => $lead->id,
                            'delay' => $delay,
                        ]);

                        if ($result['success']) {
                            $results['success'][] = [
                                'lead_id' => $lead->id,
                                'email' => $lead->email,
                                'message_id' => $result['message_id'] ?? null
                            ];
                            
                            // Log successful email with body
                            $this->logEmailSent($lead, $request->subject, $result, $body);
                        } else {
                            $results['failed'][] = [
                                'lead_id' => $lead->id,
                                'email' => $lead->email,
                                'error' => $result['message']
                            ];
                        }

                        $delay += 1;

                    } catch (\Exception $e) {
                        $results['failed'][] = [
                            'lead_id' => $lead->id,
                            'email' => $lead->email,
                            'error' => $e->getMessage()
                        ];
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Bulk email operation completed',
                'data' => [
                    'total_processed' => count($leads),
                    'success_count' => count($results['success']),
                    'failed_count' => count($results['failed']),
                    'results' => $results
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error sending bulk emails', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send bulk emails: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send a custom email (not to a lead)
     */
    public function sendCustomEmail(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'to' => 'required|array|min:1',
            'to.*' => 'email',
            'cc' => 'nullable|array',
            'cc.*' => 'email',
            'bcc' => 'nullable|array',
            'bcc.*' => 'email',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'is_html' => 'boolean',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->sendEmail($user->id, [
                'to' => $request->to,
                'cc' => $request->cc,
                'bcc' => $request->bcc,
                'subject' => $request->subject,
                'body' => $request->body,
                'is_html' => $request->is_html ?? true,
                'attachments' => $request->file('attachments', []),
            ]);

            if ($result['success']) {
                // Log custom email (optional)
                if (Schema::hasTable('email_logs')) {
                    $this->logEmailSent(null, $request->subject, $result, $request->body);
                }
                
                return response()->json([
                    'success' => true,
                    'message' => 'Email sent successfully',
                    'data' => [
                        'message_id' => $result['message_id'] ?? null,
                        'recipients' => [
                            'to' => $request->to,
                            'cc' => $request->cc ?? [],
                            'bcc' => $request->bcc ?? []
                        ]
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 400);

        } catch (\Exception $e) {
            Log::error('Error sending custom email', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send email: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get email history for a lead
     */
    public function getLeadEmailHistory(Lead $lead, Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($lead->organisation_id !== $user->organisation_id) {
            return response()->json(['message' => 'Unauthorized for this lead'], 403);
        }

        // Check if email_logs table exists
        if (Schema::hasTable('email_logs')) {
            $logs = \App\Models\EmailLog::where('lead_id', $lead->id)
                ->orderBy('sent_at', 'desc')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'lead_id' => $lead->id,
                    'emails' => $logs,
                    'total_sent' => $logs->count(),
                    'last_sent' => $logs->first() ? $logs->first()->sent_at : null
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'lead_id' => $lead->id,
                'emails' => [],
                'total_sent' => 0,
                'last_sent' => null
            ]
        ]);
    }

    /**
     * Check if user can send emails (connection status)
     */
    public function checkEmailCapability(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $result = $this->googleService->checkConnection($user->id);

        return response()->json([
            'can_send_emails' => $result['connected'] ?? false,
            'provider_email' => $result['email'] ?? null,
            'expires_at' => $result['expires_at'] ?? null,
            'message' => $result['message'] ?? 'Not connected'
        ]);
    }
    
    /**
     * Private method to send email via Google Service
     */
    private function sendEmail($userId, array $data)
    {
        try {
            $client = $this->googleService->getAuthenticatedClient($userId);
            
            if (!$client) {
                return [
                    'success' => false,
                    'message' => 'Failed to authenticate with Google. Please reconnect your Google account.'
                ];
            }

            $gmail = new \Google\Service\Gmail($client);
            $message = new \Google\Service\Gmail\Message();

            $to = is_array($data['to']) ? implode(', ', $data['to']) : $data['to'];
            
            $headers = [
                'From: ' . $this->getSenderEmail($userId),
                'To: ' . $to,
                'Subject: ' . $data['subject'],
                'Content-Type: ' . ($data['is_html'] ?? true ? 'text/html; charset=utf-8' : 'text/plain; charset=utf-8'),
            ];

            if (!empty($data['cc'])) {
                $headers[] = 'Cc: ' . implode(', ', $data['cc']);
            }

            if (!empty($data['bcc'])) {
                $headers[] = 'Bcc: ' . implode(', ', $data['bcc']);
            }

            $boundary = null;
            if (!empty($data['attachments'])) {
                $boundary = uniqid('boundary_');
                $headers[] = 'MIME-Version: 1.0';
                $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
            }

            $rawMessage = implode("\r\n", $headers) . "\r\n\r\n";
            
            if ($boundary) {
                $rawMessage .= "--{$boundary}\r\n";
                $rawMessage .= "Content-Type: " . ($data['is_html'] ?? true ? 'text/html' : 'text/plain') . "; charset=utf-8\r\n\r\n";
                $rawMessage .= $data['body'] . "\r\n\r\n";
                
                foreach ($data['attachments'] as $attachment) {
                    $rawMessage .= "--{$boundary}\r\n";
                    $rawMessage .= "Content-Type: " . $attachment->getMimeType() . "; name=\"" . $attachment->getClientOriginalName() . "\"\r\n";
                    $rawMessage .= "Content-Disposition: attachment; filename=\"" . $attachment->getClientOriginalName() . "\"\r\n";
                    $rawMessage .= "Content-Transfer-Encoding: base64\r\n\r\n";
                    $rawMessage .= chunk_split(base64_encode(file_get_contents($attachment->getRealPath()))) . "\r\n";
                }
                
                $rawMessage .= "--{$boundary}--";
            } else {
                $rawMessage .= $data['body'];
            }

            $message->setRaw(base64_encode($rawMessage));

            if (isset($data['delay']) && $data['delay'] > 0) {
                sleep($data['delay']);
            }

            $result = $gmail->users_messages->send('me', $message);

            Log::info('Email sent successfully', [
                'user_id' => $userId,
                'to' => $data['to'],
                'subject' => $data['subject'],
                'message_id' => $result->getId()
            ]);

            return [
                'success' => true,
                'message' => 'Email sent successfully',
                'message_id' => $result->getId(),
                'sent_at' => now()->toDateTimeString()
            ];

        } catch (\Google\Service\Exception $e) {
            Log::error('Google API error sending email', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'details' => $e->getErrors() ?? []
            ]);

            return [
                'success' => false,
                'message' => 'Google API error: ' . $e->getMessage()
            ];
            
        } catch (\Exception $e) {
            Log::error('Error sending email', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send email: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Personalize email body with lead data
     */
    private function personalizeEmail($body, Lead $lead, $personalize = true)
    {
        if (!$personalize) {
            return $body;
        }

        $replacements = [
            '{{lead_name}}' => $lead->full_name,
            '{{first_name}}' => explode(' ', $lead->full_name)[0] ?? $lead->full_name,
            '{{company}}' => $lead->company ?? '',
            '{{position}}' => $lead->position ?? '',
            '{{location}}' => $lead->location ?? '',
        ];

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $body
        );
    }

    /**
     * Personalize subject line
     */
    private function personalizeSubject($subject, Lead $lead, $personalize = true)
    {
        if (!$personalize) {
            return $subject;
        }

        $replacements = [
            '{{lead_name}}' => $lead->full_name,
            '{{first_name}}' => explode(' ', $lead->full_name)[0] ?? $lead->full_name,
            '{{company}}' => $lead->company ?? '',
        ];

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $subject
        );
    }

    /**
     * Get sender email from connected Google account
     */
    private function getSenderEmail($userId)
    {
        $provider = \App\Models\EmailProvider::where('user_id', $userId)
            ->where('provider', 'google')
            ->first();

        return $provider ? $provider->provider_email : config('mail.from.address');
    }

    /**
     * Log email sent to lead
     */
    private function logEmailSent($lead, $subject, array $result, $body = null)
    {
        $user = auth()->user();
        
        try {
            // Check if table exists
            if (!Schema::hasTable('email_logs')) {
                return;
            }
            
            // For custom emails, lead might be null
            $leadData = [
                'lead_id' => $lead ? $lead->id : null,
                'user_id' => $user->id,
                'organisation_id' => $user->organisation_id,
                'to_email' => $lead ? $lead->email : 'custom_email@example.com',
                'subject' => $subject,
                'body' => $body, // This was missing - now added
                'sent_at' => now(),
                'message_id' => $result['message_id'] ?? null,
                'status' => $result['success'] ? 'sent' : 'failed',
                'error_message' => $result['success'] ? null : ($result['message'] ?? 'Unknown error')
            ];

            \App\Models\EmailLog::create($leadData);

            // Update lead's last contacted date if it's a lead email
            if ($lead && $lead->email) {
                $lead->update([
                    'last_contacted_at' => now()
                ]);
            }
            
        } catch (\Exception $e) {
            \Log::error('Failed to log email', [
                'error' => $e->getMessage(),
                'lead_id' => $lead ? $lead->id : null,
                'user_id' => $user->id ?? null
            ]);
        }
    }
}