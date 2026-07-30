<?php

namespace App\Events;

use App\Models\UserProgram;
use Illuminate\Foundation\Events\Dispatchable;

class ProgramGenerated
{
    use Dispatchable;

    public function __construct(public UserProgram $userProgram) {}
}
