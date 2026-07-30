<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'role_id',
        'name',
        'email',
        'phone',
        'password',
        'avatar_path',
        'timezone',
        'locale',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function hasRole(string $name): bool
    {
        return $this->role->name === $name;
    }

    public function hasPermission(string $name): bool
    {
        return $this->role->permissions()->where('name', $name)->exists();
    }

    public function onboardingSessions(): HasMany
    {
        return $this->hasMany(OnboardingSession::class);
    }

    public function healthProfile(): HasOne
    {
        return $this->hasOne(HealthProfile::class);
    }

    public function lifestyleProfile(): HasOne
    {
        return $this->hasOne(LifestyleProfile::class);
    }

    public function diseases(): HasMany
    {
        return $this->hasMany(UserDisease::class);
    }

    public function allergies(): HasMany
    {
        return $this->hasMany(UserAllergy::class);
    }

    public function medications(): HasMany
    {
        return $this->hasMany(UserMedication::class);
    }

    public function bodyMeasurements(): HasMany
    {
        return $this->hasMany(BodyMeasurement::class);
    }

    public function aiSettings(): HasMany
    {
        return $this->hasMany(UserAiSetting::class);
    }

    public function aiMemories(): HasMany
    {
        return $this->hasMany(AiMemory::class);
    }

    public function aiRecommendations(): HasMany
    {
        return $this->hasMany(AiRecommendation::class);
    }

    public function programs(): HasMany
    {
        return $this->hasMany(UserProgram::class);
    }

    public function activePrograms(): HasMany
    {
        return $this->programs()->where('status', 'active');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    public function weightLogs(): HasMany
    {
        return $this->hasMany(WeightLog::class);
    }

    public function waistLogs(): HasMany
    {
        return $this->hasMany(WaistLog::class);
    }

    public function bodyFatLogs(): HasMany
    {
        return $this->hasMany(BodyFatLog::class);
    }

    public function waterIntakeLogs(): HasMany
    {
        return $this->hasMany(WaterIntakeLog::class);
    }

    public function sleepLogs(): HasMany
    {
        return $this->hasMany(SleepLog::class);
    }

    public function progressPhotos(): HasMany
    {
        return $this->hasMany(ProgressPhoto::class);
    }

    public function healthScores(): HasMany
    {
        return $this->hasMany(HealthScore::class);
    }

    public function userAchievements(): HasMany
    {
        return $this->hasMany(UserAchievement::class);
    }

    public function achievements(): BelongsToMany
    {
        return $this->belongsToMany(Achievement::class, 'user_achievements')->withPivot('earned_at');
    }

    /**
     * weight_logs (Phase 7's dedicated daily-tracking table) takes
     * precedence over body_measurements (Phase 3's health-profile
     * snapshot table) per the forward-looking comment left in
     * MapOnboardingAnswersToHealthProfile - both tables coexist in
     * mysql.sql for different concerns, but anything asking "what does
     * this user currently weigh" should prefer the newer source.
     */
    public function latestWeightKg(): ?float
    {
        $value = $this->weightLogs()->latest('logged_at')->value('weight_kg')
            ?? $this->bodyMeasurements()->whereNotNull('weight_kg')->latest('measured_at')->value('weight_kg')
            ?? $this->healthProfile?->initial_weight_kg;

        return $value !== null ? (float) $value : null;
    }

    public function latestWaistCm(): ?float
    {
        $value = $this->waistLogs()->latest('logged_at')->value('waist_cm')
            ?? $this->bodyMeasurements()->whereNotNull('waist_cm')->latest('measured_at')->value('waist_cm');

        return $value !== null ? (float) $value : null;
    }

    public function coachProfile(): HasOne
    {
        return $this->hasOne(CoachProfile::class);
    }

    /** Members this user coaches (as the coach). */
    public function coachedMembers(): HasMany
    {
        return $this->hasMany(CoachMember::class, 'coach_id');
    }

    /** This user's coach-assignment history (as the member). */
    public function coachAssignments(): HasMany
    {
        return $this->hasMany(CoachMember::class, 'member_id');
    }

    public function activeCoachAssignment(): HasOne
    {
        return $this->hasOne(CoachMember::class, 'member_id')->where('status', 'active');
    }

    public function coachNotesWritten(): HasMany
    {
        return $this->hasMany(CoachNote::class, 'coach_id');
    }

    public function coachNotesAbout(): HasMany
    {
        return $this->hasMany(CoachNote::class, 'member_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function coachConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'coach_id');
    }

    public function reviewsReceived(): HasMany
    {
        return $this->hasMany(Review::class, 'coach_id');
    }

    public function reviewsGiven(): HasMany
    {
        return $this->hasMany(Review::class, 'member_id');
    }
}
