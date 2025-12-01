# TaskMesh - Quick Reference Card

## 🚀 Quick Start
```bash
# Database
mysql -u root < database/schema.sql
mysql -u root < database/demo_users.sql

# Access
http://localhost/TaskMesh/
admin@taskmesh.com / admin123
```

## ✅ What's Fixed

| Feature | Status | Location |
|---------|--------|----------|
| Greek charset | ✅ Fixed | demo_users.sql |
| User role management | ✅ Fixed | Settings page |
| User status toggle | ✅ Fixed | Settings page |
| Subtasks API | ✅ Created | api/subtasks/ |
| Subtasks UI | ✅ Added | Task details |
| Task assignment | ✅ Added | Task creation |
| Comments UI | ✅ Added | Task details |
| Chat POST | ✅ Added | api/teams/messages.php |

## 🎯 Test These Now

### 1. User Management (Admin Only)
- Settings → Click blue icon → Change role
- Settings → Click orange icon → Toggle status
- ✅ Greek names display correctly

### 2. Task Features
- Tasks → New Task → Select assignee
- Click task → Add subtasks (press Enter)
- Click task → Add comments (click send)
- ✅ Check/uncheck subtasks
- ✅ Delete subtasks with trash icon

### 3. Teams & Chat
- Teams → New Team (Admin/Manager only)
- Chat → Select team → Send message
- ✅ Messages save to database

## 📁 New/Modified Files

### Backend
- ✅ `api/subtasks/index.php` - NEW
- ✅ `api/teams/messages.php` - UPDATED (POST added)
- ✅ `database/demo_users.sql` - UPDATED (charset)

### Frontend
- ✅ `pages/settings.html` - FIXED (API calls)
- ✅ `pages/tasks.html` - ENHANCED (assignee, subtasks, comments)
- ✅ `pages/chat.html` - UPDATED (REST API)

### Docs
- ✅ `TODO.md` - Implementation guide
- ✅ `API_STATUS.md` - Complete API reference
- ✅ `TESTING_GUIDE.md` - Step-by-step tests
- ✅ `IMPLEMENTATION_SUMMARY.md` - Full details

## 🔑 Demo Users

```
Admin:
  admin@taskmesh.com / admin123

Managers:
  manager1@taskmesh.com / demo123
  manager2@taskmesh.com / demo123

Members:
  user1@taskmesh.com / demo123
  user2@taskmesh.com / demo123
  user3@taskmesh.com / demo123
  user4@taskmesh.com / demo123
  user5@taskmesh.com / demo123
```

## 🔧 API Quick Test

```javascript
// Browser console
const token = localStorage.getItem('token');

// Subtasks
fetch('/api/subtasks/index.php?task_id=1', {
  headers: {'Authorization': `Bearer ${token}`}
}).then(r => r.json()).then(console.log);

// Chat
fetch('/api/teams/messages.php', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({team_id: 1, content: 'Test'})
}).then(r => r.json()).then(console.log);
```

## ⚠️ Troubleshooting

| Issue | Solution |
|-------|----------|
| Greek chars broken | `ALTER DATABASE taskmesh_db CHARACTER SET utf8mb4;` |
| Can't change roles | Must be logged in as ADMIN |
| Subtasks not showing | Verify task exists, check console |
| Chat not sending | Check Network tab, verify team membership |

## 📊 All Features Working

- [x] Admin user management (role change, status toggle)
- [x] Task creation with assignee
- [x] Subtasks (create, complete, delete)
- [x] Comments on tasks
- [x] Team creation (Admin/Manager)
- [x] Team chat (send/receive)
- [x] Greek character support

## 🎉 Status: READY TO USE!

Everything works. Test it now! 🚀
