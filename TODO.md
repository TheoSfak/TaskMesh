# TaskMesh - Todo List & Current Status

## ✅ BACKEND FIXES COMPLETED

### 1. Greek Character Encoding ✅
**Problem:** Demo users had Greek names but charset wasn't explicitly set  
**Fix:** Added `SET NAMES utf8mb4` to `database/demo_users.sql`  
**Status:** FIXED - Greek characters (Γιώργος, Μαρία, etc.) will now display correctly

### 2. Subtasks API ✅
**Problem:** Subtasks feature didn't exist  
**Fix:** Created complete `/api/subtasks/index.php` with all CRUD operations  
**Endpoints:**
- `GET ?task_id=X` - Get all subtasks for a task
- `POST` - Create subtask (body: `{task_id, title, status, deadline}`)
- `PUT ?id=X` - Update subtask (body: `{title, status, deadline}`)
- `DELETE ?id=X` - Delete subtask

### 3. Team Chat POST Endpoint ✅
**Problem:** Could only GET messages, not send them  
**Fix:** Added POST method to `/api/teams/messages.php`  
**Usage:** `POST` with body `{team_id, content}` - Returns created message with user info

---

## ⚠️ FRONTEND WORK REQUIRED

### 4. User Management (Admin Functions) ❌
**Backend:** ✅ Working APIs exist  
**Frontend:** ❌ No UI to use them  

**APIs Ready to Use:**
```javascript
// Change user role (ADMIN/MANAGER/MEMBER)
PUT /api/users/index.php?id={userId}&action=role
Body: {"role": "ADMIN"}

// Toggle user active/inactive status
PUT /api/users/index.php?id={userId}&action=status
```

**What's Needed:**
- Settings page needs "User Management" section (admin only)
- Table showing all users with role dropdown and active/inactive toggle
- Check `pages/settings.html` and add these controls

---

### 5. Task Assignment ⚠️
**Backend:** ✅ Working - accepts `assignee_id`  
**Frontend:** ❌ Probably not sending it

**Check These Files:**
- Task creation form (wherever new tasks are created)
- Task edit form
- Add user dropdown selector for `assignee_id`

**APIs Ready:**
```javascript
// Create task with assignee
POST /api/tasks/index.php
Body: {title, description, assignee_id: 5, ...}

// Update task assignee
PUT /api/tasks/single.php?id=X
Body: {assignee_id: 5}
```

---

### 6. Subtasks UI ❌
**Backend:** ✅ NEW - Fully functional API  
**Frontend:** ❌ Needs to be built

**Implementation Guide:**
```javascript
// In task detail view, add subtasks section:

// 1. Load subtasks
async function loadSubtasks(taskId) {
  const response = await fetch(`/api/subtasks/index.php?task_id=${taskId}`, {
    headers: {'Authorization': `Bearer ${token}`}
  });
  return response.json();
}

// 2. Create subtask
async function createSubtask(taskId, title) {
  await fetch('/api/subtasks/index.php', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({task_id: taskId, title: title})
  });
}

// 3. Toggle complete
async function toggleSubtask(subtaskId, currentStatus) {
  const newStatus = currentStatus === 'TODO' ? 'COMPLETED' : 'TODO';
  await fetch(`/api/subtasks/index.php?id=${subtaskId}`, {
    method: 'PUT',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({status: newStatus})
  });
}
```

**UI Elements Needed:**
- Subtasks list under task details
- Input field + "Add Subtask" button
- Checkboxes to mark complete/incomplete
- Delete button per subtask

---

### 7. Task Comments ❌
**Backend:** ✅ Working API at `/api/comments/index.php`  
**Frontend:** ❌ Needs implementation

**Implementation:**
```javascript
// Load comments
async function loadComments(taskId) {
  const response = await fetch(`/api/comments/index.php?task_id=${taskId}`, {
    headers: {'Authorization': `Bearer ${token}`}
  });
  return response.json();
}

// Add comment
async function addComment(taskId, content) {
  await fetch('/api/comments/index.php', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({task_id: taskId, content: content})
  });
}
```

**UI Elements Needed:**
- Comments section in task detail view
- List of existing comments (user avatar, name, timestamp, content)
- Text area + "Add Comment" button

---

### 8. Teams & Groups ⚠️
**Backend:** ✅ All APIs working  
**Frontend:** ⚠️ Partially implemented

**Check `pages/teams.html`:**

**APIs Available:**
```javascript
// Get user's teams
GET /api/teams/index.php

// Create team (ADMIN/MANAGER only)
POST /api/teams/index.php
Body: {name, description, color}

// Add member
POST /api/teams/members.php
Body: {team_id, user_id}

// Remove member
DELETE /api/teams/members.php?team_id=X&user_id=Y
```

**What to Verify:**
- Can users create teams? (check role restrictions)
- Can users add/remove members?
- Is there a team settings page?

---

### 9. Team Chat ⚠️
**Backend:** ✅ GET and POST both working  
**Frontend:** ⚠️ Partially implemented

**Check `pages/chat.html`:**

**Full Chat API:**
```javascript
// Load messages
async function loadMessages(teamId, limit = 50) {
  const response = await fetch(
    `/api/teams/messages.php?team_id=${teamId}&limit=${limit}`,
    {headers: {'Authorization': `Bearer ${token}`}}
  );
  return response.json(); // Returns array in chronological order
}

// Send message ✨ NEW
async function sendMessage(teamId, content) {
  const response = await fetch('/api/teams/messages.php', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({team_id: teamId, content: content})
  });
  return response.json(); // Returns created message
}
```

**What's Needed:**
- Chat interface with message list
- Input field + Send button
- Auto-scroll to latest message
- Consider polling or WebSocket for real-time updates

---

### 10. WebSocket for Real-Time Updates (Optional) 🔮
**Status:** `ws_server.php` exists but needs frontend integration

This is optional - you can use polling (check for new messages every few seconds) instead.

---

## 📁 Files to Check/Edit

### Frontend Pages:
1. **`pages/settings.html`** - Add user management for admins
2. **`pages/tasks.html`** - Add assignee selector to task forms
3. **Task detail view** (wherever it is) - Add subtasks + comments sections
4. **`pages/teams.html`** - Verify team creation/management works
5. **`pages/chat.html`** - Verify chat send/receive works

### Look for JavaScript files that handle:
- Task creation/editing
- User management
- Team operations
- Chat interface

---

## 🧪 How to Test

### 1. Test Charset Fix
```bash
cd c:\xampp\htdocs\TaskMesh
mysql -u root
```
```sql
USE taskmesh_db;
SOURCE database/demo_users.sql;
SELECT first_name, last_name FROM users;
```
Should show: Γιώργος, Μαρία, Νίκος, Ελένη, etc. correctly

### 2. Test Subtasks API
Use browser console or Postman:
```javascript
// Create subtask
fetch('http://localhost/TaskMesh/api/subtasks/index.php', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer YOUR_TOKEN',
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    task_id: 1,
    title: 'Test Subtask'
  })
})
```

### 3. Test Chat
```javascript
// Send message
fetch('http://localhost/TaskMesh/api/teams/messages.php', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer YOUR_TOKEN',
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    team_id: 1,
    content: 'Γεια σου! (Hello in Greek)'
  })
})
```

---

## 🎯 Recommended Order of Implementation

### Phase 1: Core Task Features
1. ✅ Subtasks API (DONE)
2. Add subtasks UI to task detail view
3. Add comments UI to task detail view
4. Add assignee selector to task forms

### Phase 2: User Management
5. Add admin user management interface
6. Allow admins to change roles and toggle user status

### Phase 3: Team Features
7. Verify/fix team creation and member management
8. ✅ Chat send endpoint (DONE)
9. Implement chat interface
10. (Optional) Add WebSocket for real-time updates

---

## 📞 Need Help?

All backend APIs are documented in `API_STATUS.md`

Each API is tested and working - the work now is connecting the frontend to use them!
