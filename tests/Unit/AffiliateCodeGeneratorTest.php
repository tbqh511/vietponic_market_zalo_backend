<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Support\AffiliateCodeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\AffiliateCustomerFactory;
use Tests\TestCase;

class AffiliateCodeGeneratorTest extends TestCase
{
    use RefreshDatabase, AffiliateCustomerFactory;

    public function test_generates_8_char_code_with_safe_alphabet(): void
    {
        $code = AffiliateCodeGenerator::generate();
        $this->assertEquals(8, strlen($code));
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]+$/', $code);
    }

    public function test_codes_are_unique_across_multiple_generations(): void
    {
        $codes = [];
        for ($i = 0; $i < 30; $i++) {
            $code = AffiliateCodeGenerator::generate();
            $codes[] = $code;
            $this->makeCustomer(['affiliate_code' => $code]);
        }
        $this->assertCount(30, array_unique($codes));
    }
}
