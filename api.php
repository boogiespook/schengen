<?php
// api.php - API endpoints with authentication

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Session-Token');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'database.php';
require_once 'auth.php';

// Get action from query parameter
$action = $_GET['action'] ?? '';

// Get session token from header
$sessionToken = $_SERVER['HTTP_X_SESSION_TOKEN'] ?? '';

// Helper function to get JSON input
function getJsonInput() {
    $input = file_get_contents('php://input');
    return json_decode($input, true);
}

// Helper function to send JSON response
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

// Helper function to require authentication
function requireAuth($sessionToken) {
    $user = validateSession($sessionToken);
    if (!$user) {
        sendResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }
    return $user;
}

// Route actions
switch ($action) {
    // ===== Authentication Endpoints =====

    case 'register':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendResponse(['success' => false, 'message' => 'Method not allowed'], 405);
        }

        $data = getJsonInput();
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        $result = registerUser($username, $password);
        sendResponse($result, $result['success'] ? 200 : 400);
        break;

    case 'login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendResponse(['success' => false, 'message' => 'Method not allowed'], 405);
        }

        $data = getJsonInput();
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        $result = loginUser($username, $password);
        sendResponse($result, $result['success'] ? 200 : 401);
        break;

    case 'logout':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendResponse(['success' => false, 'message' => 'Method not allowed'], 405);
        }

        $success = logoutUser($sessionToken);
        sendResponse(['success' => $success]);
        break;

    case 'check_session':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            sendResponse(['success' => false, 'message' => 'Method not allowed'], 405);
        }

        $user = validateSession($sessionToken);
        if ($user) {
            sendResponse(['authenticated' => true, 'user' => $user]);
        } else {
            sendResponse(['authenticated' => false], 401);
        }
        break;

    // ===== Trip Management Endpoints (Authenticated) =====

    case 'get_trips':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            sendResponse(['success' => false, 'message' => 'Method not allowed'], 405);
        }

        $user = requireAuth($sessionToken);

        try {
            $db = getConnection();
            $stmt = $db->prepare("
                SELECT id, arrival, departure, country, city
                FROM trips
                WHERE user_id = ?
                ORDER BY arrival DESC
            ");
            $stmt->execute([$user['id']]);
            $trips = $stmt->fetchAll();

            sendResponse($trips);
        } catch (PDOException $e) {
            error_log("Get trips error: " . $e->getMessage());
            sendResponse(['success' => false, 'message' => 'Failed to fetch trips'], 500);
        }
        break;

    case 'save_trips':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendResponse(['success' => false, 'message' => 'Method not allowed'], 405);
        }

        $user = requireAuth($sessionToken);
        $trips = getJsonInput();

        if (!is_array($trips)) {
            sendResponse(['success' => false, 'message' => 'Invalid data format'], 400);
        }

        try {
            $db = getConnection();

            // Begin transaction
            $db->beginTransaction();

            // Delete all existing trips for this user
            $stmt = $db->prepare("DELETE FROM trips WHERE user_id = ?");
            $stmt->execute([$user['id']]);

            // Insert new trips
            $stmt = $db->prepare("
                INSERT INTO trips (id, user_id, arrival, departure, country, city)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            foreach ($trips as $trip) {
                $stmt->execute([
                    $trip['id'],
                    $user['id'],
                    $trip['arrival'],
                    $trip['departure'],
                    $trip['country'] ?? null,
                    $trip['city'] ?? null
                ]);
            }

            // Commit transaction
            $db->commit();

            sendResponse(['success' => true]);
        } catch (PDOException $e) {
            $db->rollBack();
            error_log("Save trips error: " . $e->getMessage());
            sendResponse(['success' => false, 'message' => 'Failed to save trips'], 500);
        }
        break;

    // ===== Admin Endpoints =====

    case 'get_users':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            sendResponse(['success' => false, 'message' => 'Method not allowed'], 405);
        }

        $admin = requireAdmin($sessionToken);

        try {
            $db = getConnection();

            // Get all users with trip counts
            $stmt = $db->query("
                SELECT
                    u.id,
                    u.username,
                    u.is_admin,
                    u.created_at,
                    u.last_login,
                    COUNT(t.id) as trip_count
                FROM users u
                LEFT JOIN trips t ON u.id = t.user_id
                GROUP BY u.id
                ORDER BY u.created_at DESC
            ");
            $users = $stmt->fetchAll();

            // Convert is_admin to boolean
            foreach ($users as &$user) {
                $user['is_admin'] = (bool)$user['is_admin'];
                $user['trip_count'] = (int)$user['trip_count'];
            }

            sendResponse($users);
        } catch (PDOException $e) {
            error_log("Get users error: " . $e->getMessage());
            sendResponse(['success' => false, 'message' => 'Failed to fetch users'], 500);
        }
        break;

    case 'delete_user':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendResponse(['success' => false, 'message' => 'Method not allowed'], 405);
        }

        $admin = requireAdmin($sessionToken);
        $data = getJsonInput();
        $userId = $data['user_id'] ?? null;

        if (!$userId) {
            sendResponse(['success' => false, 'message' => 'User ID required'], 400);
        }

        // Cannot delete yourself
        if ($userId == $admin['id']) {
            sendResponse(['success' => false, 'message' => 'Cannot delete your own account'], 400);
        }

        try {
            $db = getConnection();

            // Check if user exists
            $stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user) {
                sendResponse(['success' => false, 'message' => 'User not found'], 404);
            }

            // Delete user (CASCADE will delete trips and sessions)
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);

            sendResponse([
                'success' => true,
                'message' => "User '{$user['username']}' deleted successfully"
            ]);
        } catch (PDOException $e) {
            error_log("Delete user error: " . $e->getMessage());
            sendResponse(['success' => false, 'message' => 'Failed to delete user'], 500);
        }
        break;

    // ===== Default / Error =====

    default:
        sendResponse(['success' => false, 'message' => 'Invalid action'], 400);
}
?>
