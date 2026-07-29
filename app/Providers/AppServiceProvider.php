<?php

namespace App\Providers;

use App\Events\OnboardingCompleted;
use App\Listeners\DispatchInitialProgramGeneration;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // This MariaDB instance's InnoDB key-length limit rejects a utf8mb4
        // varchar(255) primary/unique key (used by framework tables like
        // password_reset_tokens). Our own migrations specify explicit
        // shorter lengths matching the Database Dictionary, so this only
        // affects bare $table->string() columns.
        Schema::defaultStringLength(191);

        Event::listen(OnboardingCompleted::class, DispatchInitialProgramGeneration::class);
    }
}
