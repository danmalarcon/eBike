# Requerimientos Funcionales y Reglas de Negocio - eBike Management System (EMS)

## 1. Módulo de Identidad y Acceso (IAM)
- **RF-001 (Registro de Usuarios):** Permite a nuevos clientes registrarse proporcionando nombre, email, teléfono y contraseña. El sistema asigna por defecto el rol de cliente (`rol_id = 2`) y genera un UUID único (`uuid`).
- **RF-002 (Autenticación Segura):** Inicios de sesión validados contra email y contraseña.
- **RN-001 (Unicidad de Email):** El correo electrónico debe ser único en la tabla `usuarios`[cite: 2, 3].
- **RN-004 (Cifrado Obligatorio):** Las contraseñas NUNCA deben guardarse en texto plano. Obligatorio utilizar `password_hash()` con el algoritmo `bcrypt` (cost 12)[cite: 2, 3].
- **Seguridad JWT:** La sesión del usuario se gestiona mediante tokens JWT sanitizados en cabeceras HTTP Authorization o Cookies HTTPOnly[cite: 2, 3].

## 2. Módulo de Gestión de Flota (Estaciones y Bicicletas)
- **RF-005 (CRUD de Estaciones y Bicicletas):** El Administrador puede listar, crear, editar y cambiar de estado las estaciones y bicicletas del sistema[cite: 2, 3].
- **RN-007 (Disponibilidad Estricta):** Una bicicleta solo se puede ofrecer para alquiler si su estado es `'disponible'` Y su `nivel_bateria > 20.00`[cite: 2, 3]. Bicicletas en estado `'alquilada'`, `'mantenimiento'` o `'baja'` quedan bloqueadas automáticamente[cite: 2, 3].

## 3. Módulo de Operaciones de Alquiler (Core Engine)
- **RF-003 (Inicio de Alquiler):** 
  1. El cliente selecciona una bicicleta disponible en una estación de origen (`estacion_orig`).
  2. El sistema valida la disponibilidad (`RN-007`).
  3. Crea un registro en la tabla `alquileres` con un `uuid_alquiler` nuevo, `fecha_inicio = CURRENT_TIMESTAMP` y estado `'en_curso'`[cite: 2, 3].
  4. Actualiza el estado de la bicicleta a `'alquilada'`[cite: 2, 3].
- **RF-004 (Finalización y Cálculo Automático de Tarifas):**
  1. El cliente entrega la bicicleta en una estación de destino (`estacion_dest`).
  2. El sistema registra `fecha_fin = CURRENT_TIMESTAMP` y cambia el estado del alquiler a `'completado'`[cite: 2, 3].
  3. La base de datos calcula automáticamente los minutos transcurridos en la columna virtual `duracion_minutos`[cite: 2, 3].
  4. El backend aplica la fórmula de tarifa: `costo_total = duracion_minutos * tarifa_por_minuto` (Tarifa base: $500 COP / min).
  5. La bicicleta cambia su estado de nuevo a `'disponible'` y actualiza su ubicación actual (`estacion_act = estacion_dest`).

## 4. Módulo de Dashboards y Métricas
- **RF-005 (Dashboard Admin):** Visualización en tiempo real de:
  - Total de bicicletas activas vs en mantenimiento[cite: 2, 3].
  - Número de alquileres en curso[cite: 2, 3].
  - Ingresos totales generados[cite: 2, 3].
- **Dashboard Usuario:** Vista simplificada con su alquiler activo actual y su historial de viajes finalizados.

## 5. Requerimientos No Funcionales (NFR)
- **NF-001 (Performance):** Tiempos de respuesta HTTP inferiores a 2 segundos en todas las peticiones[cite: 2, 3].
- **NF-006 (Seguridad OWASP):** Sanitización estricta de entradas en `Validator.php` para prevenir inyecciones SQL (uso de PDO con Binding Parameters) y ataques XSS[cite: 2, 3].
- **Respuestas API:** Todas las respuestas hacia el frontend para operaciones asíncronas deben ser estructuradas en JSON: `{"success": true|false, "message": "...", "data": {...}}`.