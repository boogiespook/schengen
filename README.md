# Schengen Area Calculator 🇪🇺

A modern, multi-user web application for tracking days spent in the Schengen Area to ensure compliance with the 90/180 day visa rule. Features secure user authentication, admin panel, and real-time trip calculation.

![Version](https://img.shields.io/badge/version-2.0.0-blue)
![PHP](https://img.shields.io/badge/PHP-8.3-purple)
![License](https://img.shields.io/badge/license-MIT-green)

## Features ✨

### Multi-User Support
- **Secure Authentication** - Password-protected user accounts with ARGON2ID hashing
- **Session Management** - 24-hour secure sessions with cryptographic tokens
- **User Isolation** - Each user sees only their own trips
- **User Registration** - Self-service account creation

### Trip Management
- **Add Trips** - Record arrival/departure dates, country, and city
- **90/180 Day Calculation** - Automatic calculation of days spent in Schengen area
- **Country Breakdown** - Statistics by country visited
- **Trip History** - View all your trips with dates and durations
- **Real-time Updates** - Instant recalculation as you add or remove trips

### Admin Panel
- **User Management** - View all registered users
- **Delete Users** - Remove users and their data (with safeguards)
- **User Statistics** - See trip counts, registration dates, last login
- **Role Management** - Admin/User role badges

### Modern Design
- **Dark Theme** - Beautiful blue gradient dark mode
- **Responsive** - Works on mobile, tablet, and desktop
- **Glass Morphism** - Modern UI with backdrop blur effects
- **Smooth Animations** - Polished interactions throughout

## Quick Start 🚀

### Local Development

```bash
# Navigate to the directory
cd /var/www/html/schengen

# Start PHP development server
php -S localhost:8080

# Open in browser
http://localhost:8080
```

### Docker Deployment

```bash
# Build the container
podman build -t schengen-calculator:latest .

# Run the container
podman run -d -p 8080:8080 --name schengen schengen-calculator:latest

# Access the application
http://127.0.0.1:8080
```

## Setup & Installation 📖

### Initial Database Setup

The application uses SQLite for data storage. On first run, the database is automatically created.

**Option 1: Start Fresh**

Just access the application and register your first user. The database will be created automatically.

**Option 2: Migrate Existing Data**

If you have existing `trips.json` data:

```bash
php migrate.php
```

Follow the prompts to:
1. Enter admin username
2. Set admin password
3. Import existing trips

### Creating the First Admin User

**Method 1: During Migration**
The migrate.php script will prompt you to create an admin user.

**Method 2: Direct SQL**

```bash
php -r "
require 'database.php';
require 'auth.php';
registerUser('admin', 'your-password');
\$db = getConnection();
\$db->exec(\"UPDATE users SET is_admin = 1 WHERE username = 'admin'\");
echo 'Admin user created';
"
```

**Method 3: Manual SQL**

```sql
sqlite3 schengen.db
UPDATE users SET is_admin = 1 WHERE username = 'your-username';
```

## Usage 💡

### For Regular Users

1. **Register** - Create an account with username and password (min 8 characters)
2. **Login** - Access your personal dashboard
3. **Add Trips** - Enter arrival/departure dates and location details
4. **View Statistics** - See days used, days remaining, and country breakdown
5. **Manage Trips** - Delete trips you no longer need
6. **Logout** - Securely end your session

### For Administrators

1. **Login** as admin user
2. **Click "Admin"** button in header (red button)
3. **View Users** - See all registered users with statistics
4. **Delete Users** - Remove users (except yourself) with confirmation
5. **Back to Calculator** - Return to your personal trip tracker

## API Endpoints 🔌

### Authentication Endpoints

**Register User**
```http
POST /api.php?action=register
Content-Type: application/json

{
  "username": "john",
  "password": "secure123"
}
```

**Login**
```http
POST /api.php?action=login
Content-Type: application/json

{
  "username": "john",
  "password": "secure123"
}

Response:
{
  "success": true,
  "session_token": "64-char-hex-token",
  "user": {
    "id": 1,
    "username": "john",
    "is_admin": false
  }
}
```

**Logout**
```http
POST /api.php?action=logout
X-Session-Token: your-session-token
```

**Check Session**
```http
GET /api.php?action=check_session
X-Session-Token: your-session-token

Response:
{
  "authenticated": true,
  "user": {
    "id": 1,
    "username": "john",
    "is_admin": false
  }
}
```

### Trip Management Endpoints (Authenticated)

**Get Trips**
```http
GET /api.php?action=get_trips
X-Session-Token: your-session-token

Response:
[
  {
    "id": 1714567890123,
    "arrival": "2026-06-01",
    "departure": "2026-06-15",
    "country": "France",
    "city": "Paris"
  }
]
```

**Save Trips**
```http
POST /api.php?action=save_trips
X-Session-Token: your-session-token
Content-Type: application/json

[
  {
    "id": 1714567890123,
    "arrival": "2026-06-01",
    "departure": "2026-06-15",
    "country": "France",
    "city": "Paris"
  }
]
```

### Admin Endpoints (Admin Only)

**Get All Users**
```http
GET /api.php?action=get_users
X-Session-Token: admin-session-token

Response:
[
  {
    "id": 1,
    "username": "admin",
    "is_admin": true,
    "created_at": "2026-05-01 12:00:00",
    "last_login": "2026-05-01 15:00:00",
    "trip_count": 13
  }
]
```

**Delete User**
```http
POST /api.php?action=delete_user
X-Session-Token: admin-session-token
Content-Type: application/json

{
  "user_id": 2
}

Response:
{
  "success": true,
  "message": "User 'john' deleted successfully"
}
```

## Database Schema 📊

### Users Table
```sql
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    is_admin INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME
);
```

### Trips Table
```sql
CREATE TABLE trips (
    id INTEGER PRIMARY KEY,
    user_id INTEGER NOT NULL,
    arrival DATE NOT NULL,
    departure DATE NOT NULL,
    country TEXT,
    city TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### Sessions Table
```sql
CREATE TABLE sessions (
    session_id TEXT PRIMARY KEY,
    user_id INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

## Configuration ⚙️

### Database Location

By default, the database is created as `schengen.db` in the application directory.

### Session Duration

Default: 24 hours

To change, edit `auth.php`:
```php
$expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours')); // Change here
```

### Password Requirements

- Minimum length: 8 characters
- Username: 3-20 alphanumeric characters

To change, edit `auth.php` in the `registerUser()` function.

## Security 🔒

### Password Security
- **ARGON2ID** hashing algorithm (industry standard)
- Plaintext passwords never stored
- Minimum 8-character requirement

### Session Security
- Cryptographically secure 64-character tokens
- Server-side validation on every request
- Automatic 24-hour expiration
- Logout invalidates session immediately

### SQL Injection Prevention
- PDO prepared statements exclusively
- No string concatenation in queries
- Parameterized queries throughout

### Authorization
- Admin endpoints verify admin status server-side
- Cannot delete your own account
- 401 Unauthorized for missing auth
- 403 Forbidden for insufficient privileges

### HTTPS Recommendation
For production deployment, use HTTPS to protect session tokens in transit.

## File Structure 📁

```
schengen/
├── index.html           # Main application (UI + frontend logic)
├── api.php              # API router and endpoints
├── database.php         # Database connection and schema
├── auth.php             # Authentication and session management
├── migrate.php          # Migration script for existing data
├── schengen.db          # SQLite database (auto-created, gitignored)
├── trips.json           # Your local trip data (gitignored, not in repo)
├── trips.json.example   # Sample data format for reference
├── .gitignore           # Git ignore rules (excludes database and sensitive files)
├── Dockerfile           # Container build file
└── README.md            # This file
```

## Technical Stack 💻

- **Backend:** PHP 8.3
- **Database:** SQLite 3
- **Frontend:** Vanilla JavaScript (ES6)
- **Styling:** Custom CSS with gradient themes
- **Fonts:** Google Fonts (Poppins)
- **Container:** Red Hat UBI 9 with PHP 8.3

## Browser Support 🌐

- Chrome/Edge (latest)
- Firefox (latest)
- Safari (latest)
- Mobile browsers (iOS Safari, Chrome Mobile)

## Deployment Considerations 🚀

### File Permissions

The database file and directory must be writable by the web server:

```bash
chmod 666 schengen.db
chmod 777 /path/to/schengen/
```

### Database Backup

Regular backups recommended:

```bash
# Backup database
cp schengen.db schengen.db.backup.$(date +%Y%m%d)

# Export to SQL
sqlite3 schengen.db .dump > schengen_backup.sql
```

### Performance

- SQLite handles up to 100,000 requests/day easily
- For higher loads, consider migrating to PostgreSQL/MySQL
- Session cleanup runs automatically on each validation

## Troubleshooting 🔧

### "Database is locked" error

**Cause:** SQLite journal file permissions or concurrent writes

**Fix:**
```bash
chmod 777 /path/to/schengen/
chmod 666 schengen.db
rm schengen.db-journal  # If exists
```

### "Login failed" with no error

**Cause:** Database not writable (sessions can't be created)

**Fix:** Check file and directory permissions (see above)

### Admin button not showing

**Cause:** User is not marked as admin in database

**Fix:**
```bash
php -r "
\$db = new PDO('sqlite:schengen.db');
\$db->exec(\"UPDATE users SET is_admin = 1 WHERE username = 'your-username'\");
"
```

### Session expires immediately

**Cause:** System time mismatch or session cleanup issue

**Fix:** Check server time is correct, clear sessions table:
```bash
sqlite3 schengen.db "DELETE FROM sessions;"
```

## Roadmap 🗺️

- [ ] Password reset functionality
- [ ] Email notifications for trip reminders
- [ ] Export trips to PDF/CSV
- [ ] Import trips from calendar
- [ ] Two-factor authentication
- [ ] API rate limiting
- [ ] User profile settings (timezone, email)
- [ ] Shared trips for families/groups
- [ ] Mobile app (React Native)

## Contributing 🤝

Contributions welcome! To contribute:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly (multi-user scenarios)
5. Submit a pull request

## License 📄

MIT License - feel free to use and modify for your own needs!

## Credits 👏

- Concept by [ChrisJ](https://www.chrisj.co.uk)
- Icons by [Font Awesome](https://fontawesome.com)
- Fonts by [Google Fonts](https://fonts.google.com)

## Support 💬

For issues or suggestions:
- Create an issue on GitHub
- Contact: [chrisj.co.uk](https://www.chrisj.co.uk)

---

**Note:** This application is for informational purposes only. Always verify your visa status with official authorities. The 90/180 day rule applies to the Schengen Area as a whole, not individual countries.

Made with ❤️ by ChrisJ | © 2026
