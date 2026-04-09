<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTOs\TwoFactorSetupDTO;
use App\Models\Admin;
use App\Notifications\AdminPasswordResetNotification;
use App\Services\AdminAuthService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Mockery;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AdminAuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private AdminAuthService $service;
    private Google2FA $google2fa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->google2fa = Mockery::mock(Google2FA::class);
        $this->service   = new AdminAuthService($this->google2fa);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeAdmin(array $attrs = []): Admin
    {
        return Admin::create(array_merge([
            'name'     => 'Admin Test',
            'email'    => 'admin@nexu.com',
            'password' => Hash::make('correct-password'),
            'role'     => 'super_admin',
        ], $attrs));
    }

    private function makeAdminWith2FA(array $attrs = []): Admin
    {
        $admin = $this->makeAdmin(array_merge([
            'two_factor_secret'       => 'BASE32SECRET',
            'two_factor_confirmed_at' => now(),
        ], $attrs));

        return $admin;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 1. attemptLogin
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_attempt_login_succeeds_without_2fa(): void
    {
        $admin = $this->makeAdmin();

        $result = $this->service->attemptLogin('admin@nexu.com', 'correct-password', '127.0.0.1');

        $this->assertTrue($result->success);
        $this->assertFalse($result->requiresTwoFactor);
        $this->assertNull($result->error);
        $this->assertTrue(Auth::check());
    }

    /** @test */
    public function test_attempt_login_updates_last_login_on_success(): void
    {
        $admin = $this->makeAdmin();
        $this->assertNull($admin->last_login_at);

        $this->service->attemptLogin('admin@nexu.com', 'correct-password', '192.168.1.50');

        $admin->refresh();
        $this->assertNotNull($admin->last_login_at);
        $this->assertEquals('192.168.1.50', $admin->last_login_ip);
    }

    /** @test */
    public function test_attempt_login_fails_on_wrong_password(): void
    {
        $this->makeAdmin();

        $result = $this->service->attemptLogin('admin@nexu.com', 'wrong-password', '127.0.0.1');

        $this->assertFalse($result->success);
        $this->assertEquals('Credenciales inválidas.', $result->error);
        $this->assertFalse(Auth::check());
    }

    /** @test */
    public function test_attempt_login_fails_on_unknown_email(): void
    {
        $result = $this->service->attemptLogin('noexiste@nexu.com', 'any-password', '127.0.0.1');

        $this->assertFalse($result->success);
        $this->assertEquals('Credenciales inválidas.', $result->error);
    }

    /** @test */
    public function test_attempt_login_wrong_and_unknown_return_identical_error(): void
    {
        $this->makeAdmin();

        $wrongPasswordResult = $this->service->attemptLogin('admin@nexu.com', 'bad', '127.0.0.1');
        $unknownEmailResult  = $this->service->attemptLogin('ghost@nexu.com', 'bad', '127.0.0.1');

        $this->assertEquals($wrongPasswordResult->error, $unknownEmailResult->error);
    }

    /** @test */
    public function test_attempt_login_with_2fa_returns_requires_two_factor(): void
    {
        $admin = $this->makeAdminWith2FA();

        $result = $this->service->attemptLogin('admin@nexu.com', 'correct-password', '127.0.0.1');

        $this->assertTrue($result->success);
        $this->assertTrue($result->requiresTwoFactor);
        $this->assertFalse(Auth::check()); // not logged in yet
    }

    /** @test */
    public function test_attempt_login_with_2fa_stores_pending_session(): void
    {
        $admin = $this->makeAdminWith2FA();

        $this->service->attemptLogin('admin@nexu.com', 'correct-password', '127.0.0.1');

        $this->assertEquals($admin->id, session('auth.pending_2fa'));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 2. verifyTwoFactor
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_verify_two_factor_returns_true_on_valid_code(): void
    {
        $admin = $this->makeAdminWith2FA();

        $this->google2fa
            ->shouldReceive('verifyKey')
            ->once()
            ->andReturn(true);

        $result = $this->service->verifyTwoFactor($admin, '123456', '127.0.0.1');

        $this->assertTrue($result);
    }

    /** @test */
    public function test_verify_two_factor_updates_last_login_on_success(): void
    {
        $admin = $this->makeAdminWith2FA();

        $this->google2fa->shouldReceive('verifyKey')->andReturn(true);

        $this->service->verifyTwoFactor($admin, '123456', '10.0.0.1');

        $admin->refresh();
        $this->assertNotNull($admin->last_login_at);
        $this->assertEquals('10.0.0.1', $admin->last_login_ip);
    }

    /** @test */
    public function test_verify_two_factor_returns_false_on_invalid_code(): void
    {
        $admin = $this->makeAdminWith2FA();

        $this->google2fa->shouldReceive('verifyKey')->andReturn(false);

        $result = $this->service->verifyTwoFactor($admin, '000000', '127.0.0.1');

        $this->assertFalse($result);
    }

    /** @test */
    public function test_verify_two_factor_increments_attempts_on_failure(): void
    {
        $admin = $this->makeAdminWith2FA();
        $key   = "2fa_lock:{$admin->id}";

        $this->google2fa->shouldReceive('verifyKey')->twice()->andReturn(false);

        $this->service->verifyTwoFactor($admin, '000000', '127.0.0.1');
        $this->service->verifyTwoFactor($admin, '000000', '127.0.0.1');

        $this->assertEquals(2, Cache::get($key));
    }

    /** @test */
    public function test_verify_two_factor_locks_after_three_failures(): void
    {
        $admin = $this->makeAdminWith2FA();

        $this->google2fa->shouldReceive('verifyKey')->times(3)->andReturn(false);

        $this->service->verifyTwoFactor($admin, '000000', '127.0.0.1');
        $this->service->verifyTwoFactor($admin, '000000', '127.0.0.1');
        $this->service->verifyTwoFactor($admin, '000000', '127.0.0.1');

        // 4th attempt — google2fa should NOT be called (locked)
        $this->google2fa->shouldNotReceive('verifyKey');
        $result = $this->service->verifyTwoFactor($admin, '123456', '127.0.0.1');

        $this->assertFalse($result);
    }

    /** @test */
    public function test_verify_two_factor_clears_lock_on_success(): void
    {
        $admin = $this->makeAdminWith2FA();
        $key   = "2fa_lock:{$admin->id}";

        // Simulate 2 prior failures stored in cache
        Cache::put($key, 2, 900);

        $this->google2fa->shouldReceive('verifyKey')->andReturn(true);

        $this->service->verifyTwoFactor($admin, '123456', '127.0.0.1');

        $this->assertNull(Cache::get($key));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 3. useRecoveryCode
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_use_recovery_code_succeeds_with_valid_code(): void
    {
        $admin = $this->makeAdmin([
            'two_factor_confirmed_at'   => now(),
            'two_factor_recovery_codes' => ['abcde-fghij', 'klmno-pqrst'],
        ]);

        $result = $this->service->useRecoveryCode($admin, 'abcde-fghij', '127.0.0.1');

        $this->assertTrue($result);
    }

    /** @test */
    public function test_use_recovery_code_removes_used_code(): void
    {
        $admin = $this->makeAdmin([
            'two_factor_confirmed_at'   => now(),
            'two_factor_recovery_codes' => ['abcde-fghij', 'klmno-pqrst'],
        ]);

        $this->service->useRecoveryCode($admin, 'abcde-fghij', '127.0.0.1');

        $admin->refresh();
        $this->assertNotContains('abcde-fghij', $admin->recoveryCodes());
        $this->assertContains('klmno-pqrst', $admin->recoveryCodes());
    }

    /** @test */
    public function test_use_recovery_code_is_single_use(): void
    {
        $admin = $this->makeAdmin([
            'two_factor_confirmed_at'   => now(),
            'two_factor_recovery_codes' => ['abcde-fghij'],
        ]);

        $firstUse  = $this->service->useRecoveryCode($admin, 'abcde-fghij', '127.0.0.1');
        $secondUse = $this->service->useRecoveryCode($admin, 'abcde-fghij', '127.0.0.1');

        $this->assertTrue($firstUse);
        $this->assertFalse($secondUse);
    }

    /** @test */
    public function test_use_recovery_code_fails_with_invalid_code(): void
    {
        $admin = $this->makeAdmin([
            'two_factor_confirmed_at'   => now(),
            'two_factor_recovery_codes' => ['abcde-fghij'],
        ]);

        $result = $this->service->useRecoveryCode($admin, 'wrong-wrong', '127.0.0.1');

        $this->assertFalse($result);
    }

    /** @test */
    public function test_use_recovery_code_updates_last_login(): void
    {
        $admin = $this->makeAdmin([
            'two_factor_confirmed_at'   => now(),
            'two_factor_recovery_codes' => ['abcde-fghij'],
        ]);

        $this->service->useRecoveryCode($admin, 'abcde-fghij', '10.20.30.40');

        $admin->refresh();
        $this->assertNotNull($admin->last_login_at);
        $this->assertEquals('10.20.30.40', $admin->last_login_ip);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 4. generateTwoFactorSecret
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_generate_two_factor_secret_returns_dto(): void
    {
        $admin = $this->makeAdmin();

        $this->google2fa
            ->shouldReceive('generateSecretKey')
            ->once()
            ->andReturn('NEWSECRETKEY32');

        $this->google2fa
            ->shouldReceive('getQRCodeUrl')
            ->once()
            ->andReturn('otpauth://totp/...');

        $dto = $this->service->generateTwoFactorSecret($admin);

        $this->assertInstanceOf(TwoFactorSetupDTO::class, $dto);
        $this->assertEquals('NEWSECRETKEY32', $dto->secretKey);
        $this->assertNotEmpty($dto->qrCodeUrl);
    }

    /** @test */
    public function test_generate_two_factor_secret_clears_previous_confirmation(): void
    {
        $admin = $this->makeAdminWith2FA();

        $this->google2fa->shouldReceive('generateSecretKey')->andReturn('NEWSECRET');
        $this->google2fa->shouldReceive('getQRCodeUrl')->andReturn('otpauth://...');

        $this->service->generateTwoFactorSecret($admin);

        $admin->refresh();
        $this->assertNull($admin->two_factor_confirmed_at);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 5. confirmTwoFactor
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_confirm_two_factor_sets_confirmed_at_and_returns_codes(): void
    {
        $admin = $this->makeAdmin(['two_factor_secret' => 'BASE32SECRET']);

        $this->google2fa
            ->shouldReceive('verifyKey')
            ->once()
            ->andReturn(true);

        $codes = $this->service->confirmTwoFactor($admin, '123456');

        $this->assertCount(8, $codes);
        $admin->refresh();
        $this->assertNotNull($admin->two_factor_confirmed_at);
        $this->assertCount(8, $admin->recoveryCodes());
    }

    /** @test */
    public function test_confirm_two_factor_throws_on_invalid_code(): void
    {
        $admin = $this->makeAdmin(['two_factor_secret' => 'BASE32SECRET']);

        $this->google2fa
            ->shouldReceive('verifyKey')
            ->once()
            ->andReturn(false);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Código 2FA incorrecto.');

        $this->service->confirmTwoFactor($admin, '000000');
    }

    /** @test */
    public function test_recovery_codes_have_expected_format(): void
    {
        $admin = $this->makeAdmin(['two_factor_secret' => 'BASE32SECRET']);

        $this->google2fa->shouldReceive('verifyKey')->andReturn(true);

        $codes = $this->service->confirmTwoFactor($admin, '123456');

        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^[a-z]{5}-[a-z]{5}$/', $code);
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 6. disableTwoFactor
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_disable_two_factor_clears_all_2fa_fields(): void
    {
        $admin = $this->makeAdmin([
            'two_factor_secret'         => 'SECRET',
            'two_factor_confirmed_at'   => now(),
            'two_factor_recovery_codes' => ['abcde-fghij'],
        ]);

        $this->service->disableTwoFactor($admin, 'correct-password');

        $admin->refresh();
        $this->assertNull($admin->two_factor_secret);
        $this->assertNull($admin->two_factor_confirmed_at);
        $this->assertNull($admin->two_factor_recovery_codes);
        $this->assertFalse($admin->hasTwoFactorEnabled());
    }

    /** @test */
    public function test_disable_two_factor_throws_on_wrong_password(): void
    {
        $admin = $this->makeAdminWith2FA();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Contraseña incorrecta.');

        $this->service->disableTwoFactor($admin, 'wrong-password');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 7. regenerateRecoveryCodes
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_regenerate_recovery_codes_returns_new_codes(): void
    {
        $admin = $this->makeAdmin([
            'two_factor_confirmed_at'   => now(),
            'two_factor_recovery_codes' => ['old-codes'],
        ]);

        $newCodes = $this->service->regenerateRecoveryCodes($admin, 'correct-password');

        $this->assertCount(8, $newCodes);
        $this->assertNotContains('old-codes', $newCodes);
    }

    /** @test */
    public function test_regenerate_recovery_codes_persists_in_db(): void
    {
        $admin = $this->makeAdmin([
            'two_factor_confirmed_at'   => now(),
            'two_factor_recovery_codes' => ['old-codes'],
        ]);

        $newCodes = $this->service->regenerateRecoveryCodes($admin, 'correct-password');

        $admin->refresh();
        $this->assertEquals($newCodes, $admin->recoveryCodes());
    }

    /** @test */
    public function test_regenerate_recovery_codes_throws_on_wrong_password(): void
    {
        $admin = $this->makeAdmin(['two_factor_confirmed_at' => now()]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Contraseña incorrecta.');

        $this->service->regenerateRecoveryCodes($admin, 'wrong-password');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 8. sendPasswordReset
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_send_password_reset_inserts_token_for_valid_email(): void
    {
        Notification::fake();

        $admin = $this->makeAdmin();

        $this->service->sendPasswordReset('admin@nexu.com');

        $this->assertDatabaseHas('admin_password_reset_tokens', ['email' => 'admin@nexu.com']);
    }

    /** @test */
    public function test_send_password_reset_sends_notification(): void
    {
        Notification::fake();

        $admin = $this->makeAdmin();

        $this->service->sendPasswordReset('admin@nexu.com');

        Notification::assertSentTo($admin, AdminPasswordResetNotification::class);
    }

    /** @test */
    public function test_send_password_reset_is_silent_for_unknown_email(): void
    {
        Notification::fake();

        // Should not throw
        $this->service->sendPasswordReset('ghost@nexu.com');

        $this->assertDatabaseMissing('admin_password_reset_tokens', ['email' => 'ghost@nexu.com']);
        Notification::assertNothingSent();
    }

    /** @test */
    public function test_send_password_reset_upserts_token_on_repeat_request(): void
    {
        Notification::fake();

        $this->makeAdmin();

        $this->service->sendPasswordReset('admin@nexu.com');
        $this->service->sendPasswordReset('admin@nexu.com');

        $this->assertDatabaseCount('admin_password_reset_tokens', 1);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 9. resetPassword
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_reset_password_updates_password_with_valid_token(): void
    {
        $admin = $this->makeAdmin();
        $token = 'valid-reset-token';

        DB::table('admin_password_reset_tokens')->insert([
            'email'      => 'admin@nexu.com',
            'token'      => Hash::make($token),
            'created_at' => now(),
        ]);

        $this->service->resetPassword('admin@nexu.com', $token, 'new-secure-password-123', '127.0.0.1');

        $admin->refresh();
        $this->assertTrue(Hash::check('new-secure-password-123', $admin->password));
    }

    /** @test */
    public function test_reset_password_deletes_token_after_use(): void
    {
        $admin = $this->makeAdmin();
        $token = 'single-use-token';

        DB::table('admin_password_reset_tokens')->insert([
            'email'      => 'admin@nexu.com',
            'token'      => Hash::make($token),
            'created_at' => now(),
        ]);

        $this->service->resetPassword('admin@nexu.com', $token, 'new-password', '127.0.0.1');

        $this->assertDatabaseMissing('admin_password_reset_tokens', ['email' => 'admin@nexu.com']);
    }

    /** @test */
    public function test_reset_password_throws_on_wrong_token(): void
    {
        $this->makeAdmin();

        DB::table('admin_password_reset_tokens')->insert([
            'email'      => 'admin@nexu.com',
            'token'      => Hash::make('real-token'),
            'created_at' => now(),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Token inválido o expirado.');

        $this->service->resetPassword('admin@nexu.com', 'wrong-token', 'new-password', '127.0.0.1');
    }

    /** @test */
    public function test_reset_password_throws_on_expired_token(): void
    {
        $this->makeAdmin();

        DB::table('admin_password_reset_tokens')->insert([
            'email'      => 'admin@nexu.com',
            'token'      => Hash::make('some-token'),
            'created_at' => Carbon::now()->subMinutes(61),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Token inválido o expirado.');

        $this->service->resetPassword('admin@nexu.com', 'some-token', 'new-password', '127.0.0.1');
    }

    /** @test */
    public function test_reset_password_deletes_expired_token_record(): void
    {
        $this->makeAdmin();

        DB::table('admin_password_reset_tokens')->insert([
            'email'      => 'admin@nexu.com',
            'token'      => Hash::make('some-token'),
            'created_at' => Carbon::now()->subMinutes(61),
        ]);

        try {
            $this->service->resetPassword('admin@nexu.com', 'some-token', 'new-password', '127.0.0.1');
        } catch (InvalidArgumentException) {
            // expected
        }

        $this->assertDatabaseMissing('admin_password_reset_tokens', ['email' => 'admin@nexu.com']);
    }

    /** @test */
    public function test_reset_password_throws_when_no_token_record(): void
    {
        $this->makeAdmin();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Token inválido o expirado.');

        $this->service->resetPassword('admin@nexu.com', 'any-token', 'new-password', '127.0.0.1');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 10. AdminPolicy
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_super_admin_can_view_any_admin(): void
    {
        $super   = $this->makeAdmin(['role' => 'super_admin']);
        $subject = Admin::create(['name' => 'B', 'email' => 'b@nexu.com', 'password' => 'x', 'role' => 'manager']);

        $this->assertTrue($super->isSuperAdmin());
        $this->actingAs($super, 'web');
        $this->assertTrue(\Gate::allows('viewAny', Admin::class));
    }

    /** @test */
    public function test_manager_cannot_view_any_admin(): void
    {
        $this->makeAdmin(); // super_admin
        $manager = Admin::create(['name' => 'M', 'email' => 'm@nexu.com', 'password' => 'x', 'role' => 'manager']);

        $this->actingAs($manager, 'web');
        $this->assertFalse(\Gate::allows('viewAny', Admin::class));
    }

    /** @test */
    public function test_delete_is_never_allowed_for_any_role(): void
    {
        $super   = $this->makeAdmin(['role' => 'super_admin']);
        $subject = Admin::create(['name' => 'B', 'email' => 'b@nexu.com', 'password' => 'x', 'role' => 'manager']);

        $this->actingAs($super, 'web');
        $this->assertFalse(\Gate::allows('delete', $subject));
    }
}
