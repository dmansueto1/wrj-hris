<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PasswordPolicyTest extends TestCase
{
    /**
     * @return array<string, array{string, bool}>
     */
    public static function passwords(): array
    {
        return [
            'common weak password' => ['password123', false],
            'too short' => ['Sh0rt!pw', false],
            'no symbol' => ['Str0ngPassw0rd', false],
            'no number' => ['Strong!Password', false],
            'no upper case' => ['str0ng!passw0rd', false],
            'strong' => ['Str0ng!Passw0rd', true],
        ];
    }

    #[DataProvider('passwords')]
    public function testThePasswordPolicy(string $password, bool $passes): void
    {
        $validator = Validator::make(['password' => $password], ['password' => Password::defaults()]);

        $this->assertSame($passes, $validator->passes());
    }
}
