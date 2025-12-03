# Timeline Feature - Deployment Guide

## 📋 Overview
Complete Timeline & Gantt Chart feature with Milestones support.

## 🗄️ Database Changes

### 1. Run this SQL on production database:

```sql
-- Create task_dependencies table
CREATE TABLE IF NOT EXISTS task_dependencies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    depends_on_task_id INT NOT NULL,
    dependency_type ENUM('blocks', 'must_finish_before', 'related') DEFAULT 'must_finish_before',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (depends_on_task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    UNIQUE KEY unique_dependency (task_id, depends_on_task_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create milestones table
CREATE TABLE IF NOT EXISTS milestones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    target_date DATE NOT NULL,
    status ENUM('upcoming', 'in_progress', 'completed', 'missed') DEFAULT 'upcoming',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_team_date (team_id, target_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create task_assignments table
CREATE TABLE IF NOT EXISTS task_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by INT,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_assignment (task_id, user_id),
    INDEX idx_task (task_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 📁 Files to Upload

### Modified Files:

1. **dashboard.html**
   - Added Frappe Gantt library (CSS & JS)
   - Location: Root directory

2. **pages/timeline.html** (NEW FILE)
   - Complete Timeline page with Gantt chart
   - Milestones management
   - Edit/Delete functionality
   - Location: pages/

3. **api/tasks/timeline.php** (NEW FILE)
   - Timeline data endpoint
   - Location: api/tasks/

4. **api/tasks/critical-path.php** (NEW FILE)
   - Critical path calculation
   - Location: api/tasks/

5. **api/tasks/dependencies.php** (NEW FILE)
   - Task dependencies management
   - Location: api/tasks/

6. **api/milestones/index.php** (NEW FILE)
   - Milestones CRUD operations
   - Location: api/milestones/

7. **middleware/auth.php** (MODIFIED)
   - Added getallheaders() polyfill
   - Location: middleware/

8. **config/paths.php** (MODIFIED)
   - Fixed empty string detection
   - Location: config/

9. **dashboard-production.html** (MODIFIED)
   - Added Frappe Gantt library
   - Improved Alpine.js initialization
   - Location: Root directory

## 🚀 Deployment Steps

### Step 1: Backup
```bash
# Backup current production database
mysqldump -u username -p database_name > backup_$(date +%Y%m%d).sql

# Backup current files
tar -czf backup_files_$(date +%Y%m%d).tar.gz /path/to/production/
```

### Step 2: Database Migration
```bash
# Connect to production database
mysql -u username -p database_name

# Run the SQL from section above
source timeline_tables.sql
```

### Step 3: Upload Files

**Upload these files via FTP/SFTP:**

```
Root:
├── dashboard.html (MODIFIED)
├── dashboard-production.html (MODIFIED)

api/tasks/:
├── timeline.php (NEW)
├── critical-path.php (NEW)
├── dependencies.php (NEW)

api/milestones/:
└── index.php (NEW)

pages/:
└── timeline.html (NEW)

middleware/:
└── auth.php (MODIFIED)

config/:
└── paths.php (MODIFIED)
```

### Step 4: Set Permissions
```bash
chmod 644 dashboard.html
chmod 644 dashboard-production.html
chmod 755 api/tasks/
chmod 644 api/tasks/*.php
chmod 755 api/milestones/
chmod 644 api/milestones/*.php
chmod 644 pages/timeline.html
chmod 644 middleware/auth.php
chmod 644 config/paths.php
```

### Step 5: Test

1. **Test Database:**
   ```sql
   -- Verify tables exist
   SHOW TABLES LIKE '%milestones%';
   SHOW TABLES LIKE '%task_dependencies%';
   SHOW TABLES LIKE '%task_assignments%';
   
   -- Check table structure
   DESCRIBE milestones;
   DESCRIBE task_dependencies;
   DESCRIBE task_assignments;
   ```

2. **Test API Endpoints:**
   - GET `/api/tasks/timeline.php` - Should return tasks with dates
   - GET `/api/tasks/critical-path.php` - Should return critical path
   - GET `/api/milestones/index.php` - Should return milestones
   - POST `/api/milestones/index.php` - Should create milestone

3. **Test Frontend:**
   - Navigate to Timeline page
   - Check if Gantt chart renders
   - Create a milestone
   - Edit a milestone
   - Delete a milestone

## 🐛 Troubleshooting

### Issue: Gantt chart shows error
**Solution:** Check browser console. Most common issue is library not loaded.
```javascript
// Check in browser console:
typeof Gantt !== 'undefined' // Should return true
```

### Issue: HTTP 500 on Timeline API
**Solution:** Check these:
1. Database connection in config/database.php
2. All 3 tables exist (task_dependencies, milestones, task_assignments)
3. PHP error logs: `tail -f /path/to/error.log`

### Issue: "language: 'el'" error
**Solution:** The Greek language is NOT supported in Frappe Gantt 0.6.1. We removed it.

### Issue: Milestones not showing
**Solution:** 
- Milestones are now shown by default (all teams)
- Check console: `Timeline data:` should show milestones array
- Verify milestones table has data: `SELECT * FROM milestones;`

### Issue: Edit milestone not working
**Solution:** Check that:
1. PUT method is allowed on server
2. API endpoint: `/api/milestones/index.php?id=X`
3. Browser console for errors

## 📊 Features Included

### Timeline & Gantt Chart
- ✅ Gantt chart visualization
- ✅ Task dependencies
- ✅ Critical path calculation
- ✅ Multiple view modes (Day/Week/Month)
- ✅ Zoom controls
- ✅ Filters (Team, User, Date range)

### Milestones
- ✅ Create milestones
- ✅ Edit milestones
- ✅ Delete milestones
- ✅ Status management (upcoming/in_progress/completed/missed)
- ✅ Beautiful card design with animations
- ✅ Days counter
- ✅ Team grouping

### Design
- ✅ Gradient backgrounds
- ✅ Hover animations
- ✅ Shine effects
- ✅ Responsive grid
- ✅ Color-coded status
- ✅ Modern shadows

## 🔒 Security Notes

1. All APIs use JWT authentication
2. All inputs are sanitized in PHP
3. SQL uses prepared statements
4. CSRF protection via JWT
5. DELETE operations require confirmation

## 📞 Support

If you encounter issues:
1. Check browser console (F12)
2. Check PHP error logs
3. Verify database tables exist
4. Test API endpoints with curl/Postman
5. Clear browser cache (Ctrl+Shift+R)

## ✅ Success Checklist

- [ ] Database tables created
- [ ] All files uploaded
- [ ] File permissions set
- [ ] Database connection works
- [ ] Timeline page loads
- [ ] Gantt chart displays
- [ ] Can create milestone
- [ ] Can edit milestone
- [ ] Can delete milestone
- [ ] No console errors
- [ ] Mobile responsive works

---

**Deployment Date:** _____________
**Deployed By:** _____________
**Production URL:** https://yourdomain.com/TaskMesh/dashboard.html#timeline
