# Examples

### Simple Record Processing

```php
// Process all pending orders
$result = hunter(Order::class)
    ->find('status', 'pending')
    ->then(function (Order $order) {
        $order->process();
    })
    ->hunt();

echo $result->summary(); // "Total: 15, Successful: 14, Failed: 1, Skipped: 0"
```

### Conditional Processing with Advanced Logic

```php
$result = hunter(Order::class)
    ->find('status', 'pending')    
    // Only process expedited orders immediately
    ->thenIf(
        fn($order) => $order->isExpedited(),
        fn($order) => $order->processImmediate()
    )  
    ->onError(function ($order, $e, $hunter) {
        if ($e instanceof CriticalException) {
            $hunter->stop('Critical system error detected');
        }
    })
    
    ->hunt();

// Enhanced reporting
echo $result->summary(); // "Total: 100, Successful: 95, Failed: 2, Skipped: 3 (Stopped: Critical system error detected)"

if ($result->hasSkipped()) {
    foreach ($result->skipReasons as $recordId => $reason) {
        echo "Skipped {$recordId}: {$reason}\n";
    }
}
```

### Complex Pipeline with Multiple Actions

```php
hunter(Campaign::class)
    ->find('scheduled_at', '<=', now())
    ->modifyQueryUsing(function (Builder $query): Builder {
        return $query->where('status', 'scheduled')
              ->where('active', true);
    })
    ->onBeforeThen(function (Campaign $campaign, Hunter $hunter) {
        // Validation with graceful failure
        if (!$campaign->hasContacts()) {
            $hunter->fail($campaign, 'No contacts to send to');
            return;
        }
    })
    ->onBeforeThen(function ($campaign) {
        // Backup
        $campaign->createBackup();
    })
    ->then(function ($campaign) {
        // Main processing
        ProcessCampaignJob::dispatch($campaign);
    })
    ->onAfterThen(function ($campaign) {
        // Update status
        $campaign->update(['status' => 'processing']);
    })
    ->onAfterThen(function ($campaign) {
        // Send notifications
        $campaign->notifyStakeholders();
    })
    ->onSuccess(function ($campaign) {
        Log::info("Campaign dispatched: {$campaign->id}");
    })
    ->onSuccess(function ($campaign) {
        MetricsService::incrementCampaigns();
    })
    ->onError(function ($campaign, $e) {
        $campaign->update(['status' => 'failed']);
    })
    ->onError(function ($campaign, $e) {
        NotificationService::alertAdmins($campaign, $e);
    })
    ->withLogging('campaign_processing')
    ->hunt();
```

### Laravel Command Integration

```php
// Perfect for Laravel scheduled commands
class ProcessSurveysCommand extends Command
{
    public function handle(): int
    {
        $result = hunter(Survey::class)
            ->find('scheduled_at', '<=', now())
            ->modifyQueryUsing(fn($q) => $q->where('status', 'scheduled'))
            ->onBeforeThen(function ($survey, $hunter) {
                if (!$survey->isReady()) {
                    $hunter->skip($survey);
                    return;
                }
            })
            ->then(function ($survey) {
                $survey->update(['status' => 'published']);
            })
            ->onSuccess(function ($survey) {
                $this->line("Published survey: {$survey->name}");
            })
            ->onError(function ($survey, $e) {
                $this->error("Failed to publish survey {$survey->id}: {$e->getMessage()}");
            })
            ->hunt();

        $this->info($result->summary());
        
        return $result->hasErrors() ? self::FAILURE : self::SUCCESS;
    }
}
```

### Memory-Efficient Bulk Processing

```php
$result = hunter(User::class)
    ->find('last_login_at', '<', now()->subDays(30))
    ->modifyQueryUsing(fn($q) => $q->where('status', 'active'))    
    ->thenIf(
        fn() => now()->between('09:00', '17:00'),
        fn($user) => $user->sendInactivityReminder()
    )
    ->then(function ($user) {
        $user->update(['reminder_sent_at' => now()]);
    })
    ->onError(function ($user, $e, $hunter) {
        Log::error("Failed to send reminder to user {$user->id}: {$e->getMessage()}");
        
        // Stop if email service is completely down
        if ($e instanceof EmailServiceException) {
            $hunter->stop('Email service unavailable');
        }
    })
    ->withLogging('user_reminders')
    ->hunt();

if ($result->wasStopped()) {
    echo "Processing stopped: {$result->stopReason}\n";
}
```

## Notes

- All callbacks are executed in the order they were registered
- Flow control methods (`skip()`, `fail()`, `stop()`) provide graceful alternatives to throwing exceptions
- Parameter resolution is flexible - use any parameter names that make sense for your use case
- If any callback in the chain throws an exception, the remaining callbacks in that chain are skipped and `onError()` callbacks are executed
- Logging is enabled by default and uses the Laravel Log facade
- Multiple query modifiers are applied cumulatively
- The `modifyQueryUsing()` method requires returning a Builder instance
- Skipped records are tracked separately from failed records for better reporting
