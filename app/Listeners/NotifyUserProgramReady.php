<?php

namespace App\Listeners;

use App\Events\ProgramGenerated;
use App\Notifications\ProgramReady;

class NotifyUserProgramReady
{
    public function handle(ProgramGenerated $event): void
    {
        $event->userProgram->user->notify(new ProgramReady($event->userProgram));
    }
}
