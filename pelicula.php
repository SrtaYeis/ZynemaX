<?php
ob_start();
session_start();

// Si el usuario no está logueado, redirigir al index
if (!isset($_SESSION['dni'])) {
    header("Location: index.php");
    exit();
}

// Conexión a la base de datos
$serverName = "database-zynemaxplus-server.database.windows.net";
$connectionInfo = [
    "Database" => "database-zynemaxplus-server",
    "UID" => "zynemaxplus",
    "PWD" => "grupo2_1al10",
    "Encrypt" => true,
    "TrustServerCertificate" => false
];
$conn = sqlsrv_connect($serverName, $connectionInfo);

if ($conn === false) {
    die("Error de conexión: " . print_r(sqlsrv_errors(), true));
}

// Consultar todas las películas
$sql = "SELECT id_pelicula, titulo, duracion, clasificacion, fecha_estreno, precio FROM Pelicula ORDER BY fecha_estreno DESC";
$stmt = sqlsrv_query($conn, $sql);

if ($stmt === false) {
    die("Error al consultar películas: " . print_r(sqlsrv_errors(), true));
}

// Guardar las películas en un array
$peliculas = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    // Es importante convertir la fecha a un objeto DateTime para poder formatearla
    if ($row['fecha_estreno'] instanceof DateTime) {
        $row['fecha_estreno_obj'] = $row['fecha_estreno'];
    } else {
        $row['fecha_estreno_obj'] = date_create_from_format('Y-m-d H:i:s.u', $row['fecha_estreno']);
    }
    $peliculas[] = $row;
}

sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selecciona una Película - ZynemaX+</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="page-container">
        <header class="main-header">
            <a href="pelicula.php" class="logo">ZYNEMAX+</a>
            <nav class="main-nav">
                <a href="perfil.php">PERFIL</a>
                <a href="pelicula.php" class="active">PELÍCULAS</a>
                <a href="foro.php">FORO</a>
                <a href="logout.php" class="nav-button">LOGOUT</a>
            </nav>
        </header>

        <main>
            <section class="hero-section">
                <h1 class="page-title-overlay">Selecciona una Película</h1>
            </section>

            <section class="content-area">
                <div class="card-grid">
                    <?php foreach ($peliculas as $pelicula): ?>
                        <div class="card" id="movie-<?php echo htmlspecialchars($pelicula['id_pelicula']); ?>">
                            <div class="card-image-placeholder"></div>
                            <div class="card-content">
                                <h3><?php echo htmlspecialchars($pelicula['titulo']); ?></h3>
                                <p><strong>Duración:</strong> <?php echo htmlspecialchars($pelicula['duracion']); ?> min</p>
                                <p><strong>Clasificación:</strong> <?php echo htmlspecialchars($pelicula['clasificacion']); ?></p>
                                <p><strong>Fecha Estreno:</strong> 
                                    <?php 
                                        if($pelicula['fecha_estreno_obj']) {
                                            echo $pelicula['fecha_estreno_obj']->format('d-m-Y');
                                        } else {
                                            echo 'N/A';
                                        }
                                    ?>
                                </p>
                                <p><strong>Precio:</strong> S/ <?php echo htmlspecialchars(number_format($pelicula['precio'], 2)); ?></p>
                                <a href="seleccionar_funcion.php?peliculaId=<?php echo htmlspecialchars($pelicula['id_pelicula']); ?>" class="button">Seleccionar</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </main>
    </div>

    <footer class="main-footer">
        <p>© 2025 Zynemax+ | Todos los derechos reservados</p>
    </footer>
</body>
</html>

<?php
ob_end_flush();
?>
