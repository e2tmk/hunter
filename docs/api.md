# API Reference

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
- `$record`, `$model` - The current model instance
- `$hunter` - The Hunter instance
- `${modelName}` - The model class name (e.g., `$campaign` for `Campaign`)

**For error callbacks (additionally):**
- `$exception`, `$error`, `$e` - The thrown exception

**For query modifiers:**
- `$query`, `$builder` - The query builder instance

## Execution Flow

The Hunter class executes callbacks in the following order for each found record:

1. **All `onBeforeThen()` callbacks** (in registration order)
2. **All `then()` callbacks** (in registration order)
3. **All `onAfterThen()` callbacks** (in registration order)
4. **All `onSuccess()` callbacks** (in registration order)

If any exception occurs during steps 1-3:

- **All `onError()` callbacks** are executed (in registration order)
- The error is logged if logging is enabled

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

- All callbacks are executed in the order they were registered
- Flow control methods (`skip()`, `fail()`, `stop()`) provide graceful alternatives to throwing exceptions
- Parameter resolution is flexible - use any parameter names that make sense for your use case
- If any callback in the chain throws an exception, the remaining callbacks in that chain are skipped and `onError()` callbacks are executed
- Logging is enabled by default and uses the Laravel Log facade
- Multiple query modifiers are applied cumulatively
- The `modifyQueryUsing()` method requires returning a Builder instance
- Skipped records are tracked separately from failed records for better reporting
