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
// Se enfoca en los detalles de la compra (pago y butacas)
$sql_compras = "
    SELECT
        P.titulo AS pelicula,
        PG.fecha_pago AS fecha_pago,        -- Fecha y hora del pago real
        COUNT(RB.id_butaca) AS cantidad_entradas,
        (COUNT(RB.id_butaca) * P.precio) AS total_pagado,
        -- STRING_AGG para combinar butacas únicas para esta Reserva_funcion
        STRING_AGG(CONCAT(B.fila, B.numero_butaca), ', ') WITHIN GROUP (ORDER BY B.fila, B.numero_butaca) AS butacas_compradas
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
    LEFT JOIN
        Reserva_butaca RB ON RF.id_reserva_funcion = RB.id_reserva_funcion
    LEFT JOIN
        Butaca B ON RB.id_butaca = B.id_butaca
    LEFT JOIN
        Pago PG ON RF.id_reserva_funcion = PG.id_reserva_funcion
    WHERE
        U.dni = ?
    GROUP BY
        RF.id_reserva_funcion, P.titulo, P.precio, PG.fecha_pago -- Agrupar por la reserva de función y todos los campos no agregados
    ORDER BY
        PG.fecha_pago DESC;
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
        $fecha_pago_dt = $row['fecha_pago'];

        // Asegúrate de que las fechas no sean nulas antes de formatear
        $row['fecha_pago_formatted'] = $fecha_pago_dt ? $fecha_pago_dt->format('Y-m-d H:i') : 'N/A';
        // 'fecha_hora_funcion' no se mostrará directamente.

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
        /* Estilos específicos para la sección de Historial de Compras */
        .purchase-history-container {
            background-color: var(--color-primario);
            padding: 30px;
            border-radius: 12px; /* Bordes más redondeados */
            margin-top: 40px;
            color: var(--color-fondo-principal); /* Color de texto general de la sección */
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.4); /* Sombra más pronunciada */
            width: 100%;
            max-width: 750px; /* Un poco más ancho para dar espacio */
            margin-left: auto;
            margin-right: auto;
            border: 2px solid var(--color-primario); /* Borde primario para destacar */
        }
        .purchase-history-container h2 {
            color: #F5F0E6; /* Título en color primario (rojo/vino) */
            margin-bottom: 30px;
            text-align: center;
            font-size: 2.5em; /* Título más grande */
            padding-bottom: 15px;
            border-bottom: 3px solid var(--color-primario); /* Línea inferior más gruesa */
            text-transform: uppercase;
            letter-spacing: 2px; /* Mayor espacio entre letras */
            font-weight: 700; /* Más negrita */
        }
        .purchase-item {
            background-color: #3d3d3d; /* Fondo más oscuro para cada item, contrastando con el contenedor */
            padding: 22px;
            border-radius: 10px; /* Bordes redondeados para cada tarjeta de compra */
            margin-bottom: 25px; /* Más espacio entre items */
            display: flex;
            flex-direction: column;
            gap: 12px; /* Más espacio entre los detalles */
            border: 1px solid #666; /* Borde sutil para cada item */
            transition: all 0.3s ease;
            position: relative; /* Para posibles elementos decorativos */
        }
        .purchase-item:hover {
            background-color: #d39394; /* Ligeramente más claro al pasar el ratón */
            transform: translateY(-5px); /* Efecto de elevación más notorio */
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.5); /* Sombra más fuerte al pasar el ratón */
        }
        .purchase-item:last-child {
            margin-bottom: 0;
        }
        .purchase-item p {
            margin: 0;
            color: var(--color-texto-oscuro); /* Texto principal de los detalles */
            font-size: 1.1em;
            line-height: 1.5;
            display: flex;
            justify-content: space-between;
            align-items: center; /* Alinea verticalmente los items */
            flex-wrap: wrap; /* Permite que los elementos se envuelvan si el espacio es limitado */
        }
        .purchase-item strong {
            color: #FFFFFF; /* Etiquetas en color primario */
            font-weight: bold;
            flex-shrink: 0;
            margin-right: 15px;
            min-width: 140px; /* Ancho mínimo para las etiquetas para una mejor alineación */
            text-align: left;
        }
        .purchase-item span.value {
            flex-grow: 1;
            text-align: right; /* Alinea el valor a la derecha */
            color: #f5f0e6; /* Un blanco puro para los valores */
            font-weight: 500;
        }
        .no-purchases {
            text-align: center;
            font-style: italic;
            color: #aaa;
            padding: 40px;
            font-size: 1.2em;
            background-color: #444; /* Un fondo oscuro para el mensaje de "no compras" */
            border-radius: 10px;
            border: 1px dashed var(--color-primario); /* Borde punteado para este mensaje */
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
                                <p><strong>Película:</strong> <span class="value"><?php echo htmlspecialchars($compra['pelicula']); ?></span></p>
                                <p><strong>Fecha de Compra:</strong> <span class="value"><?php echo htmlspecialchars($compra['fecha_pago_formatted']); ?></span></p>
                                <p><strong>Entradas:</strong> <span class="value"><?php echo htmlspecialchars($compra['cantidad_entradas']); ?></span></p>
                                <p><strong>Butacas:</strong> <span class="value"><?php echo htmlspecialchars($compra['butacas_compradas'] ?? 'N/A'); ?></span></p>
                                <p><strong>Total Pagado:</strong> <span class="value">S/.<?php echo number_format($compra['total_pagado'], 2); ?></span></p>
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
        <p>© 2025 Zynemax+ | Todos los derechos reservados | Dibujitos al mando</p>
    </footer>
</body>
</html>
