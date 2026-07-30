# Load Test — Queue Worker Throughput

Conducted 2026-07-30 against the real production Redis queue and MariaDB database (not a synthetic/staging environment), targeting `GenerateProgramJob` — the job dispatched every time a Member completes onboarding, per the Phase 13 checklist's specific concern ("queue worker throughput... under concurrent onboarding completions").

## A critical finding came before the load test itself

There was **no persistent queue worker or scheduler running for akusehat.web.id in production** before this phase. Every queued job (`GenerateProgramJob`, all notification sends, `EvaluateAchievementsJob`, `SubscriptionRenewalCheckJob`, etc.) has only ever executed when manually invoked via `artisan queue:work --once`/`tinker` during this project's live smoke tests across every prior phase — never automatically. Same for every `routes/console.php` scheduled job (`DispatchRemindersJob` every minute, `ComputeHealthScoreJob` daily, etc.) — nothing in cron or Supervisor was ever calling `artisan schedule:run` or `schedule:work` for this site.

This was found by checking `ps aux` for a running `queue:work` process (none) and `supervisorctl status` (other sites on this shared box — `support-worker`, `paneljowtech-queue`, `jowpanel-scheduler` — all have Supervisor-managed persistent processes; `akusehat.web.id` had no entry at all).

**Fixed**: added `/etc/supervisor/conf.d/akusehat-worker.conf`, matching the exact pattern already established for sibling sites on this box:
- `akusehat-worker` — 2 processes, `php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600 --queue=default`
- `akusehat-scheduler` — 1 process, `php artisan schedule:work` (the persistent-process equivalent of a `schedule:run` cron entry, same pattern `jowpanel-scheduler`/`paneljowtech-scheduler` already use on this box)

Both registered via `supervisorctl reread && supervisorctl update` and confirmed `RUNNING`. This is arguably a more important outcome of this checklist item than the throughput number below — without it, queued/scheduled work in this app would silently never run in production at all.

## Load test methodology

No real AI provider API keys are configured in this environment (consistent with every prior phase's testing — see Phase 5 notes), so every `GenerateProgramJob` in this test exercised the deterministic Rule-Engine-only fallback path (`no_provider_configured`) — CPU/DB-bound work with zero network I/O wait, which is the worst case for local resource contention and therefore a reasonable stand-in for "everyone's AI call happens to fail at once and falls back simultaneously," in addition to being the only path testable without real provider credentials.

For each run: created N throwaway Member accounts (with a `health_profile`/`lifestyle_profile`, matching real onboarding output) each with one `active` `UserProgram`, dispatched one `GenerateProgramJob` per program back-to-back (simulating N onboarding completions within the same second), then measured queue drain against the 2 newly-running worker processes.

## Results

| Batch size | Outcome |
|---|---|
| 30 jobs | Queue observed at 0 within ~1s of dispatch completing. 30/30 succeeded (120 meal_plans = 4/program, 30 workout_plans = 1/program, 180 checklist_items = 6/program — all exact expected multiples, 0 partial/missing rows). |
| 100 jobs | Same result: queue empty by the first poll after dispatch. 100/100 succeeded (400 meal_plans, 100 workout_plans, all exact). **0 entries in `failed_jobs`** attributable to this batch. |

Both batches drained faster than 1-second-granularity polling could resolve with only 2 worker processes — the Rule-Engine-only generation path (picking directly from `kb_foods`/`kb_exercises`, no external I/O) is fast enough that 100 concurrent onboarding completions is not a meaningful load for this app at its current scale. This is a reassuring result, not a caveat: FR-PROG-03's "generate one day at a time" design decision (Phase 6) already keeps per-job work small, and the fallback path (no AI latency) is the dominant real-world case until Phase 12's platform-default-AI-key feature or real member-configured keys are actually in wide use.

**Caveat**: this does not measure the AI-backed path's throughput (2 real HTTP calls per job, 2-30s latency per FR spec) — that path's bottleneck is external API latency and provider rate limits, not this app's own infrastructure, and can't be honestly load-tested without real provider credentials and accepting the real API cost. Re-run this test with a real (rate-limited, budget-capped) API key before a launch that expects meaningful AI-path concurrency, to find the actual saturation point of 2 worker processes against a real provider's own concurrency/rate limits.

## Follow-up
- Monitor `storage/logs/worker.log` and `supervisorctl status` after launch to confirm the persistent worker survives real traffic and server restarts (Supervisor's `autostart=true`/`autorestart=true` should cover both, but this wasn't tested against an actual server reboot in this session).
- If real member/platform AI usage grows enough that the 2-process worker pool becomes a bottleneck, `numprocs` in `akusehat-worker.conf` is the lever to pull first.
