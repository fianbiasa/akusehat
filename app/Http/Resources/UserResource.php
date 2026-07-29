<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'onboarding_completed_at' => $this->onboarding_completed_at,
            'role' => [
                'name' => $this->role->name,
                'label' => $this->role->label,
            ],
            'permissions' => $this->role->permissions->pluck('name'),
        ];
    }
}
