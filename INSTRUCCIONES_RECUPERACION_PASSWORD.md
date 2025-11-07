# Instrucciones de Despliegue - Recuperación de Contraseña y Correcciones

## Resumen de Cambios

Esta actualización incluye las siguientes correcciones:

1. **Funcionalidad de Recuperación de Contraseña** - Ahora los usuarios pueden recuperar su contraseña mediante un enlace enviado por correo
2. **Corrección del Email del Sistema** - Los correos ahora usan el "email_sistema" configurado en Configuración del Sistema
3. **Nombre del Sitio en Header y Footer** - Se muestra el "nombre_sitio" configurado en lugar del nombre fijo del sistema
4. **Colores Aplicados** - El nombre del sitio responde a los colores configurados en el sistema

## Pasos de Instalación

### Paso 1: Respaldar Base de Datos

```bash
# Crear respaldo de la base de datos actual
mysqldump -u agenciae_canaco -p agenciae_canaco > backup_$(date +%Y%m%d_%H%M%S).sql
```

### Paso 2: Actualizar Código

```bash
# Ir al directorio del proyecto
cd /home/agenciae/public_html

# Hacer pull de los últimos cambios
git pull origin copilot/fix-password-recovery-error
```

### Paso 3: Ejecutar Migración de Base de Datos

**IMPORTANTE:** Este paso es crítico para añadir los campos de recuperación de contraseña.

```bash
# Ejecutar el script de migración
mysql -u agenciae_canaco -p agenciae_canaco < database/add_password_reset_fields.sql
```

Cuando se solicite, ingresa tu contraseña de base de datos.

Salida esperada:
```
Se han añadido las columnas reset_token y reset_token_expiry a la tabla usuarios.
```

### Paso 4: Verificar Migración

Verifica que las columnas fueron añadidas correctamente:

```sql
-- Iniciar sesión en MySQL
mysql -u agenciae_canaco -p agenciae_canaco

-- Verificar la estructura de la tabla
DESCRIBE usuarios;

-- Deberías ver:
-- - reset_token VARCHAR(64) NULL
-- - reset_token_expiry DATETIME NULL
```

### Paso 5: Verificar Configuración del Sistema

1. Inicia sesión como usuario PRESIDENCIA
2. Ve a: `Configuración del Sistema`
3. Verifica que los siguientes campos estén configurados:
   - **Email del Sistema** (`email_sistema`) - Email que aparecerá como remitente
   - **Nombre del Sitio** (`nombre_sitio`) - Nombre que aparece en header y footer
   - **Nombre del Remitente SMTP** (`smtp_from_name`) - Nombre del remitente de emails

Si no existen, añádelos manualmente en la tabla `configuracion`:

```sql
-- Añadir email del sistema si no existe
INSERT INTO configuracion (clave, valor, descripcion) 
VALUES ('email_sistema', 'noreply@tudominio.com', 'Email del sistema para envío de correos')
ON DUPLICATE KEY UPDATE valor = 'noreply@tudominio.com';

-- Añadir nombre del sitio si no existe
INSERT INTO configuracion (clave, valor, descripcion) 
VALUES ('nombre_sitio', 'Cámara de Comercio', 'Nombre del sitio que aparece en header y footer')
ON DUPLICATE KEY UPDATE valor = 'Cámara de Comercio';

-- Añadir nombre del remitente si no existe
INSERT INTO configuracion (clave, valor, descripcion) 
VALUES ('smtp_from_name', 'Cámara de Comercio', 'Nombre del remitente de emails')
ON DUPLICATE KEY UPDATE valor = 'Cámara de Comercio';
```

### Paso 6: Reiniciar Servicios (Si es Necesario)

```bash
# Reiniciar Apache (si usas Apache)
sudo service apache2 restart

# O reiniciar Nginx + PHP-FPM (si usas Nginx)
sudo service php-fpm restart
sudo service nginx restart
```

## Pruebas de Funcionalidad

### Prueba 1: Recuperación de Contraseña

1. Ve a la página de login: `https://tudominio.com/login.php`
2. Haz clic en "¿Olvidaste tu contraseña?"
3. **Esperado:** Te redirige a `forgot-password.php`
4. Ingresa un email válido de un usuario existente
5. **Esperado:** Mensaje de éxito y email enviado
6. Revisa el correo (y carpeta de spam)
7. **Esperado:** Email con enlace de recuperación usando el email_sistema configurado
8. Haz clic en el enlace del correo
9. **Esperado:** Te redirige a `reset-password.php` con formulario
10. Ingresa nueva contraseña y confirma
11. **Esperado:** Mensaje de éxito
12. Intenta iniciar sesión con la nueva contraseña
13. **Esperado:** Login exitoso

### Prueba 2: Email del Sistema en Registro

1. Ve a: `https://tudominio.com/register.php`
2. Completa el formulario de registro con un nuevo email
3. Envía el formulario
4. **Esperado:** Mensaje de verificación de email
5. Revisa el correo recibido
6. **Esperado:** El remitente debe ser el email_sistema y nombre configurados

### Prueba 3: Email del Sistema en Eventos

1. Ve a un evento público: `https://tudominio.com/evento_publico.php?evento=X`
2. Completa el registro al evento
3. **Esperado:** Email de confirmación con boleto
4. Revisa el correo
5. **Esperado:** El remitente usa email_sistema y pie de página muestra email_sistema

### Prueba 4: Nombre del Sitio en Header

1. Inicia sesión en el sistema
2. Ve al Dashboard
3. **Esperado:** En la parte superior izquierda del header, debe aparecer el nombre_sitio configurado (no "CRM Cámara de Comercio")
4. El texto debe estar en color blanco

### Prueba 5: Nombre del Sitio en Footer

1. Desplázate hasta el footer de cualquier página interna
2. **Esperado:** Debe mostrar "© 2025 [nombre_sitio]. Todos los derechos reservados."
3. El texto debe estar en el color configurado del footer

## Archivos Nuevos

1. `forgot-password.php` - Página para solicitar recuperación de contraseña
2. `reset-password.php` - Página para restablecer contraseña con token
3. `database/add_password_reset_fields.sql` - Migración de base de datos

## Archivos Modificados

1. `app/helpers/functions.php` - Función `sendEmail()` actualizada para usar email_sistema
2. `app/views/layouts/header.php` - Usa nombre_sitio de configuración
3. `app/views/layouts/footer.php` - Usa nombre_sitio de configuración

## Solución de Problemas

### Problema: Los correos no se envían

**Solución:**
1. Verifica que el servidor tenga configurado el servicio de correo (sendmail, postfix, etc.)
2. Revisa los logs de PHP: `/var/log/apache2/error.log` o `/var/log/nginx/error.log`
3. Verifica que el email_sistema esté correctamente configurado en la base de datos
4. Considera usar un servicio SMTP externo si el servidor no tiene servicio de correo

### Problema: El enlace de recuperación dice "token inválido"

**Verifica:**
- Que la migración se ejecutó correctamente
- Que las columnas `reset_token` y `reset_token_expiry` existen en la tabla `usuarios`
- Que el token no haya expirado (1 hora de validez)

```sql
-- Verificar tokens activos
SELECT email, reset_token, reset_token_expiry 
FROM usuarios 
WHERE reset_token IS NOT NULL;
```

### Problema: El nombre del sitio no aparece

**Verifica:**
1. Que existe el registro en configuración:
```sql
SELECT * FROM configuracion WHERE clave = 'nombre_sitio';
```

2. Si no existe, añádelo:
```sql
INSERT INTO configuracion (clave, valor, descripcion) 
VALUES ('nombre_sitio', 'Tu Nombre del Sitio', 'Nombre del sitio');
```

3. Limpia la caché del navegador (Ctrl+Shift+R o Cmd+Shift+R)

### Problema: Error 500 al intentar recuperar contraseña

**Verifica:**
1. Logs de errores de PHP
2. Que existe el archivo `forgot-password.php` en el directorio raíz
3. Permisos de archivo: `chmod 644 forgot-password.php reset-password.php`
4. Que la migración de base de datos se ejecutó correctamente

## Rollback (Si es Necesario)

Si surgen problemas y necesitas revertir los cambios:

```bash
# Revertir código
git reset --hard HEAD~1

# Restaurar base de datos
mysql -u agenciae_canaco -p agenciae_canaco < backup_YYYYMMDD_HHMMSS.sql

# Reiniciar servicios
sudo service apache2 restart
```

## Configuración Recomendada

Para aprovechar al máximo estas mejoras, configura los siguientes valores en `Configuración del Sistema`:

1. **Email del Sistema:** Un email válido de tu dominio (ej: `noreply@tudominio.com`)
2. **Nombre del Sitio:** El nombre oficial de tu cámara de comercio
3. **Nombre del Remitente SMTP:** El mismo nombre del sitio o variación
4. **Color Primario:** Color principal de tu marca
5. **Color Header:** Color del encabezado (puede ser el mismo que primario)
6. **Color Footer:** Color del pie de página (generalmente oscuro)

## Verificación Post-Despliegue

- [ ] La recuperación de contraseña funciona correctamente
- [ ] Los correos se envían con el email_sistema configurado
- [ ] El nombre del sitio aparece en el header
- [ ] El nombre del sitio aparece en el footer
- [ ] Los colores se aplican correctamente
- [ ] No hay errores en los logs de PHP
- [ ] No hay errores en la consola del navegador

## Soporte

Si encuentras problemas no cubiertos en esta guía:

1. Revisa los logs de errores de PHP
2. Revisa los logs de errores de MySQL
3. Revisa la consola del navegador para errores de JavaScript
4. Contacta al equipo de desarrollo con:
   - Pasos para reproducir el problema
   - Mensajes de error
   - Detalles del navegador/servidor

---

**Última Actualización:** Noviembre 7, 2025  
**Versión:** 1.0  
**Probado En:** PHP 7.4+, MySQL 5.7+
