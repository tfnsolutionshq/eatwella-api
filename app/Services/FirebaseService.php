<?php

namespace App\Services;

use App\Models\FcmToken;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class FirebaseService
{
    /**
     * Send a push notification to a single user (all their registered devices).
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        Log::info('[FCM] sendToUser called.', ['user_id' => $user->id, 'title' => $title]);

        if (! $user->push_notifications_enabled) {
            Log::info('[FCM] Push disabled for user.', ['user_id' => $user->id]);
            return;
        }

        $tokens = FcmToken::where('user_id', $user->id)->pluck('token')->toArray();

        if (empty($tokens)) {
            Log::info('[FCM] No FCM tokens found for user.', ['user_id' => $user->id]);
            return;
        }

        Log::info('[FCM] Sending to tokens.', ['user_id' => $user->id, 'token_count' => count($tokens)]);

        $this->sendToTokens($tokens, $title, $body, $data, $user->id);
    }

    /**
     * Send a push notification to multiple users.
     *
     * @param \Illuminate\Support\Collection|User[] $users
     */
    public function sendToUsers(iterable $users, string $title, string $body, array $data = []): void
    {
        foreach ($users as $user) {
            $this->sendToUser($user, $title, $body, $data);
        }
    }

    // ─── Internal ─────────────────────────────────────────────────────────────

    private function sendToTokens(array $tokens, string $title, string $body, array $data, string $userId): void
    {
        $credentialsPath = config('firebase.credentials.file') ?? env('FIREBASE_CREDENTIALS');

        if ($credentialsPath && !str_starts_with($credentialsPath, '/')) {
            $credentialsPath = base_path($credentialsPath);
        }

        Log::info('[FCM] Credentials path resolved.', ['path' => $credentialsPath]);

        if (! $credentialsPath) {
            Log::debug('[FCM] Firebase credentials not configured. Push notification skipped.', [
                'title'   => $title,
                'user_id' => $userId,
            ]);
            return;
        }

        try {
            $factory = (new \Kreait\Firebase\Factory)->withServiceAccount($credentialsPath);
            $messaging = $factory->createMessaging();

            $invalidTokens = [];

            foreach ($tokens as $token) {
                try {
                    $message = \Kreait\Firebase\Messaging\CloudMessage::withTarget('token', $token)
                        ->withNotification(\Kreait\Firebase\Messaging\Notification::create($title, $body))
                        ->withData(array_map('strval', $data)); // FCM requires string values

                    $messaging->send($message);
                    Log::info('[FCM] Push sent successfully.', ['user_id' => $userId, 'token_prefix' => substr($token, 0, 10)]);
                } catch (\Kreait\Firebase\Exception\Messaging\InvalidMessage $e) {
                    // Token is invalid or unregistered — remove it
                    Log::warning('[FCM] Invalid token removed.', ['token' => substr($token, 0, 10) . '...', 'error' => $e->getMessage()]);
                    $invalidTokens[] = $token;
                } catch (\Throwable $e) {
                    Log::error('[FCM] Failed to send push to token.', ['error' => $e->getMessage()]);
                }
            }

            // Clean up stale tokens
            if (! empty($invalidTokens)) {
                FcmToken::whereIn('token', $invalidTokens)->delete();
            }
        } catch (\Throwable $e) {
            Log::error('[FCM] Firebase initialisation failed.', ['error' => $e->getMessage()]);
        }
    }
}
