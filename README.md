# TaskMesh - Modern Task Management System

## 📚 **Start Here - Documentation Guide**

### 🚀 Quick Start (Choose Your Path)

**Just want to test it?**
1. Read **`QUICK_REFERENCE.md`** (2 min)
2. Follow **`RUN_THESE_TESTS.md`** (20 min)

**Want to understand everything?**
1. **`IMPLEMENTATION_SUMMARY.md`** - What's implemented
2. **`ARCHITECTURE.md`** - How it works
3. **`API_STATUS.md`** - API reference

### 📖 All Documentation

- **`QUICK_REFERENCE.md`** - Fast setup & troubleshooting
- **`RUN_THESE_TESTS.md`** - 10 tests to verify everything works
- **`TESTING_GUIDE.md`** - Comprehensive testing manual
- **`IMPLEMENTATION_SUMMARY.md`** - Complete feature list & changes
- **`TODO.md`** - Developer implementation guide
- **`API_STATUS.md`** - Complete API documentation
- **`ARCHITECTURE.md`** - System design & data flow
- **`EMAIL_NOTIFICATIONS.md`** - Email setup & configuration ✨ **NEW**

---

## 🎨 Features

### ✅ Implemented & Working

#### Backend APIs (PHP + MySQL)
- **Authentication**: JWT-based with bcrypt (cost 12)
- **User Management**: Role change, status toggle ✅ **FIXED**
- **Tasks**: Full CRUD with assignment support
- **Subtasks**: Complete API ✨ **NEW**
- **Comments**: View and post on tasks
- **Teams**: Create and manage (Admin/Manager only)
- **Chat**: Send/receive messages ✅ **UPDATED**
- **Direct Messages**: 1-on-1 private messaging ✨ **NEW**
- **Email Notifications**: 7 notification types ✨ **NEW**
- **Database**: UTF-8mb4 for Greek characters ✅ **FIXED**

#### Frontend (Tailwind + Alpine.js)
- **Settings**: Admin controls for users ✅ **FIXED**
- **Tasks**: Assignee selector, subtasks UI, comments UI ✨ **NEW**
- **Chat**: REST API integration ✅ **UPDATED**
- **Teams**: Create and view teams
- **Responsive**: Mobile-friendly design
- **Greek Language**: Full support throughout

---

## 🚀 Installation

### Prerequisites
- XAMPP (PHP 7.4+ + MySQL 8.0+)
- Web Browser

### Setup (3 Steps)

1. **Start XAMPP**
   - Open XAMPP Control Panel
   - Start Apache and MySQL

2. **Setup Database**
   ```bash
   mysql -u root < database/schema.sql
   mysql -u root < database/demo_users.sql
   ```

3. **Access Application**
   - Open browser: `http://localhost/taskmesh/`
   
---

## 🔑 Test Credentials

### Admin Account
```
Email: admin@taskmesh.com
Password: admin123
```

### Manager Accounts (Greek names ✅)
```
Email: manager1@taskmesh.com | Password: demo123
Name: Γιώργος Παπαδόπουλος

Email: manager2@taskmesh.com | Password: demo123
Name: Μαρία Ιωάννου
```

### Member Accounts (Greek names ✅)
```
user1@taskmesh.com / demo123 - Νίκος Αντωνίου
user2@taskmesh.com / demo123 - Ελένη Δημητρίου
user3@taskmesh.com / demo123 - Κώστας Νικολάου
user4@taskmesh.com / demo123 - Σοφία Γεωργίου
user5@taskmesh.com / demo123 - Δημήτρης Κωνσταντίνου
```

---

## ✅ Feature Testing

**Follow `RUN_THESE_TESTS.md` for complete testing guide.**

Quick tests:
1. Login as admin → Settings → Change user role ✅
2. Tasks → Create with assignee → Add subtasks ✅
3. Click task → Add comments ✅
4. Teams → Create team → Go to Chat → Send message ✅
5. Verify Greek names display correctly ✅

---

## 🎯 What's New (Latest Updates)

### ✨ Added Features
- **Subtasks** - Full CRUD API and UI
- **Comments UI** - Beautiful interface with avatars
- **Task Assignment** - Dropdown selector with user list
- **User Management** - Admin can change roles and status

### 🐛 Fixes
- Greek character encoding (UTF-8mb4)
- User role change API calls
- User status toggle API calls
- Chat message sending (REST API)
   - **Note**: If you get "Invalid email or password", run:
     ```bash
     & "C:\xampp\php\php.exe" C:\xampp\htdocs\taskmesh\fix_admin_password.php
     ```

## 📁 Project Structure

```
taskmesh/
├── api/
│   ├── auth/
│   │   ├── login.php
│   │   ├── register.php
│   │   └── me.php
│   ├── teams/
│   │   ├── index.php
│   │   ├── members.php
│   │   └── messages.php
│   ├── tasks/
│   │   ├── index.php
│   │   └── single.php
│   ├── comments/
│   │   └── index.php
│   └── users/
│       └── index.php
├── config/
│   ├── database.php
│   ├── cors.php
│   └── jwt.php
├── middleware/
│   └── auth.php
├── database/
│   └── schema.sql
├── pages/
│   ├── home.html
│   ├── teams.html
│   ├── tasks.html
│   ├── chat.html
│   ├── profile.html
│   └── settings.html
├── ws_server.php
├── index.html (login)
├── register.html
└── dashboard.html
```

## 🎯 Features by Page

### 🏠 Dashboard (Home)
- 4 stat cards: Active Tasks, Teams, Completed Tasks, Performance %
- Recent 5 tasks with progress bars
- Upcoming deadlines with color-coded dots (green/yellow/red)
- Quick actions grid
- 15 animated background particles

### 👥 Teams
- Grid view with 8 preset colors
- Create team modal (Admin/Manager only)
- View team details with members
- Real-time member count

### 📋 Tasks (Kanban Board)
- 5 columns: TODO, IN_PROGRESS, IN_REVIEW, COMPLETED, CANCELLED
- Priority badges: LOW (gray), MEDIUM (blue), HIGH (orange), URGENT (red)
- Deadline color dots based on days remaining
- Create task modal with all fields
- View task details modal
- Filter by priority

### 💬 Chat
- Teams sidebar (1/4 width)
- Real-time WebSocket messaging
- Typing indicators (3-dot animation)
- Message bubbles with user info
- Auto-scroll to bottom
- Relative timestamps

### 👤 Profile
- Large avatar with initials
- Edit form: first_name, last_name, avatar URL
- Password change with current_password validation
- Role badge (color-coded)
- Success/Error messages

### ⚙️ Settings (Admin Only)
- 4 stat cards: Total Users, Active Users, Teams, Tasks
- Users table with avatars and status
- Change user role modal
- Toggle user active status
- Admin access check

## 🔐 API Endpoints

### Authentication
- `POST /api/auth/register.php` - Register new user
- `POST /api/auth/login.php` - Login user
- `GET /api/auth/me.php` - Get current user

### Teams
- `GET /api/teams/index.php` - Get all teams
- `POST /api/teams/index.php` - Create team (Admin/Manager)
- `POST /api/teams/members.php` - Add member
- `DELETE /api/teams/members.php` - Remove member
- `GET /api/teams/messages.php?team_id=X` - Get messages

### Tasks
- `GET /api/tasks/index.php` - Get all tasks (with filters)
- `POST /api/tasks/index.php` - Create task
- `GET /api/tasks/single.php?id=X` - Get task details
- `PUT /api/tasks/single.php?id=X` - Update task
- `DELETE /api/tasks/single.php?id=X` - Delete task

### Comments
- `GET /api/comments/index.php?task_id=X` - Get comments
- `POST /api/comments/index.php` - Create comment
- `DELETE /api/comments/index.php?id=X` - Delete comment

### Users (Admin)
- `GET /api/users/index.php` - Get all users
- `GET /api/users/index.php?id=X` - Get user
- `PUT /api/users/index.php?id=X` - Update user/role/status

## 🌐 WebSocket Events

### Client → Server
- `joinTeam` - Join team room
- `leaveTeam` - Leave team room
- `sendMessage` - Send message (saves to DB)
- `typing` - Typing indicator

### Server → Client
- `newMessage` - New message broadcast
- `userTyping` - User is typing

## 🎨 Design Features

### Colors
- **Teams**: 8 preset colors (#6366f1, #ec4899, #14b8a6, #f59e0b, #8b5cf6, #ef4444, #06b6d4, #10b981)
- **Status**: TODO (yellow), IN_PROGRESS (blue), IN_REVIEW (purple), COMPLETED (green), CANCELLED (red)
- **Priority**: LOW (gray), MEDIUM (blue), HIGH (orange), URGENT (red)
- **Role**: ADMIN (red), MANAGER (orange), MEMBER (green)

### Animations
- Floating particles (15 on dashboard, 5-6 on login/register)
- Card hover effects (translate-y, shadow)
- Smooth transitions (0.3s ease)
- Notification badge pulse-glow
- Toast slide-in/out
- Typing indicator bounce

### Glassmorphism
- `backdrop-filter: blur(10px)`
- `background: rgba(255, 255, 255, 0.1)`
- `border: 1px solid rgba(255, 255, 255, 0.2)`

## 🔧 Configuration

### Database (config/database.php)
```php
host: localhost
user: root
password: (empty)
database: taskmesh_db
```

### JWT (config/jwt.php)
```php
secret: "taskmesh-super-secret-jwt-key-change-in-production-12345678"
algorithm: HS256
expiry: 7 days
```

### WebSocket (ws_server.php)
```php
host: 0.0.0.0
port: 8080
```

## 📱 Mobile Responsive

- Hamburger menu for mobile (< 1024px)
- Stacked cards on small screens
- Responsive grid (1 column → 2 → 3 → 4 → 5)
- Scrollable tables
- Mobile user menu in header

## 🌙 Dark Mode

- Toggle button in header (moon/sun icon)
- localStorage persistence
- Smooth transition (0.3s)
- Dark gradient background
- Adjusted text colors

## ⚠️ Important Notes

1. **WebSocket Server**: Must run separately with `php ws_server.php`
2. **CORS**: Configured for localhost origins only
3. **JWT Secret**: Change in production
4. **Default Admin**: Change password after first login
5. **File Uploads**: Avatar URLs only (no file upload implemented)

## 🐛 Troubleshooting

### Database connection failed
- Check XAMPP MySQL is running
- Verify credentials in config/database.php

### WebSocket not connecting
- Ensure `php ws_server.php` is running
- Check port 8080 is not in use

### 404 errors
- Verify Apache DocumentRoot points to `C:\xampp\htdocs`
- Check file permissions

### JWT errors
- Clear localStorage and re-login
- Check token expiry (7 days)

## 📄 License

MIT License - Free to use and modify

## 👨‍💻 Developer

Created with ❤️ using PHP, MySQL, Tailwind CSS, and Alpine.js