# Installation Guide - CRM Updates

## Quick Start

This guide will help you deploy the new CRM updates to your production server.

---

## Prerequisites

- MySQL 5.7 or higher
- PHP 7.4 or higher with extensions:
  - PDO MySQL
  - GD or Imagick
  - cURL
  - mail() configured or SMTP access
- Web server (Apache/Nginx)
- Write permissions on upload directories

---

## Step-by-Step Installation

### Step 1: Backup Current System

**IMPORTANT: Always backup before making changes!**

```bash
# Backup database
mysqldump -u YOUR_USERNAME -p YOUR_DATABASE_NAME > backup_$(date +%Y%m%d).sql

# Backup files
tar -czf backup_files_$(date +%Y%m%d).tar.gz /path/to/your/crm
```

### Step 2: Upload New Files

Upload these files to your server:

**Modified Files:**
- `usuarios.php`
- `empresas.php`
- `configuracion.php`
- `buscar.php`
- `evento_publico.php`

**New Files:**
- `boleto_digital.php`
- `terminos.php`
- `privacidad.php`
- `app/helpers/qrcode.php`
- `app/helpers/email.php`
- `database/migration_eventos_enhancements.sql`
- `IMPLEMENTATION_SUMMARY.md`

### Step 3: Set File Permissions

```bash
# Navigate to your CRM directory
cd /path/to/your/crm

# Set directory permissions
chmod 755 public/uploads
mkdir -p public/uploads/qrcodes
chmod 755 public/uploads/qrcodes

# Set file permissions
find . -type f -name "*.php" -exec chmod 644 {} \;
```

### Step 4: Run Database Migration

```bash
# Connect to MySQL
mysql -u YOUR_USERNAME -p YOUR_DATABASE_NAME

# In MySQL prompt, run:
source database/migration_eventos_enhancements.sql;

# Verify changes
DESCRIBE eventos_inscripciones;

# Should show new columns:
# - razon_social_invitado
# - codigo_qr
# - boleto_enviado
# - fecha_envio_boleto

# Exit MySQL
exit;
```

**Or via phpMyAdmin:**
1. Log in to phpMyAdmin
2. Select your database
3. Click on "Import" tab
4. Choose `migration_eventos_enhancements.sql`
5. Click "Go"

### Step 5: Configure System Settings

1. **Log in to Admin Panel**
   - Go to: `https://yourdomain.com/YOUR_CRM_PATH/login.php`
   - Use admin credentials

2. **Go to Configuration**
   - Click on "Configuración" in menu

3. **Set Maximum Tickets**
   - Find "Máximo de Boletos por Registro"
   - Set value (recommended: 10)
   - Click "Guardar"

4. **Configure Email (SMTP)**
   - Scroll to "Configuración de Correo SMTP"
   - Enter your SMTP details:
     - Host: smtp.your-provider.com
     - Port: 587 (for TLS) or 465 (for SSL)
     - Username: your-email@domain.com
     - Password: your-password
     - Secure: TLS or SSL
     - From Name: CRM Cámara de Comercio
   - Save settings

5. **Add Terms and Conditions** (Optional)
   - In configuration, find "Términos y Condiciones"
   - Add your organization's terms
   - Save

6. **Add Privacy Policy** (Optional)
   - In configuration, find "Política de Privacidad"
   - Add your privacy policy
   - Save

### Step 6: Test the System

Run through this checklist:

#### Test 1: Fix Verification
- [ ] Go to `usuarios.php`
- [ ] No PHP warnings should appear
- [ ] Dates display correctly (not 31/12/1969)

#### Test 2: Suspended Companies
- [ ] Go to "Gestión de Empresas"
- [ ] Click "Ver Suspendidas"
- [ ] List should display (even if empty)
- [ ] Click "Volver a Activas" - should return to main list

#### Test 3: Event Registration
- [ ] Find an active event or create one
- [ ] Open public registration: `evento_publico.php?evento=EVENT_ID`
- [ ] Try the search feature (enter WhatsApp or RFC)
- [ ] Fill out the registration form:
  - Enter company name
  - Enter full name
  - Enter email
  - Enter WhatsApp (10 digits only)
  - Check/uncheck "Es Invitado" to test RFC requirement
  - Enter number of tickets
  - Solve captcha
  - Accept terms
  - Submit
- [ ] Verify success message appears
- [ ] Check email was received
- [ ] Click link to print ticket
- [ ] Verify ticket displays with QR code

#### Test 4: Digital Ticket
- [ ] Open link from email or use format: `boleto_digital.php?codigo=YOUR_QR_CODE`
- [ ] Verify all information displays correctly
- [ ] Click "Imprimir Boleto"
- [ ] Verify print preview looks good

#### Test 5: Search Functionality
- [ ] Go to search page (`buscar.php`)
- [ ] Search for WhatsApp number used in registration
- [ ] Should find both company (if exists) AND event inscription
- [ ] Click on inscription result
- [ ] Should open digital ticket

---

## Troubleshooting

### Problem: Email Not Sending

**Symptoms:** Registration succeeds but no email received

**Solutions:**
1. Check SMTP configuration in Configuración
2. Test with simple PHP mail:
   ```php
   <?php
   mail('your-email@test.com', 'Test', 'Test message');
   ?>
   ```
3. Check server mail logs: `/var/log/mail.log`
4. Verify firewall allows outbound SMTP
5. Check spam folder

### Problem: QR Code Not Generating

**Symptoms:** Registration works but no QR code image

**Solutions:**
1. Check permissions:
   ```bash
   ls -la public/uploads/qrcodes/
   chmod 755 public/uploads/qrcodes/
   ```
2. Verify cURL is enabled:
   ```bash
   php -m | grep curl
   ```
3. Check Google Charts API is accessible:
   ```bash
   curl "https://chart.googleapis.com/chart?cht=qr&chs=300x300&chl=test"
   ```
4. Check PHP error logs

### Problem: WhatsApp Validation Not Working

**Symptoms:** Can submit non-10-digit numbers

**Solutions:**
1. Clear browser cache
2. Verify JavaScript is enabled
3. Check console for errors (F12 in browser)
4. Server-side validation should still catch it

### Problem: Search Not Finding Inscriptions

**Symptoms:** Search only shows companies, not event registrations

**Solutions:**
1. Verify migration ran successfully:
   ```sql
   SELECT COUNT(*) FROM eventos_inscripciones WHERE codigo_qr IS NOT NULL;
   ```
2. Check indexes exist:
   ```sql
   SHOW INDEXES FROM eventos_inscripciones;
   ```
3. Test query directly in MySQL

### Problem: Terms Page Shows 404

**Symptoms:** Clicking terms link shows "Page Not Found"

**Solutions:**
1. Verify files uploaded:
   ```bash
   ls -la terminos.php privacidad.php
   ```
2. Check .htaccess doesn't block access
3. Verify file permissions (644)

---

## Security Checklist

After installation, verify these security measures:

- [ ] Database credentials are not in public directories
- [ ] File upload directory is protected (no direct PHP execution)
- [ ] SSL/HTTPS is enabled on production
- [ ] Error reporting is disabled in production (`display_errors = 0`)
- [ ] Database backups are scheduled
- [ ] Strong passwords for admin accounts
- [ ] SMTP credentials are secure

---

## Performance Optimization

For better performance in production:

### 1. Enable OPcache

Add to `php.ini`:
```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0
```

### 2. Database Indexes

Already included in migration, but verify:
```sql
SHOW INDEXES FROM eventos_inscripciones;
```

Should see indexes on:
- codigo_qr
- whatsapp_invitado
- rfc_invitado

### 3. QR Code Caching

QR codes are automatically cached in `public/uploads/qrcodes/`

To prevent directory bloat, add cron job to clean old QR codes:
```bash
# Clean QR codes older than 90 days
0 3 * * * find /path/to/crm/public/uploads/qrcodes/ -name "*.png" -mtime +90 -delete
```

### 4. Email Queue (Advanced)

For high-volume events, consider:
- Setting up email queue with Redis/RabbitMQ
- Using background job processor
- Implementing rate limiting

---

## Maintenance

### Regular Tasks

**Daily:**
- Monitor error logs
- Check email delivery rate

**Weekly:**
- Review event registrations
- Clean old QR codes (if needed)
- Check disk space

**Monthly:**
- Database backup verification
- Security updates check
- Performance review

### Logs to Monitor

```bash
# PHP Error Log
tail -f /var/log/php_errors.log

# Web Server Log
tail -f /var/log/apache2/error.log
# or
tail -f /var/log/nginx/error.log

# CRM-specific errors
tail -f /path/to/crm/logs/error.log
```

---

## Rollback Procedure

If something goes wrong and you need to rollback:

### 1. Restore Database

```bash
mysql -u YOUR_USERNAME -p YOUR_DATABASE_NAME < backup_YYYYMMDD.sql
```

### 2. Restore Files

```bash
tar -xzf backup_files_YYYYMMDD.tar.gz -C /
```

### 3. Clear Cache

```bash
# Clear PHP OPcache
service php7.4-fpm reload

# Clear browser cache and test
```

---

## Getting Help

If you encounter issues not covered in this guide:

1. **Check Documentation:**
   - Read `IMPLEMENTATION_SUMMARY.md`
   - Review code comments

2. **Check Logs:**
   - PHP error log
   - Web server error log
   - Database error log

3. **Test Components:**
   - Test email sending separately
   - Test QR code generation
   - Test database queries

4. **Contact Support:**
   - Include error messages
   - Include PHP/MySQL versions
   - Include relevant log entries
   - Describe steps to reproduce

---

## Success Indicators

Your installation is successful when:

✅ No PHP warnings or errors appear
✅ Suspended companies page displays
✅ Configuration saves max_boletos_por_registro
✅ Event registration form works with all validations
✅ QR codes are generated
✅ Emails are sent with tickets
✅ Digital tickets can be viewed and printed
✅ Search finds event inscriptions
✅ Terms and privacy pages load

---

## Next Steps

After successful installation:

1. **Train Staff:**
   - Show how to use new features
   - Explain guest vs. company registration
   - Demonstrate ticket verification

2. **Promote Features:**
   - Update website with new registration flow
   - Communicate digital tickets to members
   - Highlight ease of registration

3. **Monitor Usage:**
   - Track registration numbers
   - Monitor email delivery
   - Collect user feedback

4. **Optimize:**
   - Adjust max_boletos based on needs
   - Fine-tune email templates
   - Improve terms and privacy content

---

## Conclusion

You should now have a fully functional CRM system with:
- Digital event tickets with QR codes
- Enhanced registration process
- Email confirmations
- Improved search capabilities
- Better security

For detailed technical information, see `IMPLEMENTATION_SUMMARY.md`.

For support, contact your system administrator or development team.

---

**Document Version:** 1.0
**Last Updated:** November 1, 2025
**Compatible With:** CRM Cámara de Comercio v3.x
