<?php

namespace App\Support;

use App\Models\Career;
use App\Models\User;

class CareerContext
{
    private ?Career $career = null;
    private ?int $profileId = null;
    private ?User $user = null;
    private ?int $simulatedProfile = null;

    public function set(User $user, Career $career, int $profileId, ?int $simulatedProfile = null): void
    {
        $this->user = $user;
        $this->career = $career;
        $this->profileId = $profileId;
        $this->simulatedProfile = $simulatedProfile;
    }

    /** Perfil que el administrador general está simulando (sólo lectura), o null. */
    public function simulatedProfile(): ?int
    {
        return $this->simulatedProfile;
    }

    public function career(): ?Career
    {
        return $this->career;
    }

    public function careerId(): ?int
    {
        return $this->career?->getKey();
    }

    public function profileId(): ?int
    {
        return $this->profileId;
    }

    public function user(): ?User
    {
        return $this->user;
    }

    public function isGeneralAdmin(): bool
    {
        return $this->user?->globalProfileId() === 4;
    }
}
