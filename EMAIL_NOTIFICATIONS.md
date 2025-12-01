# 📧 Email Notifications Setup Guide

## Overview

TaskMesh στέλνει αυτόματα email notifications για τα παρακάτω events:

### ✅ Implemented Notifications

1. **Task Assignment** - Όταν ένας manager αναθέτει task σε member
2. **Task Completion** - Όταν ολοκληρώνεται task, ειδοποιείται ο creator (manager)
3. **Subtask Created** - Όταν προστίθεται νέο subtask, ειδοποιούνται όλοι οι assignees του task
4. **Subtask Completion** - Όταν ολοκληρώνεται subtask, ειδοποιείται ο manager
5. **New Comment** - Όταν προστίθεται σχόλιο σε task, ειδοποιούνται creator και assignee
6. **Team Member Added** - Όταν προστίθεται μέλος σε ομάδα
7. **Direct Message** - Όταν λαμβάνεις νέο DM
8. **Deadline Reminder** - 24 ώρες πριν το deadline (μέσω cron job)

---

## 🔧 Configuration

### Step 1: Email Settings

Επεξεργάσου το `config/email.php`:

```php
// SMTP Configuration
define('SMTP_HOST', 'smtp.gmail.com');          // Your SMTP server
define('SMTP_PORT', 587);                        // 587 for TLS, 465 for SSL
define('SMTP_USERNAME', 'your-email@gmail.com'); // Your email
define('SMTP_PASSWORD', 'your-app-password');    // App password (not regular password!)
define('SMTP_FROM_EMAIL', 'your-email@gmail.com');
define('SMTP_FROM_NAME', 'TaskMesh Notifications');
define('SMTP_ENCRYPTION', 'tls');                // 'tls' or 'ssl'

// Enable/Disable notifications
define('EMAIL_NOTIFICATIONS_ENABLED', true);     // Set to false to disable all emails

// Base URL for links in emails
define('APP_BASE_URL', 'http://localhost/TaskMesh');  // Change for production
```

### Step 2: Gmail App Password (if using Gmail)

Για Gmail, χρειάζεσαι **App Password** (όχι το κανονικό σου password):

1. Πήγαινε στο [Google Account Security](https://myaccount.google.com/security)
2. Enable **2-Step Verification**
3. Πήγαινε στα **App Passwords**
4. Δημιούργησε νέο App Password για "Mail"
5. Χρησιμοποίησε αυτό το password στο `SMTP_PASSWORD`

### Step 3: Development Mode (Optional)

Για development, μπορείς να ενεργοποιήσεις log mode αντί να στέλνεις πραγματικά emails.

Στο `config/email.php`, πρόσθεσε:

```php
define('EMAIL_LOG_MODE', true);  // Logs emails instead of sending
```

Τα emails θα καταγράφονται στο PHP error log αντί να στέλνονται.

---

## ⏰ Deadline Reminders (Cron Job)

Για να στέλνονται αυτόματα reminders για tasks με πλησίον deadline:

### Windows Task Scheduler

1. Άνοιξε **Task Scheduler**
2. Create Basic Task:
   - Name: `TaskMesh Deadline Reminders`
   - Trigger: **Daily at 9:00 AM**
   - Action: **Start a program**
   - Program: `C:\xampp\php\php.exe`
   - Arguments: `C:\xampp\htdocs\TaskMesh\cron\deadline_reminders.php`

### Manual Testing

```bash
php C:\xampp\htdocs\TaskMesh\cron\deadline_reminders.php
```

---

## 🧪 Testing Email Configuration

Δημιούργησε test script `test_email.php`:

```php
<?php
require_once 'config/email.php';
require_once 'lib/PHPMailer.php';

// Test email
$result = EmailService::send(
    'your-test-email@example.com',
    'TaskMesh Email Test',
    '<h1>Test Email</h1><p>If you see this, email configuration works!</p>'
);

if ($result) {
    echo "✓ Email sent successfully!";
} else {
    echo "✗ Failed to send email. Check your configuration.";
}
?>
```

Run: `php test_email.php`

---

## 📋 Email Templates

Όλα τα email templates είναι HTML και περιλαμβάνουν:

- **Professional styling** με Tailwind-inspired colors
- **Responsive design** για mobile
- **Direct links** στο TaskMesh dashboard
- **Greek language** support
- **Color-coded headers** ανά notification type:
  - 🔵 Blue (#4F46E5) - New Task
  - 🟢 Green (#10B981) - Completed
  - 🟣 Purple (#6366F1) - New Comment
  - 🟣 Violet (#8B5CF6) - Team Invitation
  - 🔴 Pink (#EC4899) - Direct Message
  - 🟠 Orange (#F59E0B) - Deadline Warning

---

## 🎨 Customizing Email Templates

Edit `lib/PHPMailer.php` functions:

- `sendTaskAssigned()` - Task assignment email
- `sendTaskCompleted()` - Task completion email
- `sendSubtaskCompleted()` - Subtask completion email
- `sendCommentAdded()` - New comment email
- `sendAddedToTeam()` - Team invitation email
- `sendDirectMessage()` - DM notification email
- `sendDeadlineReminder()` - Deadline reminder email

Κάθε function έχει inline HTML styling για full customization.

---

## 🔒 Security Best Practices

1. **Never commit** `config/email.php` with real credentials to git
2. Use **App Passwords**, not regular passwords
3. Enable **TLS/SSL encryption** (port 587 or 465)
4. Set `EMAIL_NOTIFICATIONS_ENABLED = false` in development if needed
5. Validate all user input before including in emails

---

## 🐛 Troubleshooting

### Emails not sending?

1. Check `EMAIL_NOTIFICATIONS_ENABLED = true` in `config/email.php`
2. Verify SMTP credentials are correct
3. Check PHP error logs: `C:\xampp\php\logs\php_error_log`
4. Enable `EMAIL_LOG_MODE` to see if functions are called
5. Test with `test_email.php` script
6. Check firewall settings (ports 587/465)
7. Gmail: Ensure 2FA and App Password are set up

### Common Errors

**"Could not authenticate"**
- Wrong SMTP username/password
- Need App Password for Gmail
- 2FA not enabled (Gmail requirement)

**"Connection refused"**
- Wrong SMTP host or port
- Firewall blocking connection
- SMTP server not accessible

**"SSL certificate problem"**
- In development, may need to disable SSL verification (not recommended for production)

---

## 📊 Monitoring

Check cron job logs:
```bash
cat C:\xampp\htdocs\TaskMesh\cron\deadline_reminders.log
```

Check which emails were sent by viewing PHP mail logs or using EMAIL_LOG_MODE.

---

## 🚀 Production Deployment

For production:

1. Update `APP_BASE_URL` to your domain
2. Use a reliable SMTP service (Gmail, SendGrid, Mailgun, etc.)
3. Set `EMAIL_LOG_MODE = false`
4. Test all notification types
5. Set up cron job on server (not Windows Task Scheduler)
6. Monitor email delivery rates
7. Add unsubscribe functionality (optional)

---

## 📝 Future Enhancements

Possible additions:

- Weekly summary emails (pending tasks)
- Overdue task notifications
- User preferences (disable specific notifications)
- Digest mode (batch multiple notifications)
- Slack/Discord integration as alternative to email
- In-app notifications (database-based)

---

**Author:** TaskMesh Team  
**Last Updated:** November 2025
