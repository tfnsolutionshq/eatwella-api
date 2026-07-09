<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;

class SendPushNotification implements ShouldQueue
{
    use Queueable, InteractsWithQueue;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * @param  string[]  $userIds  Array of user UUIDs to push to.
     */
    public function __construct(
        public readonly array $userIds,
        public readonly string $title,
        public readonly string $body,
        public readonly array $data = [],
    ) {}

    public function handle(FirebaseService $firebase): void
    {
        $users = User::whereIn('id', $this->userIds)->get();

        foreach ($users as $user) {
            $firebase->sendToUser($user, $this->title, $this->body, $this->data);
        }
    }
}
