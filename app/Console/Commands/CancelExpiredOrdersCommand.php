<?php

namespace App\Console\Commands;

use App\Jobs\CancelExpiredOrders;
use Illuminate\Console\Command;

class CancelExpiredOrdersCommand extends Command
{
    protected $signature = 'orders:cancel-expired';
    protected $description = 'Cancel expired orders';

    public function handle(): void
    {
        $this->info('Cancelling expired orders...');
        (new CancelExpiredOrders)->handle();
        $this->info('Done!');
    }
}
