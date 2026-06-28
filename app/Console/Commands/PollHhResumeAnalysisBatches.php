<?php

namespace App\Console\Commands;

use App\Services\Hh\HhResumeBatchAnalysisService;
use Illuminate\Console\Command;
use Throwable;

class PollHhResumeAnalysisBatches extends Command
{
    protected $signature = 'hh:poll-resume-analysis';

    protected $description = 'Poll OpenAI Batch API jobs and apply HH resume analysis results.';

    public function handle(HhResumeBatchAnalysisService $service): int
    {
        try {
            $summary = $service->pollPending();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'HH resume analysis batches checked: %d, updated: %d, failed rows: %d.',
            $summary['checked'],
            $summary['updated'],
            $summary['failed'],
        ));

        return self::SUCCESS;
    }
}
