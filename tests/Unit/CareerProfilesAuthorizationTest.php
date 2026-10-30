<?php

namespace Tests\Unit;

use App\Http\Middleware\CheckRole;
use PHPUnit\Framework\TestCase;

class CareerProfilesAuthorizationTest extends TestCase
{
    public function test_career_head_inherits_career_administration(): void
    {
        $this->assertContains('admin', CheckRole::grantsForProfile(5));
        $this->assertContains('academic_manager', CheckRole::grantsForProfile(5));
        $this->assertContains('project_manager', CheckRole::grantsForProfile(5));
        // Desde 2026-10: la jefatura registra usuarios de su carrera (UserController limita los perfiles).
        $this->assertContains('user_governance', CheckRole::grantsForProfile(5));
    }

    public function test_only_general_administrator_head_and_assistant_govern_user_accounts(): void
    {
        foreach ([4, 5, 6] as $profileId) {
            $this->assertContains('user_governance', CheckRole::grantsForProfile($profileId));
        }
        foreach ([2, 3, 7] as $profileId) {
            $this->assertNotContains('user_governance', CheckRole::grantsForProfile($profileId));
        }
    }

    public function test_assistant_cannot_use_critical_administration(): void
    {
        $grants = CheckRole::grantsForProfile(6);

        $this->assertNotContains('admin', $grants);
        $this->assertContains('academic_manager', $grants);
        $this->assertContains('project_manager', $grants);
    }

    public function test_project_coordinator_is_limited_to_project_management(): void
    {
        $grants = CheckRole::grantsForProfile(7);

        $this->assertNotContains('admin', $grants);
        $this->assertNotContains('academic_manager', $grants);
        $this->assertContains('project_manager', $grants);
    }
}
