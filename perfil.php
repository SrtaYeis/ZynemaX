<?php
session_start();

// Si el usuario no está logueado, lo redirigimos a la página de inicio
if (!isset($_SESSION['dni'])) {
    header("Location: index.php");
    exit();
}

$serverName = "database-zynemaxplus-server.database.windows.net";
$connectionInfo = ["Database" => "database-zynemaxplus-server", "UID" => "zynemaxplus", "PWD" => "grupo2_1al10", "Encrypt" => true, "TrustServerCertificate" => false];
$conn = sqlsrv_connect($serverName, $connectionInfo);
if ($conn === false) { die("Error de conexión."); }

$dni_usuario = $_SESSION['dni'];
$historial_compras = [];

// Consulta para obtener el historial de compras del usuario
// Ajusta la consulta SQL según la estructura de tu base de datos
$sql_compras = "SELECT p.titulo AS pelicula, c.fecha_compra, c.hora_funcion, c.cantidad_entradas, c.total_pagado 
                FROM compras c
                JOIN peliculas p ON c.id_pelicula = p.id_pelicula
                WHERE c.dni_usuario = ?
                ORDER BY c.fecha_compra DESC";

if ($stmt = $conexion->prepare($sql_compras)) {
    $stmt->bind_param("s", $dni_usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $historial_compras[] = $row;
    }
    $stmt->close();
} else {
    // Manejar el error de la consulta preparada
    error_log("Error al preparar la consulta de compras: " . $conexion->error);
}

// Cerrar la conexión a la base de datos
$conexion->close();

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
            flex: 1; /* Allow items to grow and shrink */
            min-width: 150px; /* Minimum width before wrapping */
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
