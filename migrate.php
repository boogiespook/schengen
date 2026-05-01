<?php
// migrate.php - Migration script to convert trips.json to SQLite database

require_once 'database.php';
require_once 'auth.php';

echo "========================================\n";
echo "Schengen Calculator - Database Migration\n";
echo "========================================\n\n";

// Check if trips.json exists
if (!file_exists('trips.json')) {
    echo "❌ Error: trips.json not found\n";
    exit(1);
}

// Check if database already has data
$db = getConnection();
$stmt = $db->query("SELECT COUNT(*) as count FROM users");
$userCount = $stmt->fetch()['count'];

if ($userCount > 0) {
    echo "⚠️  Warning: Database already contains {$userCount} user(s)\n";
    echo "Do you want to continue? This will add a new admin user. (yes/no): ";
    $response = trim(fgets(STDIN));
    if (strtolower($response) !== 'yes') {
        echo "Migration cancelled.\n";
        exit(0);
    }
}

// Read existing trips
$tripsJson = file_get_contents('trips.json');
$trips = json_decode($tripsJson, true);

if ($trips === null) {
    echo "❌ Error: Invalid JSON in trips.json\n";
    exit(1);
}

echo "✓ Found " . count($trips) . " trip(s) in trips.json\n\n";

// Prompt for admin credentials
echo "Create admin user:\n";
echo "Username (3-20 alphanumeric characters): ";
$username = trim(fgets(STDIN));

echo "Password (minimum 8 characters): ";
// Hide password input on Unix systems
if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
    system('stty -echo');
}
$password = trim(fgets(STDIN));
if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
    system('stty echo');
}
echo "\n";

echo "Confirm password: ";
if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
    system('stty -echo');
}
$passwordConfirm = trim(fgets(STDIN));
if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
    system('stty echo');
}
echo "\n\n";

// Validate password match
if ($password !== $passwordConfirm) {
    echo "❌ Error: Passwords do not match\n";
    exit(1);
}

// Register admin user
echo "Creating admin user...\n";
$result = registerUser($username, $password);

if (!$result['success']) {
    echo "❌ Error: " . $result['message'] . "\n";
    exit(1);
}

echo "✓ Admin user created successfully\n";

// Get admin user ID
$stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
$stmt->execute([$username]);
$adminUser = $stmt->fetch();
$adminUserId = $adminUser['id'];

// Import trips
echo "Importing trips...\n";
$importedCount = 0;

try {
    $stmt = $db->prepare("INSERT INTO trips (id, user_id, arrival, departure, country, city) VALUES (?, ?, ?, ?, ?, ?)");

    foreach ($trips as $trip) {
        $stmt->execute([
            $trip['id'],
            $adminUserId,
            $trip['arrival'],
            $trip['departure'],
            $trip['country'] ?? null,
            $trip['city'] ?? null
        ]);
        $importedCount++;
    }

    echo "✓ Imported {$importedCount} trip(s) for user '{$username}'\n";
} catch (PDOException $e) {
    echo "❌ Error importing trips: " . $e->getMessage() . "\n";
    exit(1);
}

// Backup trips.json
$backupFile = 'trips.json.backup.' . date('Y-m-d_His');
if (copy('trips.json', $backupFile)) {
    echo "✓ Backed up trips.json to {$backupFile}\n";
} else {
    echo "⚠️  Warning: Could not create backup of trips.json\n";
}

echo "\n========================================\n";
echo "Migration completed successfully!\n";
echo "========================================\n\n";
echo "You can now login with:\n";
echo "  Username: {$username}\n";
echo "  Password: ********\n\n";
echo "Your {$importedCount} trip(s) have been imported.\n";
echo "The original trips.json has been backed up.\n\n";
?>
