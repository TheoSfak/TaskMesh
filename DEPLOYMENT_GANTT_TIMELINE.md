# Deployment Guide: Gantt Chart & Timeline Feature

## Οδηγίες ανάπτυξης στο production site: ecowatt.gr/task

### 📋 Περιεχόμενα
1. [Database Setup](#1-database-setup)
2. [File Upload](#2-file-upload)
3. [Verification](#3-verification)
4. [Troubleshooting](#4-troubleshooting)

---

## 1. Database Setup

### Σύνδεση στο MySQL της ecowatt.gr

```bash
mysql -u your_username -p taskmesh_db
```

### Εκτέλεση SQL εντολών

Εκτελέστε τις παρακάτω εντολές για να δημιουργήσετε τους πίνακες:

```sql
-- Table: task_dependencies
CREATE TABLE IF NOT EXISTS task_dependencies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    depends_on_task_id INT NOT NULL,
    dependency_type ENUM('blocks', 'must_finish_before', 'related') DEFAULT 'must_finish_before',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (depends_on_task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    UNIQUE KEY unique_dependency (task_id, depends_on_task_id),
    INDEX idx_task_id (task_id),
    INDEX idx_depends_on (depends_on_task_id),
    INDEX idx_dependency_type (dependency_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: milestones
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
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_team_id (team_id),
    INDEX idx_target_date (target_date),
    INDEX idx_status (status),
    INDEX idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Επιβεβαίωση δημιουργίας

```sql
SHOW TABLES LIKE '%dependencies%';
SHOW TABLES LIKE 'milestones';
DESCRIBE task_dependencies;
DESCRIBE milestones;
```

---

## 2. File Upload

### Αρχεία προς ανέβασμα στο FTP

Χρησιμοποιήστε FTP client (π.χ. FileZilla) για να ανεβάσετε τα παρακάτω αρχεία:

#### 🔵 Backend API Files (Νέα αρχεία)

```
/task/api/tasks/dependencies.php          ← Από: api/tasks/dependencies.php
/task/api/tasks/critical-path.php         ← Από: api/tasks/critical-path.php
/task/api/tasks/timeline.php              ← Από: api/tasks/timeline.php
/task/api/milestones/index.php            ← Από: api/milestones/index.php
```

#### 🔵 Frontend Files (Production versions)

```
/task/pages/timeline-production.html      ← Από: pages/timeline-production.html
/task/dashboard-production.html           ← Από: dashboard-production.html
```

#### 🔵 Modified Files (Προαιρετικό - αν θέλετε να ενημερώσετε τα local versions)

```
/task/pages/tasks.html                    ← Από: pages/tasks.html (με Dependencies modal)
/task/pages/home.html                     ← Από: pages/home.html (με Timeline button)
```

#### 🔵 Documentation (Προαιρετικό)

```
/task/docs/GANTT_TIMELINE_GUIDE.md        ← Από: docs/GANTT_TIMELINE_GUIDE.md
```

### Δομή φακέλων που πρέπει να υπάρχει

```
ecowatt.gr/
└── task/
    ├── api/
    │   ├── tasks/
    │   │   ├── index.php
    │   │   ├── single.php
    │   │   ├── dependencies.php          ← ΝΕΟ
    │   │   ├── critical-path.php         ← ΝΕΟ
    │   │   └── timeline.php              ← ΝΕΟ
    │   └── milestones/
    │       └── index.php                 ← ΝΕΟ
    ├── pages/
    │   ├── timeline-production.html      ← ΝΕΟ
    │   ├── tasks.html                    ← ΕΝΗΜΕΡΩΜΕΝΟ
    │   └── home.html                     ← ΕΝΗΜΕΡΩΜΕΝΟ
    ├── dashboard-production.html         ← ΕΝΗΜΕΡΩΜΕΝΟ
    └── docs/
        └── GANTT_TIMELINE_GUIDE.md       ← ΝΕΟ
```

### Μετονομασία αρχείων στο production (μέσω FTP ή SSH)

Αφού ανεβάσετε τα `-production.html` αρχεία, πρέπει να τα μετονομάσετε:

**Μέσω SSH:**
```bash
cd /path/to/ecowatt.gr/task
mv pages/timeline-production.html pages/timeline.html
mv dashboard-production.html dashboard.html
```

**Ή μέσω FileZilla:**
- Δεξί κλικ → Rename
- `timeline-production.html` → `timeline.html`
- `dashboard-production.html` → `dashboard.html`

---

## 3. Verification

### Έλεγχος Backend APIs

Ανοίξτε τα παρακάτω URLs στον browser (με valid JWT token):

```
https://ecowatt.gr/task/api/tasks/timeline.php
https://ecowatt.gr/task/api/tasks/critical-path.php
https://ecowatt.gr/task/api/tasks/dependencies.php
https://ecowatt.gr/task/api/milestones/index.php
```

**Αναμενόμενη απάντηση (χωρίς token):**
```json
{
  "error": "Access denied"
}
```

**Αναμενόμενη απάντηση (με token):**
```json
{
  "tasks": [...],
  "milestones": [...]
}
```

### Έλεγχος Frontend

1. **Είσοδος στο σύστημα:**
   ```
   https://ecowatt.gr/task/index.html
   ```

2. **Dashboard:**
   ```
   https://ecowatt.gr/task/dashboard.html
   ```
   - Ελέγξτε ότι στο sidebar βλέπετε το "Timeline" link με το εικονίδιο `fa-chart-gantt`

3. **Timeline Page:**
   ```
   https://ecowatt.gr/task/pages/timeline.html
   ```
   - Πρέπει να φορτώσει το Gantt Chart
   - Να δείχνει φίλτρα (Team, User, Date Range)
   - Να έχει zoom controls (Day, Week, Month, Quarter)
   - Να υπάρχει κουμπί "Νέο Milestone"

4. **Tasks Page - Dependencies:**
   ```
   https://ecowatt.gr/task/dashboard.html#tasks
   ```
   - Ανοίξτε μια εργασία για επεξεργασία
   - Ελέγξτε ότι υπάρχει κουμπί "Εξαρτήσεις"
   - Κλικ στο κουμπί πρέπει να ανοίξει modal με λίστα εξαρτήσεων

### Δοκιμές λειτουργικότητας

#### Test 1: Δημιουργία Dependency
1. Πηγαίνετε στο Tasks
2. Επιλέξτε μια εργασία
3. Κλικ "Εξαρτήσεις"
4. Προσθέστε μια εξάρτηση από άλλη εργασία
5. Ελέγξτε ότι αποθηκεύτηκε

#### Test 2: Προβολή Gantt Chart
1. Πηγαίνετε στο Timeline
2. Επιλέξτε μια ομάδα από τα φίλτρα
3. Ελέγξτε ότι εμφανίζονται οι εργασίες
4. Δοκιμάστε zoom (Day/Week/Month)
5. Ελέγξτε ότι φαίνονται τα arrows μεταξύ εξαρτώμενων εργασιών

#### Test 3: Critical Path
1. Στο Timeline page
2. Κλικ "Critical Path"
3. Ελέγξτε ότι οι critical tasks είναι κόκκινες
4. Ελέγξτε ότι εμφανίζεται το info box με total duration

#### Test 4: Milestone Creation
1. Στο Timeline page
2. Κλικ "Νέο Milestone"
3. Συμπληρώστε τη φόρμα
4. Δημιουργήστε το milestone
5. Ελέγξτε ότι αποθηκεύτηκε

---

## 4. Troubleshooting

### Πρόβλημα: "Access denied" σε όλα τα API calls

**Λύση:**
- Ελέγξτε ότι το JWT token είναι valid
- Ελέγξτε το `middleware/auth.php`
- Ελέγξτε το `config/jwt.php` (secret key)

### Πρόβλημα: Δεν φορτώνει το Gantt Chart

**Λύση:**
- Άνοιξτε Developer Console (F12)
- Ελέγξτε για errors στο Network tab
- Ελέγξτε ότι το Frappe Gantt library φορτώνει από CDN:
  ```
  https://cdn.jsdelivr.net/npm/frappe-gantt@0.6.1/dist/frappe-gantt.min.js
  ```
- Ελέγξτε ότι το `API_BASE` στο `timeline.html` είναι `/task/api`

### Πρόβλημα: "Table doesn't exist" errors

**Λύση:**
- Εκτελέστε ξανά τις SQL εντολές από την ενότητα 1
- Ελέγξτε ότι συνδεθήκατε στο σωστό database:
  ```sql
  USE taskmesh_db;
  SHOW TABLES;
  ```

### Πρόβλημα: Dependencies modal δεν ανοίγει

**Λύση:**
- Ελέγξτε ότι το `tasks.html` είναι ενημερωμένο
- Ελέγξτε στο console για JavaScript errors
- Ελέγξτε ότι το Alpine.js φορτώνει σωστά

### Πρόβλημα: "Circular dependency detected"

**Αυτό δεν είναι σφάλμα!**
- Το σύστημα αποτρέπει κυκλικές εξαρτήσεις
- Επιλέξτε άλλη εργασία που δεν δημιουργεί κύκλο

### Πρόβλημα: Δεν βλέπω το Timeline link στο sidebar

**Λύση:**
- Ελέγξτε ότι το `dashboard-production.html` ανέβηκε και μετονομάστηκε σωστά
- Κάντε hard refresh (Ctrl+Shift+R)
- Ελέγξτε browser cache

### Πρόβλημα: 404 Not Found σε αρχεία

**Λύση:**
- Ελέγξτε ότι τα paths είναι case-sensitive (Linux server!)
- Ελέγξτε ότι τα αρχεία ανέβηκαν στο σωστό φάκελο
- Ελέγξτε file permissions (πρέπει να είναι 644 για αρχεία, 755 για φακέλους)

```bash
# Via SSH
chmod 644 /path/to/ecowatt.gr/task/pages/timeline.html
chmod 644 /path/to/ecowatt.gr/task/api/tasks/*.php
chmod 755 /path/to/ecowatt.gr/task/api/tasks
chmod 755 /path/to/ecowatt.gr/task/api/milestones
```

---

## 📊 Quick Reference

### API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/task/api/tasks/timeline.php` | GET | Επιστρέφει tasks με dates, dependencies, assignees |
| `/task/api/tasks/critical-path.php` | GET | Υπολογισμός critical path |
| `/task/api/tasks/dependencies.php` | GET | Λήψη dependencies για task |
| `/task/api/tasks/dependencies.php` | POST | Δημιουργία dependency |
| `/task/api/tasks/dependencies.php` | DELETE | Διαγραφή dependency |
| `/task/api/milestones/index.php` | GET | Λήψη milestones |
| `/task/api/milestones/index.php` | POST | Δημιουργία milestone |
| `/task/api/milestones/index.php` | PUT | Ενημέρωση milestone |
| `/task/api/milestones/index.php` | DELETE | Διαγραφή milestone |

### File Sizes

- `timeline.html`: ~23 KB
- `dependencies.php`: ~7 KB
- `critical-path.php`: ~6 KB
- `timeline.php`: ~6.5 KB
- `milestones/index.php`: ~8 KB

### External Dependencies (CDN)

- Frappe Gantt: `https://cdn.jsdelivr.net/npm/frappe-gantt@0.6.1/`
- Alpine.js: `https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/`
- Tailwind CSS: `https://cdn.tailwindcss.com`
- Font Awesome: `https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/`

---

## ✅ Deployment Checklist

- [ ] Database tables created (`task_dependencies`, `milestones`)
- [ ] Backend APIs uploaded (`dependencies.php`, `critical-path.php`, `timeline.php`, `milestones/index.php`)
- [ ] Frontend production files uploaded (`timeline-production.html`, `dashboard-production.html`)
- [ ] Production files renamed (remove `-production` suffix)
- [ ] Timeline link visible in dashboard sidebar
- [ ] Gantt Chart loads correctly
- [ ] Dependencies modal works in Tasks page
- [ ] Critical Path calculation works
- [ ] Milestone creation works
- [ ] API authentication working (JWT)
- [ ] No console errors (F12)
- [ ] File permissions correct (644/755)

---

## 🎉 Success!

Αν όλα τα παραπάνω λειτουργούν, το Gantt Chart & Timeline feature έχει εγκατασταθεί επιτυχώς!

### Επόμενα βήματα:
1. Δημιουργήστε dependencies μεταξύ των εργασιών σας
2. Ορίστε milestones για το project
3. Χρησιμοποιήστε το Critical Path για να βρείτε bottlenecks
4. Εξάγετε PDF reports (coming soon)

### Support:
- Documentation: `/task/docs/GANTT_TIMELINE_GUIDE.md`
- User Guide: Ανοίξτε το αρχείο για λεπτομερείς οδηγίες χρήσης
