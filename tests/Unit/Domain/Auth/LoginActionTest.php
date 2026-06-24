<?php

namespace Tests\Unit\Domain\Auth;

use App\Domain\Auth\Actions\IssueAuthTokensAction;
use App\Domain\Auth\Actions\LoginAction;
use App\Domain\Auth\DTOs\AuthTokenDTO;
use App\Domain\Auth\Exceptions\InvalidCredentialsException;
use App\Models\User\User;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LoginActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_token_with_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);
        $user->profile()->create(['first_name' => 'Jane', 'last_name' => 'Doe']);

        $expectedDto = new AuthTokenDTO('access', 'refresh', 'Bearer', 900);

        $issueAction = Mockery::mock(IssueAuthTokensAction::class);
        $issueAction->shouldReceive('execute')->once()->andReturn($expectedDto);

        $action = new LoginAction($issueAction);
        $result = $action->execute($user->email, 'secret123');

        $this->assertSame($expectedDto, $result);
    }

    public function test_login_throws_exception_for_wrong_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct')]);

        $issueAction = Mockery::mock(IssueAuthTokensAction::class);
        $action = new LoginAction($issueAction);

        $this->expectException(InvalidCredentialsException::class);
        $action->execute($user->email, 'wrong');
    }

    public function test_login_throws_exception_for_unknown_email(): void
    {
        $issueAction = Mockery::mock(IssueAuthTokensAction::class);
        $action = new LoginAction($issueAction);

        $this->expectException(InvalidCredentialsException::class);
        $action->execute('nobody@example.com', 'password');
    }
}
