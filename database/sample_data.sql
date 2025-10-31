-- Datos de ejemplo del estado de Querétaro
USE crm_camara_comercio;

-- Configuración del sistema
INSERT INTO configuracion (clave, valor, descripcion) VALUES
('nombre_sitio', 'CRM Cámara de Comercio de Querétaro', 'Nombre del sitio'),
('email_sistema', 'contacto@camaraqro.com', 'Email principal del sistema'),
('whatsapp_chatbot', '4421234567', 'Número de WhatsApp del chatbot'),
('telefono_contacto', '442-123-4567', 'Teléfono de contacto'),
('horario_atencion', 'Lunes a Viernes 9:00 AM - 6:00 PM', 'Horario de atención'),
('paypal_account', 'pagos@camaraqro.com', 'Cuenta principal de PayPal'),
('dias_aviso_renovacion', '30,15,5', 'Días de aviso antes del vencimiento'),
('color_primario', '#1E40AF', 'Color primario del sistema'),
('color_secundario', '#10B981', 'Color secundario del sistema'),
('terminos_condiciones', 'Términos y condiciones del servicio...', 'Términos y condiciones'),
('politica_privacidad', 'Política de privacidad...', 'Política de privacidad');

-- Sectores
INSERT INTO sectores (nombre, descripcion) VALUES
('Comercio', 'Empresas dedicadas a la compra-venta de productos'),
('Servicios', 'Empresas que ofrecen servicios profesionales y especializados'),
('Turismo', 'Empresas relacionadas con hoteles, restaurantes y turismo');

-- Categorías
INSERT INTO categorias (nombre, descripcion, sector_id) VALUES
-- Comercio
('Abarrotes y Alimentos', 'Venta de productos alimenticios', 1),
('Ferreterías y Materiales', 'Venta de materiales de construcción y ferretería', 1),
('Ropa y Calzado', 'Comercios de prendas de vestir y calzado', 1),
('Electrónica y Tecnología', 'Venta de equipos electrónicos y tecnológicos', 1),
('Farmacias', 'Venta de medicamentos y productos de salud', 1),
('Mueblerías', 'Venta de muebles y decoración', 1),
('Papelerías', 'Venta de artículos de papelería y oficina', 1),

-- Servicios
('Consultoría Empresarial', 'Servicios de asesoría y consultoría', 2),
('Contabilidad y Finanzas', 'Servicios contables y financieros', 2),
('Marketing y Publicidad', 'Agencias de marketing y publicidad', 2),
('Tecnologías de Información', 'Desarrollo de software y servicios IT', 2),
('Servicios Legales', 'Despachos de abogados y notarías', 2),
('Salud y Medicina', 'Clínicas, consultorios y servicios médicos', 2),
('Educación y Capacitación', 'Institutos educativos y de capacitación', 2),
('Construcción', 'Servicios de construcción y arquitectura', 2),
('Transporte y Logística', 'Servicios de transporte y distribución', 2),

-- Turismo
('Hoteles y Hospedaje', 'Hoteles, moteles y servicios de alojamiento', 3),
('Restaurantes', 'Establecimientos de alimentos y bebidas', 3),
('Agencias de Viajes', 'Agencias y operadores turísticos', 3),
('Entretenimiento', 'Centros de entretenimiento y recreación', 3);

-- Membresías
INSERT INTO membresias (nombre, descripcion, costo, beneficios, vigencia_meses) VALUES
('Básica', 'Membresía ideal para nuevas empresas', 1500.00, 'Acceso a eventos básicos, directorio de empresas, newsletter mensual', 12),
('Plata', 'Membresía estándar con más beneficios', 3000.00, 'Todo lo de Básica + descuentos en eventos, consultoría trimestral, capacitaciones', 12),
('Oro', 'Membresía premium para empresas consolidadas', 5000.00, 'Todo lo de Plata + stands preferenciales, publicidad en medios, consultoría mensual', 12),
('Platino', 'Membresía exclusiva con todos los beneficios', 8000.00, 'Todos los beneficios + membresía vitalicia, asesoría ilimitada, networking VIP', 12);

-- Vendedores
INSERT INTO vendedores (nombre, email, telefono) VALUES
('María González', 'mgonzalez@camaraqro.com', '442-111-1111'),
('Juan Pérez', 'jperez@camaraqro.com', '442-222-2222'),
('Ana Martínez', 'amartinez@camaraqro.com', '442-333-3333'),
('Carlos Rodríguez', 'crodriguez@camaraqro.com', '442-444-4444');

-- Empresas de ejemplo de Querétaro
INSERT INTO empresas (no_registro, no_mes, fecha_recibo, no_recibo, razon_social, es_nueva, vendedor_id, tipo_afiliacion, membresia_id, direccion_comercial, fecha_renovacion, rfc, email, telefono, whatsapp, representante, sector_id, categoria_id, direccion_fiscal, colonia, ciudad, codigo_postal, estado, verificado) VALUES
(1, 1, '2024-01-15', 'R-001', 'Tecnología Querétaro SA de CV', 1, 1, 'NUEVA', 3, 'Av. 5 de Febrero 101', '2025-01-15', 'TQU240115A12', 'contacto@tecnologiaqro.com', '442-100-1000', '4421001000', 'Ing. Roberto Sánchez', 2, 11, 'Av. 5 de Febrero 101, Col. Centro', 'Centro', 'Santiago de Querétaro', '76000', 'Querétaro', 1),
(2, 1, '2024-01-20', 'R-002', 'Restaurante La Queretana', 1, 2, 'NUEVA', 2, 'Calle Corregidora 45', '2025-01-20', 'RLQ240120B34', 'info@laqueretana.com', '442-200-2000', '4422002000', 'Chef María López', 3, 18, 'Calle Corregidora 45, Col. Centro', 'Centro', 'Santiago de Querétaro', '76000', 'Querétaro', 1),
(3, 2, '2024-02-10', 'R-003', 'Consultoría Empresarial del Bajío', 1, 1, 'NUEVA', 3, 'Av. Universidad 200', '2025-02-10', 'CEB240210C56', 'contacto@cebajio.com', '442-300-3000', '4423003000', 'Lic. Fernando Ramírez', 2, 8, 'Av. Universidad 200, Col. Centro Sur', 'Centro Sur', 'Santiago de Querétaro', '76090', 'Querétaro', 1),
(4, 2, '2024-02-15', 'R-004', 'Ferretería El Constructor', 0, 3, 'RENOVACION', 1, 'Blvd. Bernardo Quintana 1500', '2025-02-15', 'FEC190215D78', 'ventas@elconstructor.com', '442-400-4000', '4424004000', 'Sr. Pedro Hernández', 1, 2, 'Blvd. Bernardo Quintana 1500, Col. San Pablo', 'San Pablo', 'Santiago de Querétaro', '76125', 'Querétaro', 1),
(5, 3, '2024-03-05', 'R-005', 'Hotel Boutique Querétaro', 1, 2, 'NUEVA', 4, 'Calle Allende 15', '2025-03-05', 'HBQ240305E90', 'reservas@hotelboutiqueqro.com', '442-500-5000', '4425005000', 'Sra. Laura Morales', 3, 17, 'Calle Allende 15, Col. Centro Histórico', 'Centro Histórico', 'Santiago de Querétaro', '76000', 'Querétaro', 1),
(6, 3, '2024-03-12', 'R-006', 'Despacho Jurídico Querétaro', 1, 1, 'NUEVA', 2, 'Av. Constituyentes 300', '2025-03-12', 'DJQ240312F12', 'contacto@djqro.com', '442-600-6000', '4426006000', 'Lic. Ricardo Torres', 2, 12, 'Av. Constituyentes 300, Col. El Jacal', 'El Jacal', 'Santiago de Querétaro', '76170', 'Querétaro', 1),
(7, 4, '2024-04-08', 'R-007', 'Farmacia Santa Fe', 0, 3, 'RENOVACION', 1, 'Av. Zaragoza 50', '2025-04-08', 'FSF200408G34', 'contacto@farmaciasantafe.com', '442-700-7000', '4427007000', 'Q.F.B. Ana Ruiz', 1, 5, 'Av. Zaragoza 50, Col. Centro', 'Centro', 'Santiago de Querétaro', '76000', 'Querétaro', 1),
(8, 4, '2024-04-20', 'R-008', 'Agencia Digital Marketing360', 1, 2, 'NUEVA', 3, 'Calle Pasteur 123', '2025-04-20', 'ADM240420H56', 'hola@marketing360qro.com', '442-800-8000', '4428008000', 'Lic. Miguel Ángel Castro', 2, 10, 'Calle Pasteur 123, Col. Niños Héroes', 'Niños Héroes', 'Santiago de Querétaro', '76010', 'Querétaro', 1),
(9, 5, '2024-05-15', 'R-009', 'Mueblería La Casa Ideal', 1, 4, 'NUEVA', 2, 'Av. 5 de Febrero 500', '2025-05-15', 'MCI240515I78', 'ventas@lacasaideal.com', '442-900-9000', '4429009000', 'Sr. Javier Méndez', 1, 6, 'Av. 5 de Febrero 500, Col. La Cruz', 'La Cruz', 'Santiago de Querétaro', '76020', 'Querétaro', 1),
(10, 5, '2024-05-25', 'R-010', 'Transportes del Norte', 0, 1, 'RENOVACION', 2, 'Carretera México 57 Km 210', '2025-05-25', 'TDN180525J90', 'operaciones@transportesnorte.com', '442-101-0100', '4421010100', 'Ing. Alberto Díaz', 2, 16, 'Carretera México 57 Km 210, Col. La Negreta', 'La Negreta', 'Santiago de Querétaro', '76140', 'Querétaro', 1);

-- Usuario administrador por defecto
INSERT INTO usuarios (nombre, email, password, rol, activo, email_verificado) VALUES
('Administrador Sistema', 'admin@camaraqro.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'PRESIDENCIA', 1, 1);
-- Password: password

-- Usuario de dirección
INSERT INTO usuarios (nombre, email, password, rol, activo, email_verificado) VALUES
('Director General', 'director@camaraqro.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'DIRECCION', 1, 1);

-- Usuario afilador
INSERT INTO usuarios (nombre, email, password, rol, activo, email_verificado) VALUES
('María González', 'afilador@camaraqro.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'AFILADOR', 1, 1);

-- Eventos de ejemplo
INSERT INTO eventos (titulo, descripcion, fecha_inicio, fecha_fin, ubicacion, tipo, cupo_maximo, requiere_inscripcion, creado_por) VALUES
('Expo Empresarial Querétaro 2025', 'Gran exposición de empresas locales con networking y conferencias', '2025-03-15 09:00:00', '2025-03-15 18:00:00', 'Centro de Congresos Querétaro', 'PUBLICO', 500, 1, 1),
('Taller de Transformación Digital', 'Aprende sobre las últimas tendencias en digitalización empresarial', '2025-02-20 10:00:00', '2025-02-20 14:00:00', 'Auditorio Cámara de Comercio', 'PUBLICO', 100, 1, 1),
('Reunión de Consejo Directivo', 'Sesión mensual del consejo directivo', '2025-02-05 16:00:00', '2025-02-05 18:00:00', 'Sala de Juntas', 'CONSEJO', 20, 0, 1),
('Desayuno Empresarial: Finanzas 2025', 'Charla sobre perspectivas financieras y fiscales', '2025-02-28 08:00:00', '2025-02-28 11:00:00', 'Hotel Boutique Querétaro', 'PUBLICO', 80, 1, 2);

-- Requerimientos de ejemplo
INSERT INTO requerimientos (titulo, descripcion, empresa_solicitante_id, usuario_creador_id, sector_id, categoria_id, palabras_clave, presupuesto_estimado, plazo_dias, estado) VALUES
('Proveedor de Uniformes Empresariales', 'Buscamos proveedor de uniformes para 50 empleados, incluye bordado de logo', 1, 1, 1, 3, 'uniformes, bordado, ropa laboral', 25000.00, 30, 'ABIERTO'),
('Desarrollo de Sitio Web Corporativo', 'Necesitamos desarrollar sitio web responsive con catálogo de productos', 4, 1, 2, 11, 'desarrollo web, diseño, e-commerce', 50000.00, 60, 'ABIERTO'),
('Servicio de Catering para Eventos', 'Buscamos proveedor de catering para eventos corporativos mensuales', 5, 1, 3, 18, 'catering, banquetes, eventos', 15000.00, 15, 'ABIERTO');

-- Notificaciones de ejemplo
INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, leida) VALUES
(1, 'SISTEMA', 'Bienvenido al Sistema', 'Gracias por usar el CRM de la Cámara de Comercio', 0),
(2, 'RENOVACION', 'Próxima Renovación', 'Tu membresía vence en 30 días', 0),
(3, 'EVENTO', 'Nuevo Evento Disponible', 'Se ha publicado un nuevo evento: Expo Empresarial 2025', 0);
