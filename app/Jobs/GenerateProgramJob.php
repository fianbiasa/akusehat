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

    /**
     * Default queue worker timeout (60s) isn't enough: this makes 2
     * sequential real AI calls (meal_plan, workout_plan), each up to 3
     * attempts (docs/06-AI-Provider-Interface.md retry policy) - found via
     * live smoke testing with a real API key for the first time, every
     * prior run had used the instant Rule-Engine fallback with no
     * provider configured, so this never surfaced before.
     */
    public int $timeout = 240;

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
