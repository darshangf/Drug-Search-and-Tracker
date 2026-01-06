# Authentication API Documentation

## Overview

This document describes the authentication endpoints for the Drug Search and Tracker application. The API uses Laravel Sanctum for token-based authentication.

## Security

- All authentication endpoints are rate-limited to **5 requests per minute** to prevent brute force attacks
- Passwords are automatically hashed using bcrypt via Laravel's 'hashed' cast
- Tokens are cryptographically secure random strings
- **HTTPS is required in production** - never send tokens over HTTP
- Tokens should be stored securely on the client side:
  - Mobile apps: Use secure storage (Keychain/Keystore)
  - Web apps: Use httpOnly cookies or secure storage (avoid localStorage for sensitive tokens)

## Base URL

```
Development: http://localhost:8000/api
Production: https://your-domain.com/api
```

## Endpoints

### 1. Register User

Register a new user account.

**Endpoint:** `POST /register`

**Rate Limit:** 5 requests per minute

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123"
}
```

**Validation Rules:**
- `name`: Required, string, max 255 characters
- `email`: Required, valid email, unique in users table
- `password`: Required, string, minimum 8 characters

**Success Response (201 Created):**
```json
{
  "message": "User registered successfully",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "email_verified_at": null,
    "created_at": "2026-01-05T10:30:00.000000Z",
    "updated_at": "2026-01-05T10:30:00.000000Z"
  },
  "access_token": "1|abcdefghijklmnopqrstuvwxyz1234567890",
  "token_type": "Bearer"
}
```

**Error Response (422 Unprocessable Entity):**
```json
{
  "message": "The email has already been taken.",
  "errors": {
    "email": [
      "The email has already been taken."
    ]
  }
}
```

**cURL Example:**
```bash
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123"
  }'
```

---

### 2. Login User

Authenticate an existing user and receive an access token.

**Endpoint:** `POST /login`

**Rate Limit:** 5 requests per minute

**Request Body:**
```json
{
  "email": "john@example.com",
  "password": "password123"
}
```

**Validation Rules:**
- `email`: Required, valid email format
- `password`: Required, string

**Success Response (200 OK):**
```json
{
  "message": "Login successful",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "email_verified_at": null,
    "created_at": "2026-01-05T10:30:00.000000Z",
    "updated_at": "2026-01-05T10:30:00.000000Z"
  },
  "access_token": "2|zyxwvutsrqponmlkjihgfedcba0987654321",
  "token_type": "Bearer"
}
```

**Error Response (422 Unprocessable Entity):**
```json
{
  "message": "The provided credentials are incorrect.",
  "errors": {
    "email": [
      "The provided credentials are incorrect."
    ]
  }
}
```

**cURL Example:**
```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "password123"
  }'
```

---


## Using Authentication Tokens

### In HTTP Headers

For all protected endpoints, include the token in the Authorization header:

```
Authorization: Bearer {your_access_token}
```

### JavaScript/Axios Example

```javascript
// Store token after login
const loginResponse = await axios.post('/api/login', {
  email: 'john@example.com',
  password: 'password123'
});
const token = loginResponse.data.access_token;

// Use token for authenticated requests
const response = await axios.get('/api/user', {
  headers: {
    'Authorization': `Bearer ${token}`
  }
});
```

### PHP/Guzzle Example

```php
$client = new \GuzzleHttp\Client();

// Login
$response = $client->post('http://localhost:8000/api/login', [
    'json' => [
        'email' => 'john@example.com',
        'password' => 'password123'
    ]
]);
$data = json_decode($response->getBody(), true);
$token = $data['access_token'];

// Authenticated request
$response = $client->get('http://localhost:8000/api/user', [
    'headers' => [
        'Authorization' => "Bearer $token"
    ]
]);
```

---

## Rate Limiting

### Authentication Endpoints

- **Limit:** 5 requests per minute per IP address
- **Applies to:** `/register`, `/login`
- **Response Code:** 429 Too Many Requests
- **Headers:** Response includes `X-RateLimit-*` headers showing limit status

**Rate Limit Response:**
```json
{
  "message": "Too Many Attempts."
}
```

**Rate Limit Headers:**
```
X-RateLimit-Limit: 5
X-RateLimit-Remaining: 0
Retry-After: 60
```

---

## Error Codes

| Code | Description |
|------|-------------|
| 200  | OK - Request successful |
| 201  | Created - User registered successfully |
| 401  | Unauthorized - Missing or invalid token |
| 422  | Unprocessable Entity - Validation error |
| 429  | Too Many Requests - Rate limit exceeded |
| 500  | Internal Server Error - Server error |

---

## Testing

### Running Tests

```bash
# Run all tests
php artisan test

# Run authentication tests only
php artisan test --filter=AuthenticationTest
php artisan test --filter=AuthServiceTest

```

### Test Coverage

The authentication system includes:
- **Unit Tests:** Business logic in `AuthService`
- **Feature Tests:** HTTP endpoints and validation
- **Rate Limiting Tests:** Throttle middleware verification

---

## Architecture

### Clean Code Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── AuthController.php      # Thin controller, delegates to service
│   └── Requests/
│       ├── LoginRequest.php            # Login validation rules
│       └── RegisterRequest.php         # Registration validation rules
├── Models/
│   └── User.php                        # User model with HasApiTokens trait
└── Services/
    └── AuthService.php                 # Business logic for authentication

tests/
├── Unit/
│   └── AuthServiceTest.php             # Unit tests for AuthService
└── Feature/
    └── AuthenticationTest.php          # Integration tests for endpoints
```

### Design Principles

1. **Separation of Concerns:** Controller handles HTTP, Service handles business logic
2. **Single Responsibility:** Each class has one clear purpose
3. **Dependency Injection:** AuthService injected into controller
4. **Request Validation:** Dedicated Request classes with custom messages
5. **Testability:** Clean architecture enables comprehensive testing

---

## Security Best Practices

### Production Checklist

- [ ] Enable HTTPS for all API endpoints
- [ ] Configure proper CORS settings
- [ ] Set token expiration in `config/sanctum.php`
- [ ] Implement proper token storage on client
- [ ] Monitor rate limit violations
- [ ] Log authentication failures
- [ ] Implement password reset functionality
- [ ] Add email verification (optional)
- [ ] Consider implementing refresh tokens for long sessions
- [ ] Regular security audits

### Token Management

```php
// Optional: Set token expiration in config/sanctum.php
'expiration' => 60 * 24, // 24 hours in minutes

// Optional: Revoke all tokens on login (single device)
$user->tokens()->delete();
$token = $user->createToken('auth_token')->plainTextToken;
```
