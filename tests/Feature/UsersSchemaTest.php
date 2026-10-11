<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UsersSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function testADuplicateEmployeeNumberIsRejectedByTheDatabase(): void
    {
        User::factory()->create(['employee_no' => 'EMP-0001']);

        $this->expectException(UniqueConstraintViolationException::class);

        User::factory()->create(['employee_no' => 'EMP-0001']);
    }

    public function testASoftDeletedUserStillExistsWithWithTrashed(): void
    {
        $user = User::factory()->create();

        $user->delete();

        $this->assertNull(User::find($user->id));
        $this->assertNotNull(User::withTrashed()->find($user->id));
    }

    public function testAUserCanBeSavedWithoutAnEmail(): void
    {
        $user = User::factory()->create(['email' => null]);

        $this->assertNull($user->fresh()->email);
    }

    public function testTheUsernameIsTheFirstInitialPlusTheLastName(): void
    {
        $first = User::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);
        $second = User::factory()->create(['first_name' => 'Jane', 'last_name' => 'Doe']);
        $third = User::factory()->create(['first_name' => 'Jim', 'last_name' => 'Doe']);

        $this->assertSame('jdoe', $first->username);
        $this->assertSame('jdoe2', $second->username);
        $this->assertSame('jdoe3', $third->username);
        $this->assertSame('John Doe', $first->name);
    }

    public function testArchivedUsersKeepTheirUsernameReserved(): void
    {
        User::factory()->create(['first_name' => 'John', 'last_name' => 'Doe'])->delete();

        $user = User::factory()->create(['first_name' => 'Jane', 'last_name' => 'Doe']);

        $this->assertSame('jdoe2', $user->username);
    }

    public function testTheActiveScopeExcludesInactiveUsers(): void
    {
        $active = User::factory()->create();
        User::factory()->inactive()->create();

        $this->assertSame([$active->id], User::active()->pluck('id')->all());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function roleStates(): array
    {
        return [
            'admin' => ['admin'],
            'staff' => ['staff'],
            'worker' => ['worker'],
        ];
    }

    #[DataProvider('roleStates')]
    public function testRoleFactoryStatesAssignTheRole(string $state): void
    {
        $this->assertTrue(User::factory()->{$state}()->create()->hasRole($state));
    }

    public function testAdminFactoryStateHasTheAdminPermissions(): void
    {
        $this->assertTrue(User::factory()->admin()->create()->can('users.manage'));
    }

    public function testAUserWithAnInvalidRateIsNotCreated(): void
    {
        try {
            User::factory()->create(['hourly_rate' => '-5']);
            $this->fail('Expected an InvalidArgumentException.');
        } catch (InvalidArgumentException) {
            // expected
        }

        $this->assertSame(0, User::count());
    }

    public function testAVeryLongLastNameStillProducesAUsernameThatFitsTheColumn(): void
    {
        $user = User::factory()->create(['first_name' => 'Ana', 'last_name' => str_repeat('b', 100)]);

        $this->assertSame('a'.str_repeat('b', 44), $user->username);
    }
}
