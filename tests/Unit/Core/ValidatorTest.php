<?php
// tests/Unit/Core/ValidatorTest.php
declare(strict_types=1);
namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Smallwork\Core\Validator;

class ValidatorTest extends TestCase
{
    public function test_required_passes(): void
    {
        $v = Validator::make(['name' => 'Alice'], ['name' => 'required']);
        $this->assertTrue($v->passes());
        $this->assertEmpty($v->errors());
    }

    public function test_required_fails_on_missing(): void
    {
        $v = Validator::make([], ['name' => 'required']);
        $this->assertFalse($v->passes());
        $this->assertArrayHasKey('name', $v->errors());
    }

    public function test_required_fails_on_empty_string(): void
    {
        $v = Validator::make(['name' => ''], ['name' => 'required']);
        $this->assertFalse($v->passes());
    }

    public function test_string_rule(): void
    {
        $v = Validator::make(['name' => 'Alice'], ['name' => 'required|string']);
        $this->assertTrue($v->passes());

        $v2 = Validator::make(['name' => 123], ['name' => 'string']);
        $this->assertFalse($v2->passes());
    }

    public function test_email_rule(): void
    {
        $v = Validator::make(['email' => 'alice@test.com'], ['email' => 'required|email']);
        $this->assertTrue($v->passes());

        $v2 = Validator::make(['email' => 'not-an-email'], ['email' => 'email']);
        $this->assertFalse($v2->passes());
    }

    public function test_numeric_rule(): void
    {
        $v = Validator::make(['age' => '25'], ['age' => 'numeric']);
        $this->assertTrue($v->passes());

        $v2 = Validator::make(['age' => 25], ['age' => 'numeric']);
        $this->assertTrue($v2->passes());

        $v3 = Validator::make(['age' => 'abc'], ['age' => 'numeric']);
        $this->assertFalse($v3->passes());
    }

    public function test_min_rule_for_strings(): void
    {
        $v = Validator::make(['name' => 'Al'], ['name' => 'string|min:3']);
        $this->assertFalse($v->passes());

        $v2 = Validator::make(['name' => 'Alice'], ['name' => 'string|min:3']);
        $this->assertTrue($v2->passes());
    }

    public function test_max_rule_for_strings(): void
    {
        $v = Validator::make(['name' => 'Alice'], ['name' => 'string|max:3']);
        $this->assertFalse($v->passes());

        $v2 = Validator::make(['name' => 'Al'], ['name' => 'string|max:3']);
        $this->assertTrue($v2->passes());
    }

    public function test_min_max_for_numbers(): void
    {
        $v = Validator::make(['age' => 5], ['age' => 'numeric|min:10']);
        $this->assertFalse($v->passes());

        $v2 = Validator::make(['age' => 15], ['age' => 'numeric|min:10|max:100']);
        $this->assertTrue($v2->passes());
    }

    public function test_in_rule(): void
    {
        $v = Validator::make(['role' => 'admin'], ['role' => 'in:admin,user,service']);
        $this->assertTrue($v->passes());

        $v2 = Validator::make(['role' => 'superadmin'], ['role' => 'in:admin,user']);
        $this->assertFalse($v2->passes());
    }

    public function test_multiple_fields(): void
    {
        $v = Validator::make(
            ['name' => 'Alice', 'email' => 'a@b.com', 'age' => '30'],
            ['name' => 'required|string|min:2', 'email' => 'required|email', 'age' => 'required|numeric']
        );
        $this->assertTrue($v->passes());
    }

    public function test_multiple_errors(): void
    {
        $v = Validator::make(
            [],
            ['name' => 'required', 'email' => 'required|email']
        );
        $this->assertFalse($v->passes());
        $errors = $v->errors();
        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('email', $errors);
    }

    public function test_optional_field_skipped_when_missing(): void
    {
        $v = Validator::make([], ['nickname' => 'string|min:2']);
        $this->assertTrue($v->passes()); // No 'required', so missing is OK
    }

    public function test_array_rule(): void
    {
        $v = Validator::make(['tags' => ['a', 'b']], ['tags' => 'array']);
        $this->assertTrue($v->passes());

        $v2 = Validator::make(['tags' => 'not-array'], ['tags' => 'array']);
        $this->assertFalse($v2->passes());
    }
}
