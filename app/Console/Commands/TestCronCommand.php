<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestCronCommand extends Command
{
    protected $signature = 'test:cron';
    protected $description = 'Test cron scheduler';

    public function handle(): void
    {
        Mail::raw('Cron is working! Time: ' . now(), function ($message) {
            $message->to('promisedeco24@gmail.com')
                    ->subject('Cron Test - ' . now());
        });
        
        $this->info('Test email sent!');
    }
}
