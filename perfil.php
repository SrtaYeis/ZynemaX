<?php
session_start();

// Si el usuario no está logueado, lo redirigimos a la página de inicio
if (!isset($_SESSION['dni'])) {
    header("Location: index.php");
    exit();
}

// =================================================================
//  INICIO DE LA CONEXIÓN A LA BASE DE DATOS Y OBTENCIÓN DE DATOS
// =================================================================

// Conexión a Azure SQL Database
$serverName = "database-zynemaxplus-server.database.windows.net";
// Es ALTAMENTE RECOMENDABLE almacenar las credenciales de forma segura (ej. variables de entorno),
// no directamente en el código para un entorno de producción.
$connectionInfo = ["Database" => "database-zynemaxplus-server", "UID" => "zynemaxplus", "PWD" => "grupo2_1al10", "Encrypt" => true, "TrustServerCertificate" => false];
$conn = sqlsrv_connect($serverName, $connectionInfo);

if ($conn === false) {
    // Registra el error para depuración y muestra un mensaje genérico al usuario
    error_log("Error de conexión a la base de datos: " . print_r(sqlsrv_errors(), true));
    die("Lo sentimos, no pudimos conectar con la base de datos en este momento.");
}

$dni_usuario = $_SESSION['dni'];
$historial_compras = [];

// Consulta SQL ajustada para tu esquema de base de datos
// Se asume que una "compra" es una reserva de butacas para una función de película.
// El "total_pagado" se calcula multiplicando el número de butacas reservadas por el precio de la película.
$sql_compras = "
    SELECT
        P.titulo AS pelicula,
        F.fecha_hora AS fecha_hora_funcion,
        COUNT(RB.id_butaca) AS cantidad_entradas,
        (COUNT(RB.id_butaca) * P.precio) AS total_pagado -- Calcula el total basado en entradas * precio de la película
    FROM
        Usuario U
    JOIN
        Reserva R ON U.dni = R.dni_usuario
    JOIN
        Reserva_funcion RF ON R.id_reserva = RF.id_reserva
    JOIN
        Funcion F ON RF.id_funcion = F.id_funcion
    JOIN
        Pelicula P ON F.id_pelicula = P.id_pelicula
    LEFT JOIN -- LEFT JOIN para asegurar que todas las reserva_funcion sean consideradas
        Reserva_butaca RB ON RF.id_reserva_funcion = RB.id_reserva_funcion
    WHERE
        U.dni = ?
    GROUP BY
        P.titulo, F.fecha_hora, P.precio
    ORDER BY
        F.fecha_hora DESC;
";

// Prepara y ejecuta la consulta usando las funciones sqlsrv
$params = array(&$dni_usuario); // Parámetro para el DNI

$stmt = sqlsrv_query($conn, $sql_compras, $params);

if ($stmt === false) {
    error_log("Error al ejecutar la consulta de compras: " . print_r(sqlsrv_errors(), true));
    $historial_compras = []; // Asegura que el array esté vacío si la consulta falla
} else {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // Las columnas DATETIME se devuelven como objetos DateTime.
        // Formateamos la fecha y hora para la visualización.
        $fecha_hora_dt = $row['fecha_hora_funcion'];
        $row['fecha_compra'] = $fecha_hora_dt->format('Y-m-d'); // Extrae solo la fecha
        $row['hora_funcion'] = $fecha_hora_dt->format('H:i'); // Extrae solo la hora
        $historial_compras[] = $row;
    }
    sqlsrv_free_stmt($stmt); // Libera los recursos de la declaración
}

// Cierra la conexión a la base de datos al finalizar
sqlsrv_close($conn);

// =================================================================
//  FIN DE LA CONEXIÓN A LA BASE DE DATOS Y OBTENCIÓN DE DATOS
// =================================================================
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - ZynemaX+</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Estilos básicos para el historial de compras */
        .purchase-history-container {
            background-color: #333;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            color: #eee;
        }
        .purchase-history-container h2 {
            color: #ffb400;
            margin-bottom: 20px;
            text-align: center;
        }
        .purchase-item {
            background-color: #444;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        .purchase-item p {
            margin: 5px 0;
            flex: 1; /* Permite que los elementos crezcan y se encojan */
            min-width: 150px; /* Ancho mínimo antes de envolver */
        }
        .purchase-item strong {
            color: #ffb400;
        }
        .no-purchases {
            text-align: center;
            font-style: italic;
            color: #aaa;
        }
    </style>
</head>
<body>
    <div class="page-container">
        <header class="main-header">
            <a href="pelicula.php" class="logo">ZYNEMAX+</a>
            <nav class="main-nav">
                <a href="perfil.php" class="active">PERFIL</a>
                <a href="pelicula.php">PELÍCULAS</a>
                <a href="foro.php">FORO</a>
                <a href="logout.php" class="nav-button">LOGOUT</a>
            </nav>
        </header>
        <main>
            <section class="hero-section">
                <h1 class="page-title-overlay">Bienvenido, <?php echo htmlspecialchars($_SESSION['nombre']); ?></h1>
            </section>
            <div class="content-area">
                <div class="form-container">
                    <h2>Información de tu Perfil</h2>
                    <div class="selection-item">
                        <p><strong>DNI:</strong> <?php echo htmlspecialchars($_SESSION['dni']); ?></p>
                    </div>
                    <div class="selection-item">
                        <p><strong>Nombre:</strong> <?php echo htmlspecialchars($_SESSION['nombre']); ?></p>
                    </div>
                    <div class="selection-item">
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($_SESSION['email']); ?></p>
                    </div>
                    <div class="selection-item">
                        <p><strong>Tipo de Usuario:</strong> <?php echo htmlspecialchars(ucfirst($_SESSION['tipo_usuario'])); ?></p>
                    </div>
                </div>

                <div class="purchase-history-container">
                    <h2>Historial de Compras</h2>
                    <?php if (!empty($historial_compras)): ?>
                        <?php foreach ($historial_compras as $compra): ?>
                            <div class="purchase-item">
                                <p><strong>Película:</strong> <?php echo htmlspecialchars($compra['pelicula']); ?></p>
                                <p><strong>Fecha:</strong> <?php echo htmlspecialchars($compra['fecha_compra']); ?></p>
                                <p><strong>Hora:</strong> <?php echo htmlspecialchars($compra['hora_funcion']); ?></p>
                                <p><strong>Entradas:</strong> <?php echo htmlspecialchars($compra['cantidad_entradas']); ?></p>
                                <p><strong>Total:</strong> S/.<?php echo number_format($compra['total_pagado'], 2); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="no-purchases">No tienes compras registradas aún.</p>
                    <?php endif; ?>
                </div>
                </div>
        </main>
    </div>
    <footer class="main-footer">
        <p>© 2025 Zynemax+ | Todos los derechos reservados</p>
    </footer>
</body>
</html>
