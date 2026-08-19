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
        $this->assertNotContains('user_governance', CheckRole::grantsForProfile(5));
    }

    public function test_only_general_administrator_governs_user_accounts(): void
    {
        $this->assertContains('user_governance', CheckRole::grantsForProfile(4));

        foreach ([1, 2, 3, 5, 6, 7] as $profileId) {
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
