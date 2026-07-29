<?php

namespace App\Providers;

use App\Events\OnboardingCompleted;
use App\Listeners\DispatchInitialProgramGeneration;
use App\Listeners\MapOnboardingAnswersToHealthProfile;
use App\Models\BodyMeasurement;
use App\Models\LifestyleProfile;
use App\Observers\BodyMeasurementObserver;
use App\Observers\LifestyleProfileObserver;
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

        // Health-profile mapping must run before program generation is
        // dispatched, since the (future) Rule Engine will read from it.
        Event::listen(OnboardingCompleted::class, MapOnboardingAnswersToHealthProfile::class);
        Event::listen(OnboardingCompleted::class, DispatchInitialProgramGeneration::class);

        BodyMeasurement::observe(BodyMeasurementObserver::class);
        LifestyleProfile::observe(LifestyleProfileObserver::class);
    }
}
