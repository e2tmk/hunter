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

    public function consoleTable(): void
    {
        if (! function_exists('\Laravel\Prompts\table')) {
            echo $this->summary() . "\n";

            return;
        }

        $headers = ['Metric', 'Value', 'Details'];
        $rows    = [
            ['Total Records', number_format($this->total), ''],
            ['Successful', number_format($this->successful), $this->total > 0 ? sprintf('%.1f%%', $this->getSuccessRate()) : ''],
            ['Failed', number_format($this->failed), $this->failed > 0 ? sprintf('%.1f%%', ($this->failed / $this->total) * 100) : ''],
            ['Skipped', number_format($this->skipped), $this->skipped > 0 ? sprintf('%.1f%%', ($this->skipped / $this->total) * 100) : ''],
        ];

        // Add execution metrics if available
        if ($this->executionTime > 0) {
            $rows[] = ['Execution Time', sprintf('%.2fs', $this->executionTime), ''];
        }

        if ($this->memoryUsage > 0) {
            $rows[] = ['Memory Usage', $this->getFormattedMemoryUsage(), ''];
        }

        $rows[] = $this->wasStopped() ? ['Status', 'Stopped', $this->stopReason] : ['Status', 'Completed', ''];

        if ($this->executionTime > 0 && $this->total > 0) {
            $rate   = $this->total / $this->executionTime;
            $rows[] = ['Processing Rate', sprintf('%.1f records/sec', $rate), ''];
        }

        \Laravel\Prompts\table(
            headers: $headers,
            rows: $rows
        );

        if ($this->hasErrors()) {
            echo "\n";
            \Laravel\Prompts\warning("⚠️  {$this->failed} record(s) failed to process. Check logs for details.");
        }

        if ($this->hasSkipped()) {
            echo "\n";
            \Laravel\Prompts\info("ℹ️  {$this->skipped} record(s) were skipped.");

            if ($this->hasSkipReasons()) {
                echo "\nSkip reasons:\n";

                foreach (array_count_values($this->skipReasons) as $reason => $count) {
                    echo "  • {$reason}: {$count} record(s)\n";
                }
            }
        }
    }
}
