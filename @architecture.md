# Arquitectura del Sistema - eBike Management System (EMS)

## 1. Stack Tecnológico Estricto
- **Backend:** PHP 8.2 puro.
- **Frontend:** HTML5, CSS3, JavaScript (Vanilla) y Bootstrap 5.3.
- **Base de Datos:** MySQL 8.0 / MariaDB (Definida en `@database.md`).
- **Autoloading:** Composer (estándar PSR-4).

## 2. Patrón de Diseño Arquitectónico
El sistema debe seguir el patrón **MVC (Model-View-Controller)** de forma estricta.
- **Controllers:** Reciben peticiones HTTP, validan entradas y retornan respuestas. NO contienen lógica de negocio compleja ni consultas SQL directas.
- **Models / Repositories:** Única capa autorizada para ejecutar sentencias SQL usando **PDO**.
- **Views:** Vistas HTML con PHP nativo. Sin consultas a la base de datos dentro de la vista.
- **Middleware:** Manejo de seguridad (JWT y verificación de roles).

## 3. Estructura de Directorios Obligatoria (Código MVP)
El agente debe generar y respetar estrictamente la siguiente estructura de carpetas:

```text
ebike/
├── app/
│   ├── config/
│   │   ├── database.php          # Conexión PDO
│   │   ├── constants.php         # Constantes globales
│   │   └── routes.php            # Enrutador
│   │
│   ├── controllers/
│   │   ├── AuthController.php    # Autenticación
│   │   ├── BikeController.php    # Gestión bicicletas
│   │   ├── RentalController.php  # Lógica alquileres
│   │   ├── StationController.php # Gestión estaciones
│   │   └── DashboardController.php # Métricas
│   │
│   ├── models/
│   │   ├── Database.php          # Singleton PDO
│   │   ├── User.php              # Modelo Usuario
│   │   ├── Bike.php              # Modelo Bicicleta
│   │   ├── Station.php           # Modelo Estación
│   │   └── Rental.php            # Modelo Alquiler
│   │
│   ├── views/
│   │   ├── layouts/
│   │   │   ├── header.php        # Header común
│   │   │   └── footer.php        # Footer común
│   │   ├── auth/
│   │   │   ├── login.php         # Vista login
│   │   │   └── register.php      # Vista registro
│   │   ├── admin/
│   │   │   └── dashboard.php     # Panel administrador
│   │   └── user/
│   │       ├── dashboard.php     # Panel cliente
│   │       └── rental.php        # Interfaz de alquiler
│   │
│   ├── helpers/
│   │   ├── Validator.php         # Sanitización y validación
│   │   └── Session.php           # Manejo de JWT/Sesiones
│   │
│   └── middleware/
│       └── AuthMiddleware.php    # Proteger rutas privadas
│
├── public/
│   ├── index.php                 # Front Controller (Entry point)
│   ├── .htaccess                 # Reescritura Apache
│   └── assets/
│       ├── css/
│       │   └── styles.css        # Estilos personalizados
│       └── js/
│           └── app.js            # Lógica cliente / Fetch API
│
├── composer.json                 # Autoload PSR-4
└── README.md

```

## 4. Reglas de Enrutamiento e Infraestructura
Todo el tráfico web entrante debe ser enrutado de manera obligatoria a través de `public/index.php` (Front Controller). Queda strictly prohibido el acceso directo a los archivos PHP dentro del directorio `app/`.

## 5. Formato de Comunicación API
El intercambio de datos para las APIs internas y respuestas del backend hacia las peticiones Fetch / AJAX del frontend debe realizarse estrictamente en formato JSON (`header('Content-Type: application/json')`). 