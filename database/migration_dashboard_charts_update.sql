-- ================================================
-- Migración: Actualización de Dashboard y Reportes
-- Fecha: 2025-11-01
-- Descripción: Actualización del sistema con corrección del error de htmlspecialchars
--              y adición de 8 gráficas en Dashboard y 4 en Reportes para roles administrativos
-- ================================================

-- Este archivo documenta los cambios realizados en el sistema.
-- NO requiere ejecución en base de datos ya que los cambios son principalmente en PHP/JavaScript

-- ================================================
-- CAMBIOS REALIZADOS
-- ================================================

-- 1. CORRECCIÓN DE ERROR PHP (app/helpers/functions.php línea 233)
--    - Se corrigió el error: "Deprecated: htmlspecialchars(): Passing null to parameter #1 ($string) of type string is deprecated"
--    - Solución: La función e() ahora maneja valores null correctamente, retornando string vacío
--    - Código anterior:
--      function e($string) {
--          return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
--      }
--    - Código actualizado:
--      function e($string) {
--          if ($string === null) {
--              return '';
--          }
--          return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
--      }

-- 2. ADICIÓN DE LIBRERÍA CHART.JS (app/views/layouts/header.php)
--    - Se agregó Chart.js v4.4.0 para renderizar gráficas interactivas
--    - URL CDN: https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js

-- 3. DASHBOARD - 8 NUEVAS GRÁFICAS (dashboard.php)
--    Solo visible para roles: PRESIDENCIA, DIRECCION, CONSEJERO, AFILADOR
--    
--    Gráfica 1: Empresas por Membresía (Doughnut Chart)
--    - Muestra distribución de empresas según tipo de membresía
--    - Query: SELECT m.nombre, COUNT(e.id) FROM membresias m LEFT JOIN empresas e
--    
--    Gráfica 2: Empresas por Sector (Bar Chart Horizontal)
--    - Top 10 sectores con más empresas afiliadas
--    - Query: SELECT s.nombre, COUNT(e.id) FROM sectores s LEFT JOIN empresas e LIMIT 10
--    
--    Gráfica 3: Ingresos por Mes (Line Chart)
--    - Tendencia de ingresos en los últimos 6 meses
--    - Query: SELECT DATE_FORMAT(fecha_pago, '%Y-%m'), SUM(monto) FROM pagos
--    
--    Gráfica 4: Nuevas Empresas por Mes (Bar Chart)
--    - Cantidad de nuevas afiliaciones por mes (últimos 6 meses)
--    - Query: SELECT DATE_FORMAT(created_at, '%Y-%m'), COUNT(*) FROM empresas
--    
--    Gráfica 5: Estado de Membresías (Pie Chart)
--    - Distribución: Activas, Próximas a Vencer, Vencidas
--    - Usa datos ya calculados en el dashboard
--    
--    Gráfica 6: Eventos por Tipo (Doughnut Chart)
--    - Distribución de eventos según su tipo (PUBLICO, PRIVADO, etc.)
--    - Query: SELECT tipo, COUNT(*) FROM eventos
--    
--    Gráfica 7: Requerimientos por Estado (Bar Chart)
--    - Distribución de requerimientos según estado (ABIERTO, CERRADO, etc.)
--    - Query: SELECT estado, COUNT(*) FROM requerimientos
--    
--    Gráfica 8: Empresas por Ciudad (Bar Chart Horizontal)
--    - Top 10 ciudades con mayor número de empresas afiliadas
--    - Query: SELECT ciudad, COUNT(*) FROM empresas WHERE ciudad IS NOT NULL LIMIT 10

-- 4. REPORTES - 4 NUEVAS GRÁFICAS (reportes.php)
--    Solo visible para roles: PRESIDENCIA, DIRECCION, CONSEJERO, AFILADOR
--    
--    Gráfica 1: Ingresos por Membresía (Bar Chart)
--    - Ubicación: Pestaña "Ingresos"
--    - Muestra ingresos totales por tipo de membresía
--    - Query: Ya existente en $ingresosPorMembresia
--    
--    Gráfica 2: Tendencia de Ingresos (Line Chart)
--    - Ubicación: Pestaña "Ingresos"
--    - Muestra evolución de ingresos mes a mes (últimos 12 meses)
--    - Query: Ya existente en $ingresosPorMes
--    
--    Gráfica 3: Distribución por Sector (Pie Chart)
--    - Ubicación: Pestaña "Empresas"
--    - Muestra porcentaje de empresas por sector
--    - Query: Ya existente en $empresasPorSector
--    
--    Gráfica 4: Crecimiento de Afiliaciones (Line Chart)
--    - Ubicación: Pestaña "Empresas"
--    - Muestra tendencia de nuevas afiliaciones por mes (último año)
--    - Query: Ya existente en $nuevasEmpresasPorMes

-- ================================================
-- VERIFICACIÓN DE TABLAS NECESARIAS
-- ================================================
-- Las siguientes tablas deben existir y son utilizadas por las gráficas:

-- Verificar tabla empresas
SELECT COUNT(*) as total_empresas FROM empresas;

-- Verificar tabla membresias
SELECT COUNT(*) as total_membresias FROM membresias;

-- Verificar tabla sectores
SELECT COUNT(*) as total_sectores FROM sectores;

-- Verificar tabla pagos
SELECT COUNT(*) as total_pagos FROM pagos;

-- Verificar tabla eventos
SELECT COUNT(*) as total_eventos FROM eventos;

-- Verificar tabla requerimientos
SELECT COUNT(*) as total_requerimientos FROM requerimientos;

-- ================================================
-- NOTAS IMPORTANTES
-- ================================================
-- 1. Las gráficas solo son visibles para usuarios con permisos de nivel AFILADOR o superior
--    (PRESIDENCIA, DIRECCION, CONSEJERO, AFILADOR)
-- 2. Las gráficas utilizan Chart.js v4.4.0 desde CDN, no requiere instalación local
-- 3. Todas las gráficas son responsive y se adaptan a diferentes tamaños de pantalla
-- 4. Los colores utilizados son consistentes con el tema del sistema
-- 5. Las consultas utilizan datos históricos de los últimos 6-12 meses según la gráfica

-- ================================================
-- COMPATIBILIDAD
-- ================================================
-- PHP: 7.4+
-- MySQL: 5.7+
-- Navegadores: Chrome 90+, Firefox 88+, Safari 14+, Edge 90+
-- Chart.js: 4.4.0

-- ================================================
-- FIN DE MIGRACIÓN
-- ================================================
