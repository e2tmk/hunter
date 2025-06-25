# API Reference

## Hunter Class

### Constructor Methods

#### `Hunter::for(string $modelClass): self`

Creates a new Hunter instance for the specified Eloquent model.

**Parameters:**

-   `$modelClass`: The fully qualified class name of the Eloquent model

**Throws:**

-   `InvalidArgumentException`: If the class doesn't exist or doesn't extend `Model`

**Example:**

```php
$hunter = Hunter::for(User::class);
```

---

### Query Building Methods

#### `find(string $column, mixed $operator = null, mixed $value = null): self`

Sets the primary search criteria.

**Parameters:**

-   `$column`: The column name to search
-   `$operator`: The comparison operator (optional, defaults to '=')
-   `$value`: The value to compare against

**Example:**

```php
$hunter->find('status', 'active');
$hunter->find('created_at', '>', now()->subDays(30));
```

#### `modifyQueryUsing(Closure $callback): self`

Adds a custom query modifier.

**Parameters:**

-   `$callback`: Function that receives the query builder and returns the modified query

**Example:**

```php
$hunter->modifyQueryUsing(function ($query) {
    return $query->whereHas('posts');
});
```

---

### Convenience Methods

#### `whereIn(string $column, array $values): self`

Adds a WHERE IN clause.

#### `whereNull(string $column): self`

Adds a WHERE NULL clause.

#### `whereNotNull(string $column): self`

Adds a WHERE NOT NULL clause.

#### `whereBetween(string $column, array $values): self`

Adds a WHERE BETWEEN clause.

#### `orderBy(string $column, string $direction = 'asc'): self`

Adds an ORDER BY clause.

#### `latest(string $column = 'created_at'): self`

Orders by the specified column in descending order.

#### `oldest(string $column = 'created_at'): self`

Orders by the specified column in ascending order.

#### `limit(int $limit): self`

Limits the number of records.

#### `offset(int $offset): self`

Sets the query offset.

#### `with(array|string $relations): self`

Eager loads the specified relations.

---

### Action Methods

#### `then(Closure $callback): self`

Adds an action to be executed for each record.

**Parameters:**

-   `$callback`: Function that receives the record, hunter instance, and model

**Example:**

```php
$hunter->then(function ($user, $hunter) {
    $user->update(['processed' => true]);
});
```

#### `thenIf(Closure|bool $condition, Closure $callback, array $context = []): self`

Conditionally executes an action.

**Parameters:**

-   `$condition`: Boolean or closure that returns boolean
-   `$callback`: Action to execute if condition is true
-   `$context`: Additional context for the condition closure

**Example:**

```php
$hunter->thenIf(
    fn($user) => $user->posts()->count() === 0,
    fn($user) => $user->delete()
);
```

---

### Callback Methods

#### `onBeforeThen(Closure $callback): self`

Registers a callback to be executed before the main actions.

#### `onAfterThen(Closure $callback): self`

Registers a callback to be executed after the main actions.

#### `onSuccess(Closure $callback): self`

Registers a callback to be executed when processing succeeds.

#### `onError(Closure $callback): self`

Registers a callback to be executed when an error occurs.

#### `onProgress(Closure $callback): self`

Registers a callback to track processing progress.

**Callback Parameters:**

-   `$processed`: Number of records processed
-   `$total`: Total number of records
-   `$percentage`: Completion percentage
-   `$successful`: Number of successful records
-   `$failed`: Number of failed records
-   `$skipped`: Number of skipped records
-   `$result`: The current HunterResult instance
-   `$hunter`: The Hunter instance

**Example:**

```php
$hunter->onProgress(function ($processed, $total, $percentage) {
    echo "Progress: {$percentage}% ({$processed}/{$total})\n";
});
```

---

### Configuration Methods

#### `chunk(int $size): self`

Sets the chunk size for batch processing.

**Parameters:**

-   `$size`: Number of records to process per chunk (must be > 0)

**Default:** 250

#### `withLogging(string $context = 'hunter'): self`

Enables error logging with the specified context.

#### `withoutLogging(): self`

Disables error logging.

#### `dryRun(bool $enabled = true): self`

Enables or disables dry run mode.

**Note:** In dry run mode, the main actions are not executed, but callbacks and validations still run.

---

### Flow Control Methods

#### `skip(?Model $record = null, ?string $reason = null): self`

Marks the current or specified record to be skipped.

#### `stop(?string $reason = null): self`

Stops the entire processing operation.

#### `fail(Model $record, string $reason): self`

Marks a record as failed with a specific reason.

---

### Getter Methods

#### `getCurrentRecord(): ?Model`

Returns the currently being processed record.

#### `getResult(): ?HunterResult`

Returns the current result instance.

#### `getSkipReason(): ?string`

Returns the reason for skipping the current record.

#### `getStopReason(): ?string`

Returns the reason for stopping processing.

---

### Execution Method

#### `hunt(): HunterResult`

Executes the batch processing operation.

**Returns:** `HunterResult` instance with processing statistics and results.

---

## HunterResult Class

### Properties

#### `int $total`

Total number of records found.

#### `int $successful`

Number of successfully processed records.

#### `int $failed`

Number of records that failed processing.

#### `int $skipped`

Number of records that were skipped.

#### `array $errors`

Array of errors indexed by record identifier.

#### `array $skippedRecords`

Array of skipped record identifiers.

#### `array $skipReasons`

Array of skip reasons indexed by record identifier.

#### `?string $stopReason`

Reason for stopping processing (if applicable).

#### `float $executionTime`

Total execution time in seconds.

#### `int $memoryUsage`

Peak memory usage in bytes.

---

### Methods

#### `hasErrors(): bool`

Returns true if any records failed processing.

#### `hasSkipped(): bool`

Returns true if any records were skipped.

#### `wasStopped(): bool`

Returns true if processing was stopped before completion.

#### `getStopReason(): ?string`

Returns the reason for stopping processing.

#### `getSkipReason(string $recordId): ?string`

Returns the skip reason for a specific record.

#### `getSkipReasons(): array`

Returns all skip reasons.

#### `hasSkipReasons(): bool`

Returns true if there are any skip reasons.

#### `getSuccessRate(): float`

Returns the success rate as a percentage.

#### `getProcessedCount(): int`

Returns the total number of processed records (successful + failed).

#### `getExecutionTime(): float`

Returns the execution time in seconds.

#### `getMemoryUsage(): int`

Returns the memory usage in bytes.

#### `getFormattedMemoryUsage(): string`

Returns the memory usage in a human-readable format (e.g., "64 MB").

#### `summary(): string`

Returns a brief summary string.

#### `getDetailedSummary(): array`

Returns a detailed summary array with all statistics.

---

## Error Handling

### Exception Types

The Hunter may throw the following exceptions:

-   `InvalidArgumentException`: When invalid parameters are provided
-   `RuntimeException`: When execution fails due to runtime conditions

### Error Logging

When logging is enabled, errors are automatically logged with the following context:

```php
[
    'model' => 'App\\Models\\User',
    'id' => 123,
    'error' => 'Error message',
    'trace' => 'Stack trace...'
]
```

### Custom Error Handling

```php
$hunter->onError(function ($record, $exception, $hunter) {
    // Custom error handling
    Log::channel('batch-processing')->error("Failed to process {$record->id}", [
        'exception' => $exception,
        'record' => $record->toArray(),
    ]);

    // Mark for retry
    $record->update(['retry_count' => $record->retry_count + 1]);
});
```

---

## Best Practices

### 1. Use Appropriate Chunk Sizes

-   **Small operations (simple updates):** 1000-2000 records
-   **Medium operations (multiple queries):** 100-500 records
-   **Heavy operations (file processing, API calls):** 10-50 records

### 2. Always Test with Dry Run

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

## Static Methods

#### `for(string $modelClass): self`

Creates a new Hunter instance for the specified model class.

```php
Hunter::for(User::class)
Hunter::for(Order::class)
Hunter::for('App\Models\Product')

// Or using the helper
hunter(User::class)
hunter(Order::class)
hunter('App\Models\Product')
```

## Instance Methods

#### `find(string $column, mixed $operator = null, mixed $value = null): self`

Defines the search criteria for the query. Supports multiple syntaxes:

**Two-parameter syntax** (column and value):

```php
->find('status', 'active')
->find('created_at', now())
```

**Three-parameter syntax** (column, operator, and value):

```php
->find('created_at', '>=', now()->subDays(7))
->find('price', '>', 100)
->find('status', 'in', ['pending', 'processing'])
```

#### `modifyQueryUsing(Closure $callback): self`

Adds additional query constraints using the Filament-style approach. The callback must return a Builder instance. Multiple modifiers can be chained and will all be applied:

```php
->modifyQueryUsing(function (Builder $query): Builder {
    return $query->where('status', 'active');
})
->modifyQueryUsing(function ($builder): Builder {
    return $builder->whereHas('subscriptions');
})
```

#### `then(Closure $callback): self`

Defines the main action to execute for each found record. Multiple actions can be chained:

```php
->then(function (Order $order) {
    $order->process();
})
->then(function ($order) {
    $order->sendConfirmation();
})
```

#### `thenIf(Closure | bool $condition, Closure $callback, array $context = []): self`

Conditionally executes an action based on a condition. The condition is evaluated during configuration, providing optimal performance:

```php
// Execute only if condition is true
->thenIf(
    fn($order) => $order->total > 1000,
    fn($order) => $order->applyVipDiscount()
)

// With boolean condition
->thenIf(
    !now()->isWeekend(),
    fn($order) => $order->processImmediate()
)

// With additional context
->thenIf(
    fn($order, $config) => $order->priority >= $config['min_priority'],
    fn($order) => $order->processHighPriority(),
    ['min_priority' => 5]
)

// Always execute (condition defaults to true)
->thenIf(
    true,
    fn($order) => $order->sendConfirmation()
)
```

#### `onBeforeThen(Closure $callback): self`

Executes callbacks before the main actions. Useful for validation, preparation, or flow control:

```php
->onBeforeThen(function (Order $order, Hunter $hunter) {
    if (!$order->isValid()) {
        $hunter->fail($order, 'Invalid order');
        return;
    }
})
->onBeforeThen(function ($order) {
    Log::info("Processing order: {$order->id}");
})
```

#### `onAfterThen(Closure $callback): self`

Executes callbacks after the main actions complete successfully:

```php
->onAfterThen(function (Order $order) {
    $order->update(['processed_at' => now()]);
})
->onAfterThen(function ($order, $hunter) {
    NotificationService::sendProcessedNotification($order);
})
```

#### `onSuccess(Closure $callback): self`

Executes callbacks when all actions complete successfully:

```php
->onSuccess(function (Order $order) {
    Log::info("Order processed successfully: {$order->id}");
})
->onSuccess(function ($order) {
    MetricsService::incrementProcessedOrders();
})
```

#### `onError(Closure $callback): self`

Executes callbacks when any action throws an exception:

```php
->onError(function (Order $order, Throwable $e, Hunter $hunter) {
    $order->update(['status' => 'failed']);
})
->onError(function ($order, $exception) {
    NotificationService::sendErrorAlert($order, $exception);
})
```

#### `withLogging(string $context = 'hunter'): self`

Enables error logging with a custom context:

```php
->withLogging('order_processing')
->withLogging('campaign_dispatch')
```

## Flow Control Methods

#### `skip(?Model $record = null, ?string $reason = null): self`

Gracefully skips the current record without throwing an exception. Can be called from any callback:

```php
->onBeforeThen(function ($campaign, $hunter) {
    if (!$campaign->hasContacts()) {
        $hunter->skip($campaign, 'No contacts available');
        return;
    }
})
```

#### `fail(Model $record, string $reason): self`

Marks the current record as failed with a specific reason and skips remaining processing:

```php
->onBeforeThen(function ($campaign, $hunter) {
    if ($campaign->isExpired()) {
        $hunter->fail($campaign, 'Campaign has expired');
        return;
    }
})
```

#### `stop(?string $reason = null): self`

Stops processing all remaining records immediately:

```php
->onBeforeThen(function ($campaign, $hunter) {
    if (SystemStatus::isDown()) {
        $hunter->stop('System maintenance in progress');
        return;
    }
})
```

#### `getCurrentRecord(): ?Model`

Returns the currently processing record:

```php
$currentRecord = $hunter->getCurrentRecord();
```

#### `getResult(): ?HunterResult`

Returns the current result object during processing:

```php
$result = $hunter->getResult();
echo "Processed so far: {$result->successful}";
```

#### `hunt(): HunterResult`

Executes the configured actions and returns a result object with statistics.

## Flexible Parameter Resolution

Hunter uses Filament's `EvaluatesClosures` trait, providing flexible parameter resolution. You can use any combination of parameter names and types:

```php
// All of these parameter variations work:
->onBeforeThen(function (Campaign $campaign, Hunter $hunter) { })
->onBeforeThen(function ($record, $hunter) { })
->onBeforeThen(function ($model, Hunter $hunter) { })
->onBeforeThen(function (Campaign $campaign) { })
->onBeforeThen(function ($hunter) { })
->onBeforeThen(function () { })

// Error callbacks support multiple parameter names:
->onError(function ($record, Throwable $exception, $hunter) { })
->onError(function ($model, $error, Hunter $hunter) { })
->onError(function (Campaign $campaign, $e) { })

// Query modifiers:
->modifyQueryUsing(function (Builder $query) { })
->modifyQueryUsing(function ($builder) { })
```

## Available Parameter Names

**For regular callbacks:**

-   `$record`, `$model` - The current model instance
-   `$hunter` - The Hunter instance
-   `${modelName}` - The model class name (e.g., `$campaign` for `Campaign`)

**For error callbacks (additionally):**

-   `$exception`, `$error`, `$e` - The thrown exception

**For query modifiers:**

-   `$query`, `$builder` - The query builder instance

## Execution Flow

The Hunter class executes callbacks in the following order for each found record:

1. **All `onBeforeThen()` callbacks** (in registration order)
2. **All `then()` callbacks** (in registration order)
3. **All `onAfterThen()` callbacks** (in registration order)
4. **All `onSuccess()` callbacks** (in registration order)

If any exception occurs during steps 1-3:

-   **All `onError()` callbacks** are executed (in registration order)
-   The error is logged if logging is enabled

**Flow Control:** Any callback can call `skip()`, `fail()`, or `stop()` to control the execution flow without throwing exceptions.

## HunterResult Object

The `hunt()` method returns a `HunterResult` object with the following properties:

```php
$result = hunter(Order::class)->find('status', 'pending')->hunt();

$result->total;             // Total records found
$result->successful;        // Successfully processed records
$result->failed;            // Failed records
$result->skipped;           // Skipped records
$result->errors;            // Array of error messages keyed by record identifier
$result->skippedRecords;    // Array of skipped record identifiers
$result->skipReasons;       // Array of skip reasons keyed by record identifier
$result->stopReason;        // Reason for stopping (if applicable)

// Helper methods
$result->hasErrors();        // Returns true if any records failed
$result->hasSkipped();       // Returns true if any records were skipped
$result->wasStopped();       // Returns true if processing was stopped
$result->getSuccessRate();   // Returns success rate as percentage
$result->getProcessedCount(); // Returns successful + failed count
$result->summary();          // Returns summary string
$result->getDetailedSummary(); // Returns detailed array summary
```

## Notes

-   All callbacks are executed in the order they were registered
-   Flow control methods (`skip()`, `fail()`, `stop()`) provide graceful alternatives to throwing exceptions
-   Parameter resolution is flexible - use any parameter names that make sense for your use case
-   If any callback in the chain throws an exception, the remaining callbacks in that chain are skipped and `onError()` callbacks are executed
-   Logging is enabled by default and uses the Laravel Log facade
-   Multiple query modifiers are applied cumulatively
-   The `modifyQueryUsing()` method requires returning a Builder instance
-   Skipped records are tracked separately from failed records for better reporting
