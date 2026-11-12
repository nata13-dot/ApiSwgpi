<?php

namespace Tests\Unit;

use App\Support\SelfRegistrationRule as Rule;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SelfRegistrationRuleTest extends TestCase
{
    private array $rule;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-05 12:00:00');
        $this->rule = Rule::normalize(['career_code' => '24', 'domain' => 'smartin.tecnm.mx']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_accepts_every_generation_up_to_the_current_year(): void
    {
        foreach (['22240022', '26240100', 'b20240087', 'B26240001', 'c20240087', 'C26240001'] as $id) {
            $this->assertNull(Rule::numberViolation($this->rule, $id), $id);
        }
    }

    public function test_rejects_numbers_out_of_format(): void
    {
        $cases = ['27240001' => 'generación', '26250001' => 'clave de la carrera', '26240000' => '0000', '2624001' => '8 dígitos', '262400011' => '8 dígitos', 'a26240001' => '8 dígitos', 'd26240001' => '8 dígitos', 'bc26240001' => '8 dígitos', 'DS000001' => '8 dígitos'];
        foreach ($cases as $id => $message) {
            $this->assertStringContainsString($message, (string) Rule::numberViolation($this->rule, $id), $id);
        }
    }

    public function test_a_career_without_code_accepts_any_career_digits(): void
    {
        $this->assertNull(Rule::numberViolation(Rule::normalize([]), '26990001'));
    }

    public function test_email_must_match_the_number(): void
    {
        $this->assertNull(Rule::violation($this->rule, '26240100', 'L26240100@SMARTIN.TECNM.MX'));
        $this->assertNull(Rule::violation($this->rule, 'b26240100', 'lb26240100@smartin.tecnm.mx'));
        $this->assertNull(Rule::violation($this->rule, 'C26240100', 'lc26240100@smartin.tecnm.mx'));
        $this->assertNotNull(Rule::violation($this->rule, 'c26240100', 'lb26240100@smartin.tecnm.mx'));
        foreach (['l26240101@smartin.tecnm.mx', '26240100@smartin.tecnm.mx', 'l26240100@gmail.com'] as $email) {
            $this->assertNotNull(Rule::violation($this->rule, '26240100', $email), $email);
        }
    }

    public function test_old_settings_no_longer_apply_the_222_prefix(): void
    {
        $rule = Rule::normalize(['number_prefix' => '222', 'digits' => 8, 'domain' => 'smartin.tecnm.mx']);
        $this->assertNull(Rule::numberViolation($rule, '26240100'));
    }

    public function test_examples_never_contain_a_full_number(): void
    {
        $this->assertSame('lAA24NNNN@smartin.tecnm.mx', Rule::example($this->rule));
        $this->assertSame('lbAACCNNNN@smartin.tecnm.mx', Rule::example(Rule::normalize([]), true));
    }
}
