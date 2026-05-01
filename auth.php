<?php
// auth.php - Authentication and session management

require_once 'database.php';

/**
 * Register a new user
 * @param string $username
 * @param string $password
 * @return array Result with success status and message
 */
function registerUser($username, $password) {
    // Validate username
    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        return ['success' => false, 'message' => 'Username must be 3-20 alphanumeric characters'];
    }

    // Validate password
    if (strlen($password) < 8) {
        return ['success' => false, 'message' => 'Password must be at least 8 characters'];
    }

    try {
        $db = getConnection();

        // Check if username already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Username already exists'];
        }

        // Hash password
        $passwordHash = password_hash($password, PASSWORD_ARGON2ID);

        // Insert user
        $stmt = $db->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
        $stmt->execute([$username, $passwordHash]);

        return ['success' => true, 'message' => 'User registered successfully'];
    } catch (PDOException $e) {
        error_log("Registration error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Registration failed'];
    }
}

/**
 * Login user and create session
 * @param string $username
 * @param string $password
 * @return array Result with success, user info, and session token
 */
function loginUser($username, $password) {
    try {
        $db = getConnection();

        // Fetch user
        $stmt = $db->prepare("SELECT id, username, password_hash, is_admin FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['success' => false, 'message' => 'Invalid username or password'];
        }

        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Invalid username or password'];
        }

        // Generate session token
        $sessionToken = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

        // Create session
        $stmt = $db->prepare("INSERT INTO sessions (session_id, user_id, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$sessionToken, $user['id'], $expiresAt]);

        // Update last login
        $stmt = $db->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$user['id']]);

        return [
            'success' => true,
            'session_token' => $sessionToken,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'is_admin' => (bool)$user['is_admin']
            ]
        ];
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Login failed'];
    }
}

/**
 * Validate session and return user
 * @param string $sessionToken
 * @return array|null User info if valid, null if invalid
 */
function validateSession($sessionToken) {
    if (empty($sessionToken)) {
        return null;
    }

    try {
        $db = getConnection();

        // Clean up expired sessions
        $db->exec("DELETE FROM sessions WHERE expires_at < datetime('now')");

        // Fetch session and user
        $stmt = $db->prepare("
            SELECT u.id, u.username, u.is_admin
            FROM sessions s
            JOIN users u ON s.user_id = u.id
            WHERE s.session_id = ? AND s.expires_at > datetime('now')
        ");
        $stmt->execute([$sessionToken]);
        $user = $stmt->fetch();

        if ($user) {
            $user['is_admin'] = (bool)$user['is_admin'];
        }

        return $user ?: null;
    } catch (PDOException $e) {
        error_log("Session validation error: " . $e->getMessage());
        return null;
    }
}

/**
 * Logout user by invalidating session
 * @param string $sessionToken
 * @return bool Success status
 */
function logoutUser($sessionToken) {
    if (empty($sessionToken)) {
        return false;
    }

    try {
        $db = getConnection();
        $stmt = $db->prepare("DELETE FROM sessions WHERE session_id = ?");
        $stmt->execute([$sessionToken]);
        return true;
    } catch (PDOException $e) {
        error_log("Logout error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get current user from session token
 * @param string $sessionToken
 * @return array|null User info if authenticated, null otherwise
 */
function getCurrentUser($sessionToken) {
    return validateSession($sessionToken);
}

/**
 * Require admin privileges
 * @param string $sessionToken
 * @return array User info if admin, exits with 403 if not
 */
function requireAdmin($sessionToken) {
    $user = validateSession($sessionToken);

    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    if (!$user['is_admin']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden: Admin access required']);
        exit;
    }

    return $user;
}
?>
