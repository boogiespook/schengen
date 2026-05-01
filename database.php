<?php
// database.php - SQLite database connection and schema management

function getConnection() {
    try {
        $db = new PDO('sqlite:' . __DIR__ . '/schengen.db');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Enable foreign keys
        $db->exec('PRAGMA foreign_keys = ON');

        return $db;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }
}

function initializeDatabase() {
    $db = getConnection();

    // Create users table
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_login DATETIME
    )");

    // Create trips table
    $db->exec("CREATE TABLE IF NOT EXISTS trips (
        id INTEGER PRIMARY KEY,
        user_id INTEGER NOT NULL,
        arrival DATE NOT NULL,
        departure DATE NOT NULL,
        country TEXT,
        city TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Create sessions table
    $db->exec("CREATE TABLE IF NOT EXISTS sessions (
        session_id TEXT PRIMARY KEY,
        user_id INTEGER NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NOT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Create indexes
    $db->exec("CREATE INDEX IF NOT EXISTS idx_username ON users(username)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_user_trips ON trips(user_id, arrival)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_session_expires ON sessions(expires_at)");

    return $db;
}

// Initialize database on first include
initializeDatabase();
?>
