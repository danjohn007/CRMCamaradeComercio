# Guía de Instalación - Ajustes del Sistema

## ⚡ Pasos Rápidos de Instalación

### 1. Aplicar Credenciales de PayPal

**Opción A: Desde línea de comandos**
```bash
mysql -u tu_usuario -p tu_base_de_datos < database/update_paypal_credentials.sql
```

**Opción B: Desde phpMyAdmin**
1. Acceder a phpMyAdmin
2. Seleccionar tu base de datos
3. Ir a la pestaña "SQL"
4. Copiar y pegar el contenido de `database/update_paypal_credentials.sql`
5. Ejecutar

**Opción C: Manualmente**
Actualizar los siguientes registros en la tabla `configuracion`:

| clave | valor |
|-------|-------|
| paypal_client_id | Ads5V1Ttz4gtLmCYSZBxErKYdsA5hc4XvqyE7FVfM7WRLzO-DNuNtXUtzq6GvhMUUvOxiens7EnBeMXD |
| paypal_secret | EJ6hBDoya6zU3iHQDDrSL-nklSDUbvgVuHVgg9MnwBbVrhJq9MKYV_PsOnKYqKiUy5vQVc5ipxuRcpvv |
| paypal_mode | sandbox |

---

## ✅ Verificación de Instalación

### Paso 1: Verificar Directorio Público

1. **Abrir en navegador:**
   ```
   https://tu-dominio.com/directorio_publico.php
   ```

2. **Verificar que:**
   - ✅ Se muestran las empresas activas
   - ✅ El diseño usa los colores del sistema
   - ✅ El logotipo aparece en el header (si está configurado)
   - ✅ La búsqueda funciona correctamente
   - ✅ Los filtros funcionan
   - ✅ La paginación funciona
   - ✅ Los datos de contacto se muestran correctamente

3. **Probar búsqueda:**
   - Buscar por nombre de empresa
   - Buscar por palabra clave en servicios
   - Aplicar filtros de sector/categoría/ciudad
   - Navegar entre páginas

---

### Paso 2: Verificar Boletos Gratuitos por Vigencia de Membresía

**Caso A: Empresa con membresía vigente**

1. Ir a un evento público (con costo)
2. Buscar empresa por WhatsApp o RFC
3. Seleccionar una empresa que tenga:
   - `activo = 1`
   - `fecha_renovacion` reciente
   - `membresia_id` asociado a una membresía con `vigencia_meses`
   - La fecha actual debe estar dentro de `fecha_renovacion + vigencia_meses`

4. **Resultado esperado:**
   - ✅ Debe indicar: "Como empresa afiliada, tu primer boleto es gratuito"
   - ✅ Si solicita 2+ boletos, solo debe cobrar los adicionales
   - ✅ El precio mostrado debe ser: `(boletos_solicitados - 1) * precio_evento`

**Caso B: Empresa con membresía vencida**

1. Ir al mismo evento público
2. Buscar empresa que tenga:
   - `activo = 1`
   - `fecha_renovacion` antigua
   - La fecha actual está fuera de `fecha_renovacion + vigencia_meses`

3. **Resultado esperado:**
   - ✅ NO debe mencionar boleto gratuito
   - ✅ Debe cobrar todos los boletos solicitados
   - ✅ El precio debe ser: `boletos_solicitados * precio_evento`

**Para verificar vigencia manualmente:**
```sql
SELECT 
    e.id,
    e.razon_social,
    e.fecha_renovacion,
    m.vigencia_meses,
    DATE_ADD(e.fecha_renovacion, INTERVAL m.vigencia_meses MONTH) as fecha_vencimiento,
    CASE 
        WHEN DATE_ADD(e.fecha_renovacion, INTERVAL m.vigencia_meses MONTH) >= CURDATE() 
        THEN 'VIGENTE' 
        ELSE 'VENCIDA' 
    END as estado_membresia
FROM empresas e
LEFT JOIN membresias m ON e.membresia_id = m.id
WHERE e.activo = 1
ORDER BY e.razon_social;
```

---

### Paso 3: Verificar Botón de PayPal

1. **Registrarse a un evento con costo:**
   - Ir a un evento público
   - Completar el formulario de registro
   - Solicitar boletos que requieran pago

2. **Verificar el botón de PayPal:**
   - ✅ El botón de PayPal debe aparecer
   - ✅ Al hacer clic, debe abrir el popup/ventana de PayPal
   - ✅ NO debe quedarse en estado "Procesando"
   - ✅ NO debe aparecer mensaje de error en consola

3. **Si el botón no aparece:**
   - Verificar que las credenciales de PayPal estén configuradas
   - Verificar en consola del navegador (F12) si hay errores JavaScript
   - Verificar que `paypal_client_id` esté en la tabla `configuracion`

4. **Completar el pago:**
   - Usar cuenta de prueba de PayPal Sandbox
   - Completar el flujo de pago
   - Verificar que se reciba el email con los boletos

---

### Paso 4: Verificar Formato de Emails

**Preparación:**
1. Asegurarse de que en Configuración del Sistema estén definidos:
   - Color primario
   - Color secundario
   - Color de acento 1
   - Logotipo del sistema (opcional pero recomendado)

2. Configurar un email de prueba accesible

**Prueba 1: Email de Registro (sin pago)**
1. Registrarse a un evento gratuito
2. Recibir email de confirmación
3. **Verificar:**
   - ✅ El header usa el color primario del sistema
   - ✅ El logotipo aparece en el header (si está configurado)
   - ✅ Los botones usan el color secundario
   - ✅ El footer tiene información de contacto
   - ✅ El diseño es consistente y profesional

**Prueba 2: Email de Registro (con pago pendiente)**
1. Registrarse a un evento con costo
2. Recibir email con link de pago
3. **Verificar:**
   - ✅ El header usa el color primario
   - ✅ El logotipo aparece
   - ✅ La caja de advertencia usa el color de acento
   - ✅ El monto a pagar se muestra correctamente

**Prueba 3: Email de Boletos (después del pago)**
1. Completar el pago de un evento
2. Recibir email con boletos
3. **Verificar:**
   - ✅ El header usa el color secundario (verde)
   - ✅ El logotipo aparece
   - ✅ El código QR se muestra correctamente
   - ✅ Toda la información del evento está presente

---

## 🔧 Configuración del Sistema

### Configurar Colores (si no están configurados)

1. Ir a **Configuración del Sistema** como administrador
2. En la sección de **Estilos y Colores**:
   - **Color Primario:** #1E40AF (azul, para headers)
   - **Color Secundario:** #10B981 (verde, para botones de acción)
   - **Color Acento 1:** #F59E0B (naranja/amarillo, para advertencias)

3. Guardar cambios

### Configurar Logotipo

1. En **Configuración del Sistema**
2. Subir logotipo en la sección correspondiente
3. Formatos aceptados: JPG, PNG, GIF, SVG
4. Tamaño máximo: 2MB
5. Recomendado: Imagen cuadrada o horizontal, fondo transparente

---

## 🐛 Solución de Problemas

### Problema: El directorio público no muestra empresas

**Solución:**
```sql
-- Verificar que hay empresas activas
SELECT COUNT(*) FROM empresas WHERE activo = 1;

-- Si no hay, activar algunas empresas de prueba
UPDATE empresas SET activo = 1 WHERE id IN (1, 2, 3);
```

### Problema: PayPal no se carga

**Verificar:**
1. Credenciales en `configuracion` tabla
2. Consola del navegador (F12) para errores JavaScript
3. Que el Client ID sea el correcto

**Solución rápida:**
```sql
-- Verificar credenciales
SELECT * FROM configuracion WHERE clave LIKE 'paypal%';

-- Re-aplicar si es necesario
UPDATE configuracion SET valor = 'Ads5V1Ttz4gtLmCYSZBxErKYdsA5hc4XvqyE7FVfM7WRLzO-DNuNtXUtzq6GvhMUUvOxiens7EnBeMXD' WHERE clave = 'paypal_client_id';
```

### Problema: Los emails no tienen colores

**Verificar:**
```sql
-- Verificar configuración de colores
SELECT * FROM configuracion WHERE clave LIKE 'color%';
```

**Si faltan, insertar:**
```sql
INSERT INTO configuracion (clave, valor, descripcion, categoria, tipo) VALUES 
('color_primario', '#1E40AF', 'Color primario del sistema', 'Estilos', 'color'),
('color_secundario', '#10B981', 'Color secundario del sistema', 'Estilos', 'color'),
('color_acento1', '#F59E0B', 'Color de acento 1', 'Estilos', 'color')
ON DUPLICATE KEY UPDATE valor = VALUES(valor);
```

### Problema: La búsqueda no funciona

**Verificar:**
1. Que las empresas tengan datos en los campos:
   - `servicios_productos`
   - `palabras_clave`
   - `descripcion`

2. Agregar datos de prueba:
```sql
UPDATE empresas 
SET servicios_productos = 'Servicios de consultoría empresarial, asesoría legal, contabilidad',
    palabras_clave = 'consultoría, asesoría, legal, contable, empresas',
    descripcion = 'Empresa dedicada a la consultoría y asesoría empresarial'
WHERE id = 1;
```

---

## 📊 Verificación Final

### Checklist de Funcionalidades

- [ ] Directorio público accesible sin login
- [ ] Búsqueda por texto funciona
- [ ] Filtros (sector, categoría, ciudad) funcionan
- [ ] Paginación funciona correctamente
- [ ] Empresas con membresía vigente reciben boleto gratis
- [ ] Empresas con membresía vencida NO reciben boleto gratis
- [ ] Botón de PayPal abre popup correctamente
- [ ] PayPal procesa pagos en modo sandbox
- [ ] Emails usan colores del sistema
- [ ] Emails muestran logotipo (si está configurado)
- [ ] Link al directorio en página de login

---

## 🔐 Seguridad

### Consideraciones implementadas:
- ✅ Directorio público solo muestra empresas activas
- ✅ No expone información sensible
- ✅ Sanitización de parámetros GET
- ✅ Paginación para evitar carga excesiva
- ✅ Consultas parametrizadas (prepared statements)

### Recomendaciones adicionales:
- Implementar rate limiting para búsquedas
- Agregar captcha si se detecta abuso
- Monitorear logs de búsquedas

---

## 📞 Soporte

Si encuentras problemas:

1. **Revisar logs:**
   - PHP error log
   - Consola del navegador (F12)
   - Logs de PayPal (en cuenta sandbox)

2. **Verificar prerrequisitos:**
   - PHP 7.4+
   - MySQL 5.7+
   - Extensiones: PDO, PDO_MySQL, JSON

3. **Documentación adicional:**
   - `CAMBIOS_AJUSTES_SISTEMA_FINAL.md` - Detalles técnicos
   - `README.md` - Información general del sistema

---

**Última actualización:** Noviembre 2025
**Versión:** 1.0
