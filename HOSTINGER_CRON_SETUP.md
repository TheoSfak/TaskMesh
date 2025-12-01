# 📧 Email Notifications - Database Configuration Complete!

## ✅ Ολοκληρώθηκε

Το σύστημα email notifications ενημερώθηκε για να διαβάζει τις ρυθμίσεις από **database** αντί για config files!

---

## 🎯 Τι Άλλαξε

### 1️⃣ Database Table
Νέος πίνακας `email_settings` με όλες τις SMTP ρυθμίσεις:
- ✅ SMTP Host, Port, Encryption
- ✅ Username & Password (encrypted)
- ✅ From Email & Name
- ✅ Notifications Enable/Disable toggle
- ✅ App Base URL
- ✅ Audit trail (updated_by, updated_at)

### 2️⃣ Admin UI (Settings → Email Settings)
Πλήρες interface για ρύθμιση emails:
- ✅ **Professional form** με validation
- ✅ **Gmail setup instructions** inline
- ✅ **Toggle** για enable/disable notifications
- ✅ **Test Email** με custom recipient
- ✅ **Password protection** (μόνο admins)
- ✅ **Real-time save** στη βάση

### 3️⃣ APIs
- ✅ `GET /api/settings/email.php` - Φέρνει settings (χωρίς password)
- ✅ `PUT /api/settings/email.php` - Αποθηκεύει settings
- ✅ `POST /api/settings/test-email.php` - Στέλνει test email

### 4️⃣ Email Service
Το `lib/PHPMailer.php` τώρα:
- ✅ Διαβάζει settings από database
- ✅ Ελέγχει αν notifications enabled
- ✅ Χρησιμοποιεί dynamic base URL
- ✅ Validation πριν στείλει

---

## 🚀 Πώς να Χρησιμοποιήσεις

### Βήμα 1: Σύνδεση ως Admin
```
Email: admin@taskmesh.com
Password: admin123
```

### Βήμα 2: Πήγαινε στο Settings
1. Click **Settings** στο sidebar
2. Click **Email Settings** tab

### Βήμα 3: Configure SMTP (Gmail Example)
```
SMTP Host: smtp.gmail.com
SMTP Port: 587
Encryption: TLS
SMTP Username: your-email@gmail.com
SMTP Password: xxxx xxxx xxxx xxxx (App Password)
From Email: your-email@gmail.com
From Name: TaskMesh Notifications
App Base URL: http://localhost/TaskMesh
```

### Βήμα 4: Get Gmail App Password
1. Go to: https://myaccount.google.com/security
2. Enable **2-Step Verification**
3. Go to: https://myaccount.google.com/apppasswords
4. Create App Password for "Mail"
5. Copy 16-character password → paste above

### Βήμα 5: Save & Test
1. Click **Save Settings**
2. Enter your test email in the box
3. Click **Send Test**
4. Check inbox (and spam folder)

### Βήμα 6: Enable Notifications
Toggle the switch at the top to **Enabled** ✅

---

## ⏰ Hostinger Cron Job (Hourly)

### Για Hostinger Shared Hosting:

**Command που πρέπει να βάλεις στο cPanel:**

```bash
0 * * * * /usr/bin/php /home/YOUR_USERNAME/public_html/TaskMesh/cron/deadline_reminders.php >> /home/YOUR_USERNAME/public_html/TaskMesh/cron/cron.log 2>&1
```

**Αντικατέστησε:**
- `YOUR_USERNAME` → Το username του Hostinger account σου
- `/public_html/TaskMesh` → Το path όπου έχεις ανεβάσει το TaskMesh

**Τι σημαίνει:**
- `0 * * * *` = Κάθε ώρα στο λεπτό 0 (00:00, 01:00, 02:00, κτλ)
- `/usr/bin/php` = PHP executable path (standard για Hostinger)
- `>> /path/to/cron.log` = Log output για debugging
- `2>&1` = Redirect errors στο log

### Εναλλακτικές Συχνότητες:

**Κάθε 30 λεπτά:**
```bash
0,30 * * * * /usr/bin/php /home/YOUR_USERNAME/public_html/TaskMesh/cron/deadline_reminders.php >> /home/YOUR_USERNAME/public_html/TaskMesh/cron/cron.log 2>&1
```

**Κάθε 6 ώρες:**
```bash
0 */6 * * * /usr/bin/php /home/YOUR_USERNAME/public_html/TaskMesh/cron/deadline_reminders.php >> /home/YOUR_USERNAME/public_html/TaskMesh/cron/cron.log 2>&1
```

**Μία φορά την ημέρα (9 πμ):**
```bash
0 9 * * * /usr/bin/php /home/YOUR_USERNAME/public_html/TaskMesh/cron/deadline_reminders.php >> /home/YOUR_USERNAME/public_html/TaskMesh/cron/cron.log 2>&1
```

**Δύο φορές την ημέρα (9πμ, 5μμ):**
```bash
0 9,17 * * * /usr/bin/php /home/YOUR_USERNAME/public_html/TaskMesh/cron/deadline_reminders.php >> /home/YOUR_USERNAME/public_html/TaskMesh/cron/cron.log 2>&1
```

### Πώς να το ρυθμίσεις στο Hostinger:

1. **Login** στο Hostinger hPanel
2. **Advanced** → **Cron Jobs**
3. **Create New Cron Job**
4. **Common Settings**: Select "Custom"
5. **Minute**: `0`
6. **Hour**: `*` (every hour)
7. **Day**: `*`
8. **Month**: `*`
9. **Weekday**: `*`
10. **Command**: Paste the command above (with your username)
11. Click **Create**

### Verify Cron is Running:

Check log file:
```bash
tail -f /home/YOUR_USERNAME/public_html/TaskMesh/cron/cron.log
```

Ή από hPanel:
**File Manager** → Navigate to `TaskMesh/cron/cron.log` → View

---

## 🧪 Testing

### Test Email Configuration:
1. Go to Settings → Email Settings
2. Enter your email in "Test Email" box
3. Click "Send Test"
4. Check inbox

### Test Deadline Reminders (Manual):
```bash
php /home/YOUR_USERNAME/public_html/TaskMesh/cron/deadline_reminders.php
```

---

## 🔒 Security Notes

1. **Password is encrypted** in database
2. **Never shown** in API responses
3. **Only admins** can edit email settings
4. **Audit trail** tracks who changed what
5. **Production**: Use environment variables for extra security

---

## 📊 Email Notification Types

All 7 types now use database settings:

1. ✅ **Task Assignment** - Member gets email when assigned
2. ✅ **Task Completion** - Manager gets email when completed
3. ✅ **Subtask Completion** - Manager gets email
4. ✅ **New Comment** - Creator & Assignee get email
5. ✅ **Team Invitation** - New member gets email
6. ✅ **Direct Message** - Receiver gets email
7. ✅ **Deadline Reminder** - Assignee gets email (via cron)

---

## 🎉 Benefits

### Before (config file):
- ❌ Χειροκίνητη επεξεργασία PHP file
- ❌ Χρειάζεται FTP access
- ❌ Κίνδυνος Git commits με passwords
- ❌ Δεν μπορεί να αλλάξει μη-developer

### After (database):
- ✅ UI-based configuration
- ✅ No FTP needed
- ✅ Git-safe
- ✅ Admin μπορεί να διαχειριστεί
- ✅ Test email με 1 click
- ✅ Enable/Disable toggle
- ✅ Audit trail
- ✅ Production-ready

---

## 📁 Files Created/Modified

### New Files:
- `database/email_settings.sql` - Database schema
- `api/settings/email.php` - Email settings API
- `api/settings/test-email.php` - Test email API
- `HOSTINGER_CRON_SETUP.md` - This file

### Modified Files:
- `lib/PHPMailer.php` - Now reads from database
- `pages/settings.html` - Email Settings UI tab

---

## 🚀 Next Steps

1. ✅ Configure email settings in admin panel
2. ✅ Test email delivery
3. ✅ Enable notifications toggle
4. ✅ Set up Hostinger cron job (hourly)
5. ✅ Monitor email delivery (check logs)
6. ✅ Test all 7 notification types in production

---

**Email notifications system is now fully database-driven and production-ready!** 🎉

Last Updated: November 27, 2025
