<script setup>
import { onMounted } from 'vue';

onMounted(() => {
    const titleLink = document.querySelector('a.title');
    const span = titleLink?.querySelector('span');
    span?.remove();

    titleLink.style.display = 'flex';
    titleLink.style.justifyContent = 'center';
    titleLink.style.alignItems = 'center';

    titleLink.querySelectorAll('img')?.forEach(img => {
        img.style.width = '42px';
        img.style.height = '42px';
        img.style.marginRight = '8px';
    });
});
</script>

# Hunter Examples

This document contains practical examples of using Hunter for different scenarios and use cases.

## Table of Contents

1. [Basic Operations](#basic-operations)
2. [Conditional Processing](#conditional-processing)
3. [Callbacks and Hooks](#callbacks-and-hooks)
4. [Dry Run and Testing](#dry-run-and-testing)
5. [Progress Tracking](#progress-tracking)
6. [Convenience Methods](#convenience-methods)
7. [Flow Control](#flow-control)
8. [Advanced Use Cases](#advanced-use-cases)
9. [Performance Optimization](#performance-optimization)

## Basic Operations

### Simple Update

```php
use Hunter\Hunter;
use App\Models\User;

// Activate all inactive users using the class method
$result = Hunter::for(User::class)
    ->find('status', 'inactive')
    ->then(function ($user) {
        $user->update(['status' => 'active']);
    })
    ->hunt();

echo "Users activated: {$result->successful}";

// Or use the convenient helper function
$result = hunter(User::class)
    ->find('status', 'inactive')
    ->then(function ($user) {
        $user->update(['status' => 'active']);
    })
    ->hunt();
```

````

### Search with Operators

```php
// Process users created in the last 30 days
$result = Hunter::for(User::class)
    ->find('created_at', '>', now()->subDays(30))
    ->then(function ($user) {
        $user->update(['welcome_email_sent' => true]);
    })
    ->hunt();
````

### Custom Query Building

```php
$result = Hunter::for(User::class)
    ->find('status', 'active')
    ->modifyQueryUsing(function ($query) {
        return $query->whereHas('posts', function ($q) {
            $q->where('published', true);
        })->where('last_login', '<', now()->subMonths(6));
    })
    ->then(function ($user) {
        $user->update(['status' => 'inactive']);
    })
    ->hunt();
```

## Conditional Processing

### Using thenIf with Closures

```php
$result = Hunter::for(User::class)
    ->find('status', 'pending')
    ->thenIf(
        fn($user) => $user->posts()->count() === 0,
        fn($user) => $user->delete()
    )
    ->thenIf(
        fn($user) => $user->last_login < now()->subYear(),
        fn($user) => $user->update(['status' => 'dormant'])
    )
    ->hunt();
```

### Simple Boolean Conditions

```php
$sendNotifications = true;

$result = Hunter::for(User::class)
    ->find('notification_pending', true)
    ->thenIf($sendNotifications, function ($user) {
        $user->notify(new WeeklyDigest());
        $user->update(['notification_pending' => false]);
    })
    ->hunt();
```

### Conditions with Context

```php
$result = Hunter::for(Order::class)
    ->find('status', 'pending')
    ->thenIf(
        fn($order, $hunter) => $order->created_at < now()->subHours(24),
        fn($order) => $order->update(['status' => 'expired']),
        ['timeout_hours' => 24]
    )
    ->hunt();
```

## Callbacks and Hooks

### Complete Lifecycle

```php
$result = Hunter::for(User::class)
    ->find('status', 'pending_verification')
    ->onBeforeThen(function ($user, $hunter) {
        // Validation before processing
        if ($user->email === null) {
            $hunter->skip($user, 'Missing email address');
            return;
        }

        Log::info("Starting verification for user {$user->id}");
    })
    ->then(function ($user) {
        // Main processing
        $user->sendEmailVerification();
        $user->update(['verification_sent_at' => now()]);
    })
    ->onAfterThen(function ($user) {
        // Actions after processing
        Log::info("Verification sent to user {$user->id}");
    })
    ->onSuccess(function ($user) {
        // Success handling
        $user->increment('verification_attempts');
    })
    ->onError(function ($user, $exception, $hunter) {
        // Error handling
        Log::error("Failed to send verification to user {$user->id}: {$exception->getMessage()}");
        $user->update(['verification_error' => $exception->getMessage()]);
    })
    ->hunt();
```

## Dry Run and Testing

### Testing Dangerous Operations

```php
// First, test with dry run
$dryResult = Hunter::for(User::class)
    ->find('last_login', '<', now()->subYear())
    ->whereNull('premium_expires_at')
    ->dryRun() // Important!
    ->then(function ($user) {
        $user->delete(); // Won't be executed
    })
    ->hunt();

echo "Users that would be deleted: {$dryResult->successful}\n";

// If everything looks good, run without dry run
if (confirm("Delete {$dryResult->successful} users?")) {
    $realResult = Hunter::for(User::class)
        ->find('last_login', '<', now()->subYear())
        ->whereNull('premium_expires_at')
        ->then(function ($user) {
            $user->delete();
        })
        ->hunt();

    echo "Users deleted: {$realResult->successful}\n";
}
```

### Dry Run with Detailed Logging

```php
$result = Hunter::for(Product::class)
    ->find('stock', 0)
    ->dryRun()
    ->withLogging('product-cleanup')
    ->then(function ($product, $hunter) {
        Log::info("Would archive product: {$product->name}");
        $product->update(['archived' => true]);
    })
    ->hunt();

echo "Products that would be archived: {$result->successful}\n";
```

## Progress Tracking

### Simple Progress Reporting

```php
$result = Hunter::for(User::class)
    ->find('status', 'pending')
    ->onProgress(function ($processed, $total, $percentage) {
        echo "Progress: {$percentage}% ({$processed}/{$total})\n";
    })
    ->then(function ($user) {
        // Process user
        sleep(1); // Simulate slow processing
        $user->update(['processed' => true]);
    })
    ->hunt();
```

### Advanced Progress with Statistics

```php
$result = Hunter::for(Order::class)
    ->find('status', 'pending')
    ->onProgress(function ($processed, $total, $percentage, $successful, $failed, $skipped, $result) {
        if ($processed % 100 === 0 || $percentage >= 100) {
            echo sprintf(
                "Progress: %d%% | Processed: %d/%d | Success: %d | Failed: %d | Skipped: %d\n",
                $percentage, $processed, $total, $successful, $failed, $skipped
            );
        }
    })
    ->then(function ($order, $hunter) {
        try {
            $order->processPayment();
            $order->update(['status' => 'completed']);
        } catch (PaymentException $e) {
            $hunter->fail($order, "Payment failed: {$e->getMessage()}");
        }
    })
    ->hunt();
```

### Custom Progress Tracker

```php
class ProgressTracker
{
    private $startTime;
    private $lastUpdate;

    public function __construct()
    {
        $this->startTime = time();
        $this->lastUpdate = time();
    }

    public function track($processed, $total, $percentage, $successful, $failed, $skipped)
    {
        $now = time();
        $elapsed = $now - $this->startTime;
        $rate = $processed > 0 ? $processed / $elapsed : 0;
        $eta = $rate > 0 ? ($total - $processed) / $rate : 0;

        if ($now - $this->lastUpdate >= 5) { // Update every 5 seconds
            echo sprintf(
                "[%s] %d%% | %d/%d | Rate: %.1f/s | ETA: %s | S:%d F:%d Sk:%d\n",
                date('H:i:s'),
                $percentage,
                $processed,
                $total,
                $rate,
                $this->formatTime($eta),
                $successful,
                $failed,
                $skipped
            );
            $this->lastUpdate = $now;
        }
    }

    private function formatTime($seconds)
    {
        if ($seconds < 60) return sprintf('%ds', $seconds);
        if ($seconds < 3600) return sprintf('%dm%ds', $seconds / 60, $seconds % 60);
        return sprintf('%dh%dm', $seconds / 3600, ($seconds % 3600) / 60);
    }
}

$tracker = new ProgressTracker();

$result = Hunter::for(User::class)
    ->find('status', 'pending')
    ->onProgress([$tracker, 'track'])
    ->then(function ($user) {
        // Process user
    })
    ->hunt();
```

## Convenience Methods

### Advanced Filtering

```php
$result = Hunter::for(User::class)
    ->whereIn('role', ['admin', 'editor', 'moderator'])
    ->whereNotNull('email_verified_at')
    ->whereBetween('created_at', [now()->subYear(), now()->subMonth()])
    ->whereNull('deleted_at')
    ->latest('last_login')
    ->limit(1000)
    ->with(['profile', 'permissions'])
    ->then(function ($user) {
        $user->update(['last_processed' => now()]);
    })
    ->hunt();
```

### Ordering and Limiting

```php
// Process the 100 oldest users without login
$result = Hunter::for(User::class)
    ->whereNull('last_login')
    ->oldest('created_at')
    ->limit(100)
    ->then(function ($user) {
        $user->sendReengagementEmail();
    })
    ->hunt();

// Process newest draft posts
$result = Hunter::for(Post::class)
    ->find('status', 'draft')
    ->latest('updated_at')
    ->with(['author', 'categories'])
    ->then(function ($post) {
        if ($post->shouldAutoPublish()) {
            $post->publish();
        }
    })
    ->hunt();
```

### Optimized Eager Loading

```php
$result = Hunter::for(User::class)
    ->find('status', 'active')
    ->with([
        'posts' => function ($query) {
            $query->where('published', true)->latest();
        },
        'profile:user_id,avatar,bio',
        'subscriptions.plan'
    ])
    ->then(function ($user) {
        // User already has posts, profile and subscriptions loaded
        $user->calculateEngagementScore();
    })
    ->hunt();
```

## Flow Control

### Conditional Skipping

```php
$result = Hunter::for(User::class)
    ->find('status', 'pending_deletion')
    ->then(function ($user, $hunter) {
        // Pre-deletion checks
        if ($user->orders()->where('status', 'pending')->exists()) {
            $hunter->skip($user, 'Has pending orders');
            return;
        }

        if ($user->balance > 0) {
            $hunter->skip($user, 'Has positive balance');
            return;
        }

        if ($user->created_at > now()->subDays(30)) {
            $hunter->skip($user, 'Account too recent');
            return;
        }

        // Safe to delete
        $user->anonymize();
        $user->delete();
    })
    ->hunt();

// Analyze results
foreach ($result->skipReasons as $userId => $reason) {
    echo "User {$userId} skipped: {$reason}\n";
}
```

### Stopping on Critical Conditions

```php
$result = Hunter::for(Order::class)
    ->find('status', 'processing')
    ->then(function ($order, $hunter) {
        // Check API rate limit
        if (ExternalApi::getRemainingCalls() < 10) {
            $hunter->stop('API rate limit approaching');
            return;
        }

        try {
            $order->processPayment();
        } catch (CriticalSystemException $e) {
            $hunter->stop("Critical system error: {$e->getMessage()}");
            return;
        } catch (PaymentException $e) {
            $hunter->fail($order, "Payment failed: {$e->getMessage()}");
            return;
        }

        $order->update(['status' => 'completed']);
    })
    ->hunt();

if ($result->wasStopped()) {
    echo "Processing stopped: {$result->getStopReason()}\n";
    echo "Processed {$result->successful} orders before stopping\n";
}
```

### Fail with Retry Logic

```php
$result = Hunter::for(EmailQueue::class)
    ->find('status', 'pending')
    ->whereNull('processing_at')
    ->then(function ($email, $hunter) {
        try {
            $email->send();
            $email->update(['status' => 'sent', 'sent_at' => now()]);

        } catch (TemporaryFailureException $e) {
            // Temporary failure - schedule retry
            $email->increment('retry_count');

            if ($email->retry_count >= 3) {
                $hunter->fail($email, "Max retries exceeded: {$e->getMessage()}");
            } else {
                $hunter->skip($email, "Temporary failure, will retry: {$e->getMessage()}");
                $email->update(['processing_at' => now()->addMinutes(5 * $email->retry_count)]);
            }

        } catch (PermanentFailureException $e) {
            $hunter->fail($email, "Permanent failure: {$e->getMessage()}");
            $email->update(['status' => 'failed']);
        }
    })
    ->hunt();
```

## Advanced Use Cases

### Complex Data Migration

```php
$result = Hunter::for(LegacyUser::class)
    ->find('migrated', false)
    ->chunk(50) // Smaller chunks for complex operations
    ->onProgress(function ($processed, $total, $percentage) {
        echo "Migrating users: {$percentage}% ({$processed}/{$total})\n";
    })
    ->then(function ($legacyUser, $hunter) {
        DB::transaction(function () use ($legacyUser, $hunter) {
            try {
                // Create new user
                $newUser = User::create([
                    'name' => $legacyUser->full_name,
                    'email' => $legacyUser->email_address,
                    'created_at' => $legacyUser->registration_date,
                    'legacy_id' => $legacyUser->id,
                ]);

                // Migrate posts
                foreach ($legacyUser->articles as $article) {
                    Post::create([
                        'user_id' => $newUser->id,
                        'title' => $article->headline,
                        'content' => $article->body,
                        'published_at' => $article->publish_date,
                    ]);
                }

                // Mark as migrated
                $legacyUser->update(['migrated' => true, 'new_user_id' => $newUser->id]);

            } catch (Exception $e) {
                $hunter->fail($legacyUser, "Migration failed: {$e->getMessage()}");
            }
        });
    })
    ->hunt();

echo "Migration completed:\n";
echo "- Success: {$result->successful}\n";
echo "- Failed: {$result->failed}\n";
echo "- Time: {$result->getExecutionTime()}s\n";
echo "- Memory: {$result->getFormattedMemoryUsage()}\n";
```

### File Processing

```php
$result = Hunter::for(Document::class)
    ->find('status', 'pending_processing')
    ->whereNotNull('file_path')
    ->chunk(10) // Process few files at a time
    ->onProgress(function ($processed, $total, $percentage) {
        echo "Processing files: {$percentage}%\n";
    })
    ->then(function ($document, $hunter) {
        try {
            if (!Storage::exists($document->file_path)) {
                $hunter->fail($document, 'File not found');
                return;
            }

            $fileSize = Storage::size($document->file_path);
            if ($fileSize > 100 * 1024 * 1024) { // 100MB
                $hunter->skip($document, 'File too large');
                return;
            }

            // Process file
            $processor = new DocumentProcessor();
            $result = $processor->process($document->file_path);

            $document->update([
                'status' => 'processed',
                'processed_at' => now(),
                'processing_result' => $result,
            ]);

        } catch (ProcessingException $e) {
            $hunter->fail($document, "Processing error: {$e->getMessage()}");
        }
    })
    ->hunt();
```

### Automated Cleanup with Checks

```php
$result = Hunter::for(TempFile::class)
    ->find('created_at', '<', now()->subDays(7))
    ->onProgress(function ($processed, $total, $percentage) {
        if ($processed % 50 === 0) {
            echo "Cleaning temp files: {$percentage}%\n";
        }
    })
    ->then(function ($tempFile, $hunter) {
        try {
            // Check if file still exists
            if (!Storage::exists($tempFile->path)) {
                $tempFile->delete();
                return;
            }

            // Check if file is being used
            if ($tempFile->isBeingUsed()) {
                $hunter->skip($tempFile, 'File is currently being used');
                return;
            }

            // Delete file and record
            Storage::delete($tempFile->path);
            $tempFile->delete();

        } catch (Exception $e) {
            $hunter->fail($tempFile, "Cleanup failed: {$e->getMessage()}");
        }
    })
    ->hunt();

echo "Cleanup completed:\n";
echo "- Files removed: {$result->successful}\n";
echo "- Failed: {$result->failed}\n";
echo "- Skipped: {$result->skipped}\n";
echo "- Execution time: {$result->getExecutionTime()}s\n";
echo "- Memory usage: {$result->getFormattedMemoryUsage()}\n";

// Display results in a beautiful console table
$result->consoleTable();
```

## Result Reporting and Display

### Console Table Output

Hunter provides a beautiful console table display using Laravel Prompts for better result visualization:

```php
$result = Hunter::for(User::class)
    ->find('status', 'pending')
    ->onProgress(function ($processed, $total, $percentage) {
        echo "Processing: {$percentage}%\n";
    })
    ->then(function ($user) {
        $user->update(['processed' => true]);
    })
    ->hunt();

// Display results in a formatted table
$result->consoleTable();

/*
Output:
┌─────────────────┬─────────┬──────────┐
│ Metric          │ Value   │ Details  │
├─────────────────┼─────────┼──────────┤
│ Total Records   │ 1,250   │          │
│ Successful      │ 1,200   │ 96.0%    │
│ Failed          │ 25      │ 2.0%     │
│ Skipped         │ 25      │ 2.0%     │
│ Execution Time  │ 45.67s  │          │
│ Memory Usage    │ 32.5 MB │          │
│ Status          │ Completed│         │
│ Processing Rate │ 27.4 records/sec │ │
└─────────────────┴─────────┴──────────┘

⚠️  25 record(s) failed to process. Check logs for details.
ℹ️  25 record(s) were skipped.

Skip reasons:
  • Missing email address: 15 record(s)
  • Account locked: 10 record(s)
*/
```

### Comparing Results

```php
// Compare dry run vs actual execution
$dryResult = Hunter::for(User::class)
    ->find('status', 'inactive')
    ->dryRun()
    ->then(fn($user) => $user->delete())
    ->hunt();

echo "=== DRY RUN RESULTS ===\n";
$dryResult->consoleTable();

if (confirm("Proceed with actual execution?")) {
    $realResult = Hunter::for(User::class)
        ->find('status', 'inactive')
        ->then(fn($user) => $user->delete())
        ->hunt();

    echo "\n=== ACTUAL EXECUTION RESULTS ===\n";
    $realResult->consoleTable();
}
```

### Laravel Command Integration with Console Output

```php
class ProcessUsersCommand extends Command
{
    public function handle(): int
    {
        $result = Hunter::for(User::class)
            ->find('status', 'pending')
            ->onProgress(function ($processed, $total, $percentage) {
                $this->line("Processing: {$percentage}% ({$processed}/{$total})");
            })
            ->then(function ($user) {
                $user->update(['processed' => true]);
            })
            ->hunt();

        $this->newLine();
        $this->info('Processing completed! Results:');
        $result->consoleTable();

        return $result->hasErrors() ? self::FAILURE : self::SUCCESS;
    }
}
```

### External API Synchronization

```php
$result = Hunter::for(Product::class)
    ->find('sync_status', 'pending')
    ->orderBy('priority', 'desc')
    ->chunk(25)
    ->onProgress(function ($processed, $total, $percentage) {
        echo "Syncing products: {$percentage}%\n";
    })
    ->then(function ($product, $hunter) {
        try {
            $api = new ExternalProductApi();

            // Check rate limit
            if (!$api->canMakeRequest()) {
                $hunter->stop('API rate limit reached');
                return;
            }

            $externalData = $api->getProduct($product->external_id);

            if (!$externalData) {
                $hunter->fail($product, 'Product not found in external API');
                return;
            }

            // Update product
            $product->update([
                'name' => $externalData['name'],
                'price' => $externalData['price'],
                'stock' => $externalData['stock'],
                'sync_status' => 'synced',
                'last_sync' => now(),
            ]);

        } catch (ApiException $e) {
            if ($e->isRetryable()) {
                $hunter->skip($product, "Temporary API error: {$e->getMessage()}");
            } else {
                $hunter->fail($product, "API error: {$e->getMessage()}");
            }
        }
    })
    ->hunt();
```

## Performance Optimization

### Query Optimization

```php
// ❌ Inefficient
$result = Hunter::for(User::class)
    ->find('status', 'active')
    ->then(function ($user) {
        $user->posts()->where('published', true)->count(); // N+1 query
    })
    ->hunt();

// ✅ Efficient
$result = Hunter::for(User::class)
    ->find('status', 'active')
    ->with(['posts' => fn($q) => $q->where('published', true)])
    ->then(function ($user) {
        $user->posts->count(); // No additional query
    })
    ->hunt();
```

### Appropriate Chunk Sizes

```php
// For simple operations
Hunter::for(User::class)->chunk(1000)

// For operations with multiple queries
Hunter::for(User::class)->chunk(100)

// For heavy operations (API calls, file processing)
Hunter::for(Document::class)->chunk(10)
```

### Memory Monitoring

```php
$result = Hunter::for(User::class)
    ->find('status', 'pending')
    ->onProgress(function ($processed, $total, $percentage, $successful, $failed, $skipped, $result, $hunter) {
        $currentMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);

        echo sprintf(
            "Progress: %d%% | Memory: %s | Peak: %s\n",
            $percentage,
            $this->formatBytes($currentMemory),
            $this->formatBytes($peakMemory)
        );

        // Force garbage collection if needed
        if ($currentMemory > 100 * 1024 * 1024) { // 100MB
            gc_collect_cycles();
        }
    })
    ->then(function ($user) {
        // Process user
    })
    ->hunt();
```

## Best Practices

### 1. Use Appropriate Chunk Sizes

-   **Simple operations (basic updates):** 1000-2000 records
-   **Medium operations (multiple queries):** 100-500 records
-   **Heavy operations (file processing, API calls):** 10-50 records

### 2. Always Test with Dry Run First

```php
// Test first
$dryResult = Hunter::for(User::class)
    ->find('status', 'inactive')
    ->dryRun()
    ->then(fn($user) => $user->delete())
    ->hunt();

echo "Would delete: {$dryResult->successful} records\n";
```

### 3. Monitor Progress for Long Operations

```php
$hunter->onProgress(function ($processed, $total, $percentage) {
    if ($processed % 100 === 0) {
        echo "Progress: {$percentage}%\n";
    }
});
```

### 4. Handle Errors Gracefully

```php
$hunter->then(function ($record, $hunter) {
    try {
        // Risky operation
        $record->complexOperation();
    } catch (RetryableException $e) {
        $hunter->skip($record, "Will retry: {$e->getMessage()}");
    } catch (FatalException $e) {
        $hunter->fail($record, "Fatal: {$e->getMessage()}");
    }
});
```

### 5. Use Eager Loading for Related Data

```php
$hunter->with(['posts', 'profile'])
    ->then(function ($user) {
        // $user->posts and $user->profile are already loaded
    });
```
