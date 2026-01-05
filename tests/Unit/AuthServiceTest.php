<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Unit Tests for AuthService
 * 
 * Tests the business logic of authentication service
 */
class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AuthService $authService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authService = new AuthService();
    }

    /**
     * Test successful user registration
     */
    public function test_register_creates_user_and_returns_token(): void
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
        ];

        $result = $this->authService->register($userData);

        // Assert user was created
        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        // Assert result structure
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertInstanceOf(User::class, $result['user']);
        $this->assertIsString($result['token']);

        // Assert password is hashed
        $user = User::where('email', 'john@example.com')->first();
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    /**
     * Test successful login with valid credentials
     */
    public function test_login_with_valid_credentials_returns_token(): void
    {
        // Create a user
        $user = User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
        ]);

        $credentials = [
            'email' => 'jane@example.com',
            'password' => 'password123',
        ];

        $result = $this->authService->login($credentials);

        // Assert result structure
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertEquals($user->id, $result['user']->id);
        $this->assertIsString($result['token']);

        // Assert token was created
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
        ]);
    }

    /**
     * Test login fails with invalid email
     */
    public function test_login_fails_with_invalid_email(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The provided credentials are incorrect.');

        $credentials = [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ];

        $this->authService->login($credentials);
    }

    /**
     * Test login fails with invalid password
     */
    public function test_login_fails_with_invalid_password(): void
    {
        // Create a user
        User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'correctpassword',
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The provided credentials are incorrect.');

        $credentials = [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ];

        $this->authService->login($credentials);
    }

    /**
     * Test token generation returns valid token string
     */
    public function test_generated_token_is_valid(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $result = $this->authService->register([
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password123',
        ]);

        $token = $result['token'];

        // Token should be a long string
        $this->assertIsString($token);
        $this->assertGreaterThan(40, strlen($token));

        // Token should be verifiable
        $personalAccessToken = PersonalAccessToken::findToken($token);
        $this->assertNotNull($personalAccessToken);
    }
}
