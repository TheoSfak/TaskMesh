# TaskMesh API - Complete Reference

## 🔧 Backend APIs Status

### ✅ WORKING APIs

#### **Authentication**
- `POST /api/auth/register.php` - Register new user
- `POST /api/auth/login.php` - Login (returns JWT)
- `GET /api/auth/me.php` - Get current user info

#### **Users Management** 
- `GET /api/users/index.php` - Get all users (admin only)
- `GET /api/users/index.php?id={id}` - Get single user
- `PUT /api/users/index.php?id={id}&action=role` - Update user role (admin only)
  - Body: `{"role": "ADMIN|MANAGER|MEMBER"}`
- `PUT /api/users/index.php?id={id}&action=status` - Toggle user active status (admin only)
- `PUT /api/users/index.php?id={id}` - Update user profile
  - Body: `{"first_name": "", "last_name": "", "avatar": "", "password": "", "current_password": ""}`

#### **Tasks**
- `GET /api/tasks/index.php` - Get all tasks (with filters)
  - Query params: `team_id`, `status`, `priority`, `assignee_id`
- `POST /api/tasks/index.php` - Create new task
  - Body: `{"title": "", "description": "", "status": "TODO", "priority": "MEDIUM", "deadline": "", "team_id": "", "assignee_id": ""}`
- `GET /api/tasks/single.php?id={id}` - Get single task
- `PUT /api/tasks/single.php?id={id}` - Update task
  - Body: `{"title": "", "description": "", "status": "", "priority": "", "deadline": "", "assignee_id": ""}`
- `DELETE /api/tasks/single.php?id={id}` - Delete task

#### **Subtasks** ✨ NEW
- `GET /api/subtasks/index.php?task_id={id}` - Get subtasks for task
- `POST /api/subtasks/index.php` - Create subtask
  - Body: `{"task_id": 1, "title": "", "status": "TODO", "deadline": ""}`
- `PUT /api/subtasks/index.php?id={id}` - Update subtask
  - Body: `{"title": "", "status": "TODO|COMPLETED", "deadline": ""}`
- `DELETE /api/subtasks/index.php?id={id}` - Delete subtask

#### **Comments**
- `GET /api/comments/index.php?task_id={id}` - Get comments for task
- `POST /api/comments/index.php` - Create comment
  - Body: `{"task_id": 1, "content": ""}`
- `DELETE /api/comments/index.php?id={id}` - Delete comment

#### **Teams**
- `GET /api/teams/index.php` - Get all teams (user is member of)
- `POST /api/teams/index.php` - Create team (admin/manager only)
  - Body: `{"name": "", "description": "", "color": "#6366f1"}`

#### **Team Members**
- `POST /api/teams/members.php` - Add member to team
  - Body: `{"team_id": 1, "user_id": 2}`
- `DELETE /api/teams/members.php?team_id={id}&user_id={id}` - Remove member

#### **Team Chat** ✨ UPDATED
- `GET /api/teams/messages.php?team_id={id}&limit=50` - Get messages
- `POST /api/teams/messages.php` - Send message ✨ NEW
  - Body: `{"team_id": 1, "content": ""}`

---

## 🔴 ISSUES TO FIX (Frontend)

### 1. **User Management UI**
**Backend:** ✅ Working  
**Frontend:** ❌ Missing UI

**What needs to be added:**
- Button to edit user role (call `PUT /api/users/index.php?id=X&action=role`)
- Button to toggle user active/inactive status (call `PUT /api/users/index.php?id=X&action=status`)
- Admin panel to manage all users

**Location:** Likely in `settings.html` or need to create admin dashboard

---

### 2. **Task Assignment**
**Backend:** ✅ Working  
**Frontend:** ❌ May not be sending `assignee_id`

**What to check:**
- Task creation form should include user selector for `assignee_id`
- Task edit form should allow changing `assignee_id`
- Both should send to existing APIs

**API Endpoints:**
- Create: `POST /api/tasks/index.php` with `{"assignee_id": 2}`
- Update: `PUT /api/tasks/single.php?id=X` with `{"assignee_id": 2}`

---

### 3. **Subtasks** ✨
**Backend:** ✅ NEW API Created  
**Frontend:** ❌ Not implemented yet

**What to add:**
- UI section in task detail view to show subtasks
- Form to create new subtasks
- Checkbox to mark subtasks as complete
- Delete button for subtasks

**Example UI Flow:**
```javascript
// Get subtasks
fetch('/api/subtasks/index.php?task_id=1')

// Create subtask
fetch('/api/subtasks/index.php', {
  method: 'POST',
  body: JSON.stringify({task_id: 1, title: 'Subtask 1'})
})

// Toggle completion
fetch('/api/subtasks/index.php?id=5', {
  method: 'PUT',
  body: JSON.stringify({status: 'COMPLETED'})
})
```

---

### 4. **Task Comments**
**Backend:** ✅ Working  
**Frontend:** ❌ May not be implemented

**What to check:**
- Task detail view should show comments section
- Form to add new comment
- Display existing comments with author and timestamp

**API Usage:**
```javascript
// Load comments
fetch('/api/comments/index.php?task_id=1')

// Add comment
fetch('/api/comments/index.php', {
  method: 'POST',
  body: JSON.stringify({task_id: 1, content: 'My comment'})
})
```

---

### 5. **Teams & Team Chat**
**Backend:** ✅ Working  
**Frontend:** ❌ Partially implemented

**What to check/add:**
- Team creation form (only for ADMIN/MANAGER users)
- Team members management (add/remove)
- Team chat interface
- Connect to WebSocket for real-time messages (see `ws_server.php`)

**Chat API Usage:**
```javascript
// Load messages
fetch('/api/teams/messages.php?team_id=1&limit=50')

// Send message ✨ NEW
fetch('/api/teams/messages.php', {
  method: 'POST',
  body: JSON.stringify({team_id: 1, content: 'Hello team!'})
})
```

---

## 🗄️ Database Notes

### Character Encoding
✅ **FIXED** - Added `SET NAMES utf8mb4` to `demo_users.sql`

The database is configured for Greek characters:
- Database charset: `utf8mb4`
- Collation: `utf8mb4_unicode_ci`
- Connection uses: `charset=utf8mb4`

Demo users include Greek names:
- Γιώργος Παπαδόπουλος (Manager)
- Μαρία Ιωάννου (Manager)
- Νίκος Αντωνίου (Member)
- etc.

**To reload demo users properly:**
```bash
mysql -u root < database/schema.sql
mysql -u root < database/demo_users.sql
```

---

## 🎯 Priority Action Items

### HIGH PRIORITY (Core functionality broken)
1. ✅ **Create Subtasks API** - DONE
2. ✅ **Add POST endpoint for chat messages** - DONE
3. **Implement subtasks UI in frontend**
4. **Implement task comments UI in frontend**
5. **Add user management UI for admins**

### MEDIUM PRIORITY (Enhancements)
6. **Add task assignment UI (assignee selector)**
7. **Implement team creation/management UI**
8. **Build team chat interface**

### LOW PRIORITY (Polish)
9. **Connect WebSocket for real-time updates**
10. **Add notifications system**

---

## 🚀 Quick Test Commands

### Test API Endpoints
```bash
# Login
curl -X POST http://localhost/TaskMesh/api/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@taskmesh.com","password":"admin123"}'

# Get tasks (use token from login)
curl http://localhost/TaskMesh/api/tasks/index.php \
  -H "Authorization: Bearer YOUR_TOKEN"

# Get subtasks
curl http://localhost/TaskMesh/api/subtasks/index.php?task_id=1 \
  -H "Authorization: Bearer YOUR_TOKEN"

# Send chat message
curl -X POST http://localhost/TaskMesh/api/teams/messages.php \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"team_id":1,"content":"Hello!"}'
```

---

## 📋 Summary

### ✅ Completed Backend Work
- ✅ Character encoding fixed for Greek text
- ✅ Subtasks API fully implemented
- ✅ Chat POST endpoint added
- ✅ All CRUD operations for users, tasks, teams, comments

### ❌ Frontend Work Needed
- User management interface (edit role, toggle status)
- Subtasks UI
- Comments UI on task details
- Task assignment selector
- Team management interface
- Chat interface

### 🔧 Next Steps
1. Check which frontend files handle tasks, users, teams
2. Add missing UI components
3. Wire up existing backend APIs
4. Test end-to-end functionality
