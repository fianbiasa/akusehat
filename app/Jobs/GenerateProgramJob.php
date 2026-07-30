<?php

namespace App\Jobs;

use App\Events\ProgramGenerated;
use App\Models\UserProgram;
use App\Services\Program\ProgramGenerationService;
use App\Services\Program\ProgramGenerationStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * AI latency (2-30s) must never block the request/response cycle
 * (04-Architecture.md §5) - this is the only thing that calls
 * ProgramGenerationService::generateForDate() from an HTTP-triggered path.
 */
class GenerateProgramJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public UserProgram $userProgram, public string $date) {}

    public function handle(ProgramGenerationService $service): void
    {
        ProgramGenerationStatus::markPending($this->userProgram->id, $this->date);
        $isFirstGeneration = ! $this->userProgram->mealPlans()->exists();

        try {
            $service->generateForDate($this->userProgram, $this->date);
        } catch (\Throwable $e) {
            ProgramGenerationStatus::markFailed($this->userProgram->id, $this->date);
            throw $e;
        }

        ProgramGenerationStatus::markReady($this->userProgram->id, $this->date);

        if ($isFirstGeneration) {
            ProgramGenerated::dispatch($this->userProgram);
        }
    }
}
