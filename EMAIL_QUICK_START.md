# 📧 Email Notifications - Quick Setup

## Γρήγορη Ρύθμιση (5 λεπτά)

### 1️⃣ Configure Email Settings

```bash
# Copy example config
cp config/email.example.php config/email.php
```

Edit `config/email.php`:

```php
// For Gmail:
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');      // YOUR EMAIL
define('SMTP_PASSWORD', 'xxxx xxxx xxxx xxxx');       // APP PASSWORD (see below)
define('SMTP_FROM_EMAIL', 'your-email@gmail.com');    // YOUR EMAIL
define('SMTP_ENCRYPTION', 'tls');

define('EMAIL_NOTIFICATIONS_ENABLED', true);
define('APP_BASE_URL', 'http://localhost/TaskMesh');
```

### 2️⃣ Get Gmail App Password

1. Go to: https://myaccount.google.com/security
2. Enable **2-Step Verification** (if not already)
3. Go to: https://myaccount.google.com/apppasswords
4. Create App Password for "Mail"
5. Copy the 16-character password → paste to `SMTP_PASSWORD`

### 3️⃣ Test Configuration

```bash
php test_email_config.php
```

Enter your test email when prompted. Check inbox (and spam folder).

---

## 7 Email Types Που Στέλνονται Αυτόματα

| # | Event | Trigger | Recipient |
|---|-------|---------|-----------|
| 1 | 📋 Task Assigned | Manager αναθέτει task | Assignee |
| 2 | ✅ Task Completed | Status → COMPLETED | Task creator |
| 3 | ✅ Subtask Completed | Subtask → COMPLETED | Task creator |
| 4 | 💬 New Comment | Σχόλιο σε task | Creator + Assignee |
| 5 | 👥 Added to Team | Manager adds member | New member |
| 6 | ✉️ Direct Message | User sends DM | Receiver |
| 7 | ⏰ Deadline Soon | 24h πριν deadline | Assignee |

---

## Development Mode (Skip Email Sending)

Για testing χωρίς να στέλνεις emails:

```php
// config/email.php - Add this line:
define('EMAIL_LOG_MODE', true);
```

Emails will be logged instead of sent.

---

## Deadline Reminders (Cron Job)

### Windows Task Scheduler

1. Open **Task Scheduler**
2. Create Basic Task:
   - **Name:** TaskMesh Deadline Reminders
   - **Trigger:** Daily at 9:00 AM
   - **Action:** Start a program
     - **Program:** `C:\xampp\php\php.exe`
     - **Arguments:** `C:\xampp\htdocs\TaskMesh\cron\deadline_reminders.php`

### Test Manually

```bash
php cron/deadline_reminders.php
```

---

## Troubleshooting

### ✗ "Could not authenticate"
- ❌ Using regular password instead of App Password
- ✅ Get App Password from https://myaccount.google.com/apppasswords

### ✗ Emails not received
- Check spam folder
- Verify `EMAIL_NOTIFICATIONS_ENABLED = true`
- Run test script: `php test_email_config.php`

### ✗ Cron not running
- Test manually first: `php cron/deadline_reminders.php`
- Check Task Scheduler has correct PHP path
- Verify database connection works from CLI

---

## Disable Notifications

```php
// config/email.php
define('EMAIL_NOTIFICATIONS_ENABLED', false);  // Disable all emails
```

---

## Full Documentation

- **EMAIL_NOTIFICATIONS.md** - Complete setup guide
- **EMAIL_IMPLEMENTATION.md** - Technical details
- **test_email_config.php** - Test script

---

## Production Checklist

- [ ] Update `APP_BASE_URL` to production domain
- [ ] Use professional SMTP service (SendGrid, Mailgun, SES)
- [ ] Remove `EMAIL_LOG_MODE` or set to false
- [ ] Test all 7 notification types
- [ ] Set up cron job on server
- [ ] Monitor email delivery rates
- [ ] Add to .gitignore: `config/email.php`

---

**Need Help?** See `EMAIL_NOTIFICATIONS.md` for detailed documentation.
