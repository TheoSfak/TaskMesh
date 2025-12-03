# 🔧 Path Configuration System

## Overview
TaskMesh now includes an **automatic path detection system** that eliminates the need for separate production/local configuration files. The system automatically detects where the application is installed and configures all paths accordingly.

## How It Works

### 1. Auto-Detection (Default)
The system automatically detects the installation path from the URL:

- **Root Installation**: `http://domain.com/` → Path: `` (empty)
- **Subdirectory**: `http://domain.com/task/` → Path: `/task`
- **Deeper Path**: `http://domain.com/apps/task/` → Path: `/apps/task`

### 2. API Configuration
The detected path is:
1. Saved to database (`system_settings` table, `installation_path` key)
2. Loaded on every request via `config/paths.php`
3. Cached for performance

### 3. Frontend Integration
Dashboard files automatically fetch path configuration:

```javascript
// Auto-detection happens in dashboard.html
await loadPathConfig(); // Loads from API

// All URLs use detected paths
API_URL       // e.g., "http://domain.com/task/api"
PAGES_BASE    // e.g., "/task/pages"
ASSETS_BASE   // e.g., "/task/assets"
```

## Files & Structure

### Backend Files

1. **`config/paths.php`** - Path detection class
   - `PathConfig::getInstallationPath()` - Get current installation path
   - `PathConfig::getApiBase()` - Get API URL base
   - `PathConfig::url($path)` - Build full URLs
   - Auto-saves detected path to database

2. **`api/config/paths.php`** - API endpoint
   - Returns path configuration as JSON
   - No authentication required (public config)
   - Used by frontend to load paths

3. **`database/system_settings.sql`** - Database schema
   - Added `installation_path` setting
   - Type: `string`, Category: `system`
   - Auto-populated on first request

### Frontend Files

1. **`dashboard.html`** & **`dashboard-production.html`**
   - Unified into single file (no more separate versions)
   - Auto-loads path config from API
   - Falls back to JavaScript detection if API unavailable
   - Sets global variables: `API_URL`, `BASE_PATH`, `ASSETS_BASE`, `PAGES_BASE`

2. **`pages/settings.html`** (Admin Only)
   - System tab shows current installation path
   - "Auto-Detect" button to re-detect path
   - Manual override field for custom paths
   - Reloads app after path change

## Usage

### For Development (localhost)
No configuration needed! Just open:
```
http://localhost/TaskMesh/dashboard.html
```
System detects path as `/TaskMesh` automatically.

### For Production (subdirectory)
Upload files to `/task/` on server:
```
http://domain.com/task/dashboard.html
```
System detects path as `/task` automatically.

### For Production (root)
Upload files to document root:
```
http://domain.com/dashboard.html
```
System detects path as `` (empty) automatically.

## Manual Override

If auto-detection fails, Admin can manually set the path:

1. Login as Admin
2. Go to **Settings** → **System** tab
3. Find "Installation Path" section
4. Enter path (e.g., `/task`) or leave empty for root
5. Click **Save Settings**
6. App reloads with new path

## Migration Guide

### From Old System (dashboard-production.html)
You can now delete `dashboard-production.html` and use only `dashboard.html`:

**Before:**
```
- dashboard.html          (for localhost/TaskMesh)
- dashboard-production.html (for domain.com/task)
```

**After:**
```
- dashboard.html          (works for both!)
```

### Database Update
Run this SQL to add the new setting:

```sql
INSERT INTO system_settings 
(setting_key, setting_value, setting_type, category, description) 
VALUES 
('installation_path', '', 'string', 'system', 
 'Application installation path (auto-detected, e.g., /task or empty for root)');
```

## API Reference

### Get Path Configuration
```http
GET /api/config/paths.php
```

**Response:**
```json
{
  "success": true,
  "config": {
    "basePath": "/task",
    "apiBase": "/task/api",
    "assetsBase": "/task/assets",
    "pagesBase": "/task/pages",
    "fullUrl": "http://domain.com/task",
    "apiUrl": "http://domain.com/task/api"
  }
}
```

### Update Installation Path (Admin Only)
```http
POST /api/settings/system.php
Authorization: Bearer {token}
Content-Type: application/json

{
  "settings": {
    "installation_path": "/task"
  }
}
```

## Helper Functions

### PHP
```php
require_once 'config/paths.php';

// Get installation path
$base = PathConfig::getBasePath(); // "/task" or ""

// Build URLs
$apiUrl = PathConfig::getApiBase();        // "/task/api"
$pageUrl = PathConfig::url('dashboard.html'); // "/task/dashboard.html"

// Helper functions
$url = app_path('assets/logo.png');  // "/task/assets/logo.png"
$api = api_url('tasks/index.php');   // "/task/api/tasks/index.php"
```

### JavaScript
```javascript
// Global variables (set by dashboard.html)
console.log(API_URL);       // "http://domain.com/task/api"
console.log(BASE_PATH);     // "/task"
console.log(PAGES_BASE);    // "/task/pages"
console.log(ASSETS_BASE);   // "/task/assets"

// Fetch from API
const response = await fetch(`${API_URL}/tasks/index.php`);

// Load page
const pageUrl = `${PAGES_BASE}/home.html`;
```

## Troubleshooting

### Issue: Wrong path detected
**Solution:** Go to Settings → System → Auto-Detect button

### Issue: API calls fail with 404
**Solution:** Check browser console for detected paths. Verify `installation_path` in database.

### Issue: Pages don't load
**Solution:** Check `PAGES_BASE` variable in console. Ensure files are in correct directory.

### Issue: Need to change path after deployment
**Solution:** Admin Settings → System → Installation Path → Enter new path → Save

## Benefits

✅ **No More Dual Files** - One `dashboard.html` works everywhere
✅ **Auto-Detection** - Zero configuration needed
✅ **Flexible Deployment** - Works in root or subdirectory
✅ **Admin Control** - Manual override available if needed
✅ **Database Driven** - Centralized configuration
✅ **Future-Proof** - Easy to change paths without code edits

## Technical Details

### Detection Algorithm
1. Parse current URL path: `window.location.pathname`
2. Split into parts: `/task/dashboard.html` → `["task", "dashboard.html"]`
3. Remove known files/directories: `["dashboard.html", "pages", "api", "assets"]`
4. Remaining parts form base path: `["task"]` → `/task`
5. Save to database for future requests

### Caching Strategy
- Detected on first dashboard load
- Saved to `system_settings` table
- Loaded from database on subsequent requests
- Can be manually refreshed via Settings

### Backward Compatibility
Old hardcoded URLs still work but should be updated to use path configuration for consistency.

---

**Created:** December 3, 2025  
**Version:** 1.0.0  
**Author:** Theodore Sfakianakis
