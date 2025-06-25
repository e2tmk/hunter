<?php

declare(strict_types = 1);

namespace Hunter;

use Closure;
use Hunter\Concerns\EvaluatesClosures;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class Hunter
{
    use EvaluatesClosures;

    protected string $modelClass;

    protected string $column;

    protected mixed $value;

    protected string $operator = '=';

    protected ?Collection $queryModifiers = null;

    protected ?Collection $individualActions = null;

    protected ?Collection $beforeThenCallbacks = null;

    protected ?Collection $afterThenCallbacks = null;

    protected ?Collection $successCallbacks = null;

    protected ?Collection $errorCallbacks = null;

    protected bool $logErrors = true;

    protected string $logContext = 'hunter';

    protected bool $shouldSkipCurrentRecord = false;

    protected bool $shouldStopProcessing = false;

    protected ?string $skipReason = null;

    protected ?string $stopReason = null;

    protected ?Model $currentRecord = null;

    protected ?HunterResult $currentResult = null;

    protected int $chunk = 250;

    public function __construct()
    {
        $this->queryModifiers      = collect();
        $this->individualActions   = collect();
        $this->beforeThenCallbacks = collect();
        $this->afterThenCallbacks  = collect();
        $this->successCallbacks    = collect();
        $this->errorCallbacks      = collect();
    }

    public static function for(string $modelClass): self
    {
        if (! class_exists($modelClass)) {
            throw new \InvalidArgumentException("Model class '{$modelClass}' does not exist");
        }

        if (! is_subclass_of($modelClass, Model::class)) {
            throw new \InvalidArgumentException("Class '{$modelClass}' must extend Illuminate\Database\Eloquent\Model");
        }

        $instance             = new self();
        $instance->modelClass = $modelClass;

        return $instance;
    }

    public function find(string $column, mixed $operator = null, mixed $value = null): self
    {
        $this->column = $column;

        if (blank($value) && filled($operator)) {
            $this->value = $operator;

            return $this;
        }

        $this->operator = $operator ?? '=';
        $this->value    = $value;

        return $this;
    }

    public function modifyQueryUsing(Closure $callback): self
    {
        $this->queryModifiers->push($callback);

        return $this;
    }

    public function then(Closure $callback): self
    {
        $this->individualActions->push($callback);

        return $this;
    }

    public function thenIf(Closure | bool $condition, Closure $callback, array $context = []): self
    {
        if ($condition instanceof Closure) {
            $condition = $this->evaluate($condition, array_merge([
                'hunter' => $this,
                'model'  => $this->currentRecord,
                'record' => $this->currentRecord,
            ], $context));
        }

        if (! $condition) {
            return $this;
        }

        $this->individualActions->push($callback);

        return $this;
    }

    public function onBeforeThen(Closure $callback): self
    {
        $this->beforeThenCallbacks->push($callback);

        return $this;
    }

    public function onAfterThen(Closure $callback): self
    {
        $this->afterThenCallbacks->push($callback);

        return $this;
    }

    public function onSuccess(Closure $callback): self
    {
        $this->successCallbacks->push($callback);

        return $this;
    }

    public function onError(Closure $callback): self
    {
        $this->errorCallbacks->push($callback);

        return $this;
    }

    public function withLogging(string $context = 'hunter'): self
    {
        $this->logErrors  = true;
        $this->logContext = $context;

        return $this;
    }

    public function withoutLogging(): self
    {
        $this->logErrors = false;

        return $this;
    }

    public function chunk(int $size): self
    {
        if ($size <= 0) {
            throw new \InvalidArgumentException('Chunk size must be greater than zero');
        }

        $this->chunk = $size;

        return $this;
    }

    public function skip(?Model $record = null, ?string $reason = null): self
    {
        $this->shouldSkipCurrentRecord = true;
        $this->skipReason              = $reason;

        if ($record && $this->currentResult) {
            $this->currentResult->skipped++;
            $recordId                              = $this->getRecordIdentifier($record);
            $this->currentResult->skippedRecords[] = $recordId;

            if (filled($reason)) {
                $this->currentResult->skipReasons[$recordId] = $reason;
            }
        }

        return $this;
    }

    public function stop(?string $reason = null): self
    {
        $this->shouldStopProcessing = true;
        $this->stopReason           = $reason;

        if ($this->currentResult && $reason) {
            $this->currentResult->stopReason = $reason;
        }

        return $this;
    }

    public function fail(Model $record, string $reason): self
    {
        if (filled($this->currentResult) && $this->currentResult instanceof HunterResult) {
            $this->currentResult->failed++;
            $this->currentResult->errors[$this->getRecordIdentifier($record)] = $reason;
        }

        $this->shouldSkipCurrentRecord = true;

        return $this;
    }

    public function getCurrentRecord(): ?Model
    {
        return $this->currentRecord;
    }

    public function getResult(): ?HunterResult
    {
        return $this->currentResult;
    }

    public function getSkipReason(): ?string
    {
        return $this->skipReason;
    }

    public function getStopReason(): ?string
    {
        return $this->stopReason;
    }

    public function hunt(): HunterResult
    {
        $query                  = $this->buildQuery();
        $result                 = new HunterResult();
        $result->total          = $query->count();
        $result->skipped        = 0;
        $result->skippedRecords = [];
        $result->skipReasons    = [];

        $this->currentResult = $result;

        if ($result->total === 0) {
            return $result;
        }

        $query->chunk($this->chunk, function ($records) use ($result): void {
            $records->each(function ($record) use ($result): void {
                // Reset flow control for each record
                $this->shouldSkipCurrentRecord = false;
                $this->skipReason              = null;
                $this->currentRecord           = $record;

                // Check if we should stop processing entirely
                if ($this->shouldStopProcessing) {
                    $result->skipped++;
                    $recordId                 = $this->getRecordIdentifier($record);
                    $result->skippedRecords[] = $recordId;

                    if (filled($this->stopReason)) {
                        $result->skipReasons[$recordId] = "Stopped: {$this->stopReason}";
                    }

                    return;
                }

                try {
                    // Hook: Before Then
                    $this->executeCallbacks($this->beforeThenCallbacks, $record);

                    // Check if the record was skipped in onBeforeThen
                    if ($this->shouldSkipCurrentRecord) {
                        $this->handleSkipReason($record, $result);

                        return;
                    }

                    // Main actions
                    $this->executeCallbacks($this->individualActions, $record);

                    // Check if the record was skipped in then
                    if ($this->shouldSkipCurrentRecord) {
                        $this->handleSkipReason($record, $result);

                        return;
                    }

                    // Hook: After Then
                    $this->executeCallbacks($this->afterThenCallbacks, $record);

                    // Check if the record was skipped in onAfterThen
                    if ($this->shouldSkipCurrentRecord) {
                        $this->handleSkipReason($record, $result);

                        return;
                    }

                    $result->successful++;

                    // Success callbacks
                    $this->executeCallbacks($this->successCallbacks, $record);
                } catch (Throwable $e) {
                    // Skip if the record was already marked as failed via fail() method
                    if ($this->shouldSkipCurrentRecord) {
                        return;
                    }

                    $result->failed++;
                    $result->errors[$this->getRecordIdentifier($record)] = $e->getMessage();

                    if ($this->logErrors) {
                        Log::error("Hunter error in {$this->logContext}", [
                            'model' => $record::class,
                            'id'    => $record->getKey(),
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }

                    // Error callbacks
                    $this->executeErrorCallbacks($this->errorCallbacks, $record, $e);
                }
            });
        });

        $this->currentRecord = null;
        $this->currentResult = null;

        return $result;
    }

    protected function handleSkipReason(Model $record, HunterResult $result): void
    {
        if (blank($this->skipReason)) {
            return;
        }

        $recordId                       = $this->getRecordIdentifier($record);
        $result->skipReasons[$recordId] = $this->skipReason;
    }

    protected function executeCallbacks(Collection $callbacks, Model $record): void
    {
        $callbacks->each(function ($callback) use ($record): void {
            if ($this->shouldSkipCurrentRecord || $this->shouldStopProcessing) {
                return;
            }

            $this->evaluate($callback, [
                'record'                       => $record,
                'hunter'                       => $this,
                'model'                        => $record,
                $this->getModelParameterName() => $record,
            ]);
        });
    }

    protected function executeErrorCallbacks(Collection $callbacks, Model $record, Throwable $exception): void
    {
        $callbacks->each(function ($callback) use ($record, $exception): void {
            if ($this->shouldSkipCurrentRecord || $this->shouldStopProcessing) {
                return;
            }

            $this->evaluate($callback, [
                'record'                       => $record,
                'hunter'                       => $this,
                'model'                        => $record,
                'exception'                    => $exception,
                'error'                        => $exception,
                'e'                            => $exception,
                $this->getModelParameterName() => $record,
            ]);
        });
    }

    protected function buildQuery(): Builder
    {
        /** @var Model $model */
        $model = $this->modelClass;

        $query = $model::query()
            ->where($this->column, $this->operator, $this->value);

        $this->queryModifiers->each(function ($callback) use (&$query): void {
            $result = $this->evaluate($callback, [
                'query'   => $query,
                'builder' => $query,
            ]);

            if (! $result instanceof Builder) {
                throw new \InvalidArgumentException('modifyQueryUsing callback must return an instance of Illuminate\Database\Eloquent\Builder');
            }

            $query = $result;
        });

        return $query;
    }

    protected function getRecordIdentifier(Model $record): string
    {
        $class = class_basename($record);

        return strtolower($class) . '_' . $record->getKey();
    }

    protected function getModelParameterName(): string
    {
        $class = class_basename($this->modelClass);

        return lcfirst($class);
    }
}
