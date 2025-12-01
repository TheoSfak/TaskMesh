# 📧 Email Notifications - Implementation Summary

## Υλοποιημένα Email Notifications

### 1️⃣ Task Assignment (Ανάθεση Task)
**Trigger:** Όταν δημιουργείται task με assignee ή αλλάζει assignee  
**Recipient:** Το member που ανατίθεται το task  
**Location:** `api/tasks/index.php` (POST), `api/tasks/single.php` (PUT)  
**Template:** Blue header, task title, assigned by name, link to tasks

### 2️⃣ Task Completion (Ολοκλήρωση Task)
**Trigger:** Όταν task μεταβαίνει σε status COMPLETED  
**Recipient:** Ο creator του task (manager)  
**Location:** `api/tasks/single.php` (PUT)  
**Template:** Green header, task title, completed by name, link to tasks

### 3️⃣ Subtask Completion (Ολοκλήρωση Subtask)
**Trigger:** Όταν subtask μεταβαίνει σε status COMPLETED  
**Recipient:** Ο creator του parent task (manager)  
**Location:** `api/subtasks/index.php` (PUT)  
**Template:** Green header, subtask + task title, completed by name, link to tasks

### 4️⃣ New Comment (Νέο Σχόλιο)
**Trigger:** Όταν προστίθεται σχόλιο σε task  
**Recipients:** Task creator ΚΑΙ assignee (εκτός από τον author του comment)  
**Location:** `api/comments/index.php` (POST)  
**Template:** Purple header, task title, comment preview, author name, link to tasks

### 5️⃣ Team Member Added (Προσθήκη σε Ομάδα)
**Trigger:** Όταν manager προσθέτει member σε team  
**Recipient:** Το νέο member  
**Location:** `api/teams/members.php` (POST)  
**Template:** Violet header, team name, added by name, link to teams

### 6️⃣ Direct Message (Προσωπικό Μήνυμα)
**Trigger:** Όταν στέλνεται DM σε user  
**Recipient:** Ο παραλήπτης του μηνύματος  
**Location:** `api/messages/direct.php` (POST)  
**Template:** Pink header, sender name, message preview, link to messages

### 7️⃣ Deadline Reminder (Υπενθύμιση Deadline)
**Trigger:** Cron job - 24 ώρες πριν το deadline  
**Recipient:** Ο assignee του task  
**Location:** `cron/deadline_reminders.php`  
**Template:** Orange header, task title, deadline date/time, warning styling

---

## Αρχεία που Δημιουργήθηκαν

```
lib/
  └── PHPMailer.php              # Email service με όλα τα templates

config/
  └── email.php                  # SMTP configuration

cron/
  └── deadline_reminders.php     # Daily cron job για reminders

EMAIL_NOTIFICATIONS.md           # Complete setup guide
test_email_config.php            # Test script για configuration
```

---

## Αρχεία που Τροποποιήθηκαν

### APIs με Email Integration:
- ✅ `api/tasks/index.php` - Task creation με assignee
- ✅ `api/tasks/single.php` - Task update (assignee change, completion)
- ✅ `api/subtasks/index.php` - Subtask completion
- ✅ `api/comments/index.php` - New comment
- ✅ `api/teams/members.php` - Member addition
- ✅ `api/messages/direct.php` - DM sending

Όλα τα παραπάνω APIs:
1. Κάνουν `require_once` το `lib/PHPMailer.php`
2. Στέλνουν email μετά την επιτυχή database operation
3. Δεν σταματούν την εκτέλεση αν το email αποτύχει
4. Ελέγχουν για duplicates (π.χ. δεν στέλνουν στον author)

---

## Configuration Required

Για να λειτουργήσουν τα emails, **πρέπει** να ρυθμίσεις το `config/email.php`:

```php
// Αλλαγές που ΠΡΕΠΕΙ να γίνουν:
define('SMTP_USERNAME', 'your-actual-email@gmail.com');  
define('SMTP_PASSWORD', 'your-app-password-here');       
define('SMTP_FROM_EMAIL', 'your-actual-email@gmail.com');
define('APP_BASE_URL', 'http://your-domain.com');        // For production
```

**Για Gmail:**
- Enable 2-Factor Authentication
- Create App Password: https://myaccount.google.com/apppasswords
- Use App Password (NOT your regular password)

---

## Testing

### 1. Test Email Configuration
```bash
php test_email_config.php
```
Θα σου ζητήσει email address και θα στείλει test email.

### 2. Test via Application
- **Task Assignment:** Δημιούργησε task και άναθεσέ το σε member
- **Task Completion:** Άλλαξε status task σε COMPLETED
- **Subtask Completion:** Κάνε toggle checkbox σε subtask
- **Comment:** Γράψε σχόλιο σε task
- **Team Invite:** Πρόσθεσε member σε team
- **DM:** Στείλε direct message
- **Deadline:** Δημιούργησε task με deadline αύριο, τρέξε cron manually

### 3. Development Mode
Για να δεις αν καλούνται οι functions χωρίς να στέλνεις emails:

```php
// Στο config/email.php πρόσθεσε:
define('EMAIL_LOG_MODE', true);
```

Τα emails θα καταγράφονται στο PHP error log.

---

## Email Design

Κάθε email έχει:

### Structure
- **HTML template** με inline CSS (for email client compatibility)
- **Responsive design** (max-width 600px)
- **Professional styling** με Tailwind-inspired colors
- **Direct action link** στο TaskMesh dashboard
- **Footer** με app branding

### Color Coding
- 🔵 **Blue (#4F46E5)** - New Task Assignment
- 🟢 **Green (#10B981)** - Task/Subtask Completed
- 🟣 **Purple (#6366F1)** - New Comment
- 🟣 **Violet (#8B5CF6)** - Team Invitation
- 🔴 **Pink (#EC4899)** - Direct Message
- 🟠 **Orange (#F59E0B)** - Deadline Warning

### Personalization
- Recipient name in greeting
- Action performer name
- Relevant context (task title, team name, etc.)
- Timestamps for deadlines
- Preview of content (comments, messages)

---

## Cron Job Setup (Deadline Reminders)

### Windows Task Scheduler
```
Name: TaskMesh Deadline Reminders
Trigger: Daily at 9:00 AM
Action: Start Program
  Program: C:\xampp\php\php.exe
  Arguments: C:\xampp\htdocs\TaskMesh\cron\deadline_reminders.php
```

### Linux/Unix Crontab
```bash
# Run daily at 9:00 AM
0 9 * * * /usr/bin/php /var/www/html/TaskMesh/cron/deadline_reminders.php >> /var/log/taskmesh_cron.log 2>&1
```

### Manual Testing
```bash
php cron/deadline_reminders.php
```

Το script:
- Βρίσκει tasks με deadline 0-24 ώρες μπροστά
- Εξαιρεί completed tasks
- Στέλνει email σε κάθε assignee
- Εμφανίζει summary (sent/failed)

---

## Disable Notifications

### Globally
```php
// config/email.php
define('EMAIL_NOTIFICATIONS_ENABLED', false);
```

### Specific Types
Σχολίασε την κλήση `EmailService::send...()` στο αντίστοιχο API file.

---

## Production Considerations

1. **SMTP Service:** Χρησιμοποίησε professional SMTP (SendGrid, Mailgun, AWS SES)
2. **Rate Limits:** Πρόσθεσε throttling για bulk operations
3. **Queue System:** Για μεγάλο όγκο, χρησιμοποίησε queue (Redis, RabbitMQ)
4. **Monitoring:** Log email failures, track delivery rates
5. **Unsubscribe:** Προσθήκη user preferences για notification types
6. **Templates:** Χρησιμοποίησε template engine (Twig, Blade) αντί inline HTML
7. **Testing:** Use MailHog or Mailtrap για development testing

---

## Future Enhancements

### Priority 1 (Near Future)
- [ ] User notification preferences (enable/disable per type)
- [ ] In-app notifications (database table + UI)
- [ ] Digest mode (batch multiple notifications)

### Priority 2 (Long Term)
- [ ] Weekly summary emails (pending tasks report)
- [ ] Overdue task notifications
- [ ] @mentions in comments with notifications
- [ ] Slack/Discord webhook integration
- [ ] Email templates editor in admin panel
- [ ] Notification history log

### Priority 3 (Advanced)
- [ ] Push notifications (PWA)
- [ ] SMS notifications for critical events
- [ ] Notification analytics dashboard
- [ ] A/B testing for email templates

---

## Troubleshooting

### Emails not received?
1. ✅ Check `EMAIL_NOTIFICATIONS_ENABLED = true`
2. ✅ Verify SMTP credentials correct
3. ✅ Check spam folder
4. ✅ Enable `EMAIL_LOG_MODE` to see function calls
5. ✅ Run `test_email_config.php`
6. ✅ Check PHP error logs
7. ✅ Verify firewall allows SMTP port (587/465)

### Gmail specific issues?
1. ✅ 2FA must be enabled
2. ✅ Use App Password, not regular password
3. ✅ "Less secure app access" not needed with App Password
4. ✅ Check Google account security page for blocks

### Cron job not running?
1. ✅ Verify cron is scheduled correctly
2. ✅ Check PHP path is correct
3. ✅ Test manual run first
4. ✅ Check cron execution logs
5. ✅ Ensure database connection works from CLI

---

**Status:** ✅ Fully Implemented  
**Last Updated:** November 27, 2025  
**Version:** 1.0.0
