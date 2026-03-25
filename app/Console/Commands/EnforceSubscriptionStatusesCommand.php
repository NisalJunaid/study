<?php

namespace App\Console\Commands;

use App\Services\Billing\SubscriptionEnforcementService;
use Illuminate\Console\Command;

class EnforceSubscriptionStatusesCommand extends Command
{
    protected $signature = 'subscriptions:enforce';

    protected $description = 'Apply billing enforcement rules for pending, monthly, and annual subscriptions';

    public function handle(SubscriptionEnforcementService $enforcementService): int
    {
        $results = $enforcementService->enforce();

        $this->info('Subscription enforcement completed.');
        $this->line('Expired pending payments: '.$results['expired_pending_payments']);
        $this->line('Monthly suspensions: '.$results['monthly_suspensions']);
        $this->line('Annual suspensions: '.$results['annual_suspensions']);

        return self::SUCCESS;
    }
}
