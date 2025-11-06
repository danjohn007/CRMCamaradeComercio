# Instrucciones para Migración del Sistema

## Actualización: Mejoras de Personalización y Configuración SMTP

Esta migración agrega nuevas funcionalidades al sistema CRM de la Cámara de Comercio.

### Características Nuevas

#### 1. Colores Personalizables Extendidos
Se han agregado 6 nuevos colores configurables en el sistema:
- **Color Terciario**: Para elementos complementarios
- **Color Acento 1**: Para destacar elementos importantes
- **Color Acento 2**: Para elementos especiales
- **Color Header**: Para el encabezado superior
- **Color Sidebar**: Para la barra lateral de navegación
- **Color Footer**: Para el pie de página

#### 2. Boleto Digital Optimizado
El boleto digital ha sido rediseñado con:
- Diseño compacto que cabe en una sola página A4
- Integración del logo del sistema
- Colores personalizados aplicados dinámicamente
- Información de contacto visible
- Enlace a la política de privacidad
- Código QR optimizado para impresión (180px)

#### 3. Configuración SMTP
Nueva configuración de servidor de correos integrada.

### Pasos de Instalación

1. **Ejecutar la migración SQL:**
   ```bash
   mysql -u [usuario] -p [nombre_base_datos] < database/migration_system_enhancements.sql
   ```

2. **⚠️ IMPORTANTE - Seguridad:**
   - La migración SQL contiene credenciales de correo proporcionadas por el cliente
   - **ES CRÍTICO** cambiar la contraseña SMTP inmediatamente después de ejecutar la migración
   - Acceder a: **Configuración del Sistema > Configuración de Correo SMTP**
   - Actualizar la contraseña SMTP con una más segura

3. **Configurar los colores:**
   - Ir a **Configuración del Sistema**
   - En la sección "Personalización de Diseño", ajustar los colores según la identidad visual
   - Guardar la configuración

4. **Probar el boleto digital:**
   - Crear o acceder a un evento de prueba
   - Registrar un asistente
   - Verificar que el boleto digital se muestre correctamente
   - Probar la impresión para confirmar que cabe en una página

### Recomendaciones de Seguridad

#### Credenciales SMTP
1. **Cambiar la contraseña inmediatamente** después de la instalación inicial
2. Usar una contraseña fuerte y única
3. Restringir el acceso al archivo de migración en el servidor
4. No compartir el archivo `migration_system_enhancements.sql` en repositorios públicos
5. Considerar el uso de variables de entorno para credenciales en futuras actualizaciones

#### Permisos de Archivo
```bash
# Restringir permisos del archivo de migración
chmod 600 database/migration_system_enhancements.sql
```

#### Backup
Antes de ejecutar cualquier migración, realizar un backup completo de la base de datos:
```bash
mysqldump -u [usuario] -p [nombre_base_datos] > backup_pre_migracion_$(date +%Y%m%d).sql
```

### Verificación Post-Migración

1. **Verificar colores:**
   - Acceder al sistema con diferentes roles
   - Confirmar que los colores se aplican correctamente en header, sidebar y footer
   - Verificar que los botones y elementos usen los colores personalizados

2. **Verificar boleto digital:**
   - Generar un boleto de prueba
   - Verificar que aparezca el logo del sistema
   - Confirmar que los colores personalizados se apliquen
   - Probar la impresión (debe caber en una página sin cortes)

3. **Verificar configuración SMTP:**
   - Ir a Configuración del Sistema
   - Verificar que los datos SMTP estén configurados
   - **Cambiar la contraseña** a una segura
   - Probar envío de correo de prueba

### Soporte

Para más información o problemas durante la migración, consultar:
- GUIA_SISTEMA.md
- DEPLOYMENT_INSTRUCTIONS.md
- Contactar al administrador del sistema

### Notas Técnicas

- **Base de datos:** MySQL 5.7+
- **PHP:** 7.4+
- **Archivos modificados:**
  - `configuracion.php`
  - `boleto_digital.php`
  - `app/views/layouts/header.php`
  - `app/views/layouts/footer.php`
  - `database/migration_system_enhancements.sql` (nuevo)

### Changelog

**Versión:** Noviembre 2025
- Agregados 6 colores personalizables adicionales
- Rediseñado boleto digital para formato compacto (una página A4)
- Configuración SMTP integrada en migración SQL
- Mejoras en la aplicación de CSS variables para colores dinámicos
- Footer con color personalizable
- Header con color personalizable
- Sidebar con color personalizable
