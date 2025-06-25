<?php

declare(strict_types = 1);

namespace Hunter;

class HunterResult
{
    public int $total = 0;

    public int $successful = 0;

    public int $failed = 0;

    public int $skipped = 0;

    public array $errors = [];

    public array $skippedRecords = [];

    public array $skipReasons = [];

    public ?string $stopReason = null;

    public float $executionTime = 0;

    public int $memoryUsage = 0;

    public function hasErrors(): bool
    {
        return $this->failed > 0;
    }

    public function hasSkipped(): bool
    {
        return $this->skipped > 0;
    }

    public function wasStopped(): bool
    {
        return $this->stopReason !== null;
    }

    public function getStopReason(): ?string
    {
        return $this->stopReason;
    }

    public function getSkipReason(string $recordId): ?string
    {
        return $this->skipReasons[$recordId] ?? null;
    }

    public function getSkipReasons(): array
    {
        return $this->skipReasons;
    }

    public function hasSkipReasons(): bool
    {
        return $this->skipReasons !== [];
    }

    public function getSuccessRate(): float
    {
        if ($this->total === 0) {
            return 0.0;
        }

        return ($this->successful / $this->total) * 100;
    }

    public function getProcessedCount(): int
    {
        return $this->successful + $this->failed;
    }

    public function summary(): string
    {
        $summary = "Total: {$this->total}, Successful: {$this->successful}, Failed: {$this->failed}, Skipped: {$this->skipped}";

        if ($this->wasStopped()) {
            $summary .= " (Stopped: {$this->stopReason})";
        }

        return $summary;
    }

    public function getDetailedSummary(): array
    {
        return [
            'total'           => $this->total,
            'successful'      => $this->successful,
            'failed'          => $this->failed,
            'skipped'         => $this->skipped,
            'errors'          => $this->errors,
            'skipped_records' => $this->skippedRecords,
            'skip_reasons'    => $this->skipReasons,
            'stop_reason'     => $this->stopReason,
            'success_rate'    => $this->getSuccessRate(),
            'processed_count' => $this->getProcessedCount(),
            'execution_time'  => $this->executionTime,
            'memory_usage'    => $this->memoryUsage,
        ];
    }

    public function getExecutionTime(): float
    {
        return $this->executionTime;
    }

    public function getMemoryUsage(): int
    {
        return $this->memoryUsage;
    }

    public function getFormattedMemoryUsage(): string
    {
        $bytes = $this->memoryUsage;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
