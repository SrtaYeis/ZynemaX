<?php
session_start();

// Si el usuario no está logueado, lo redirigimos a la página de inicio
if (!isset($_SESSION['dni'])) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - ZynemaX+</title>
    <link rel="stylesheet" href="style.css">
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
            </div>
        </main>
    </div>
    <footer class="main-footer">
        <p>© 2025 Zynemax+ | Todos los derechos reservados</p>
    </footer>
</body>
</html>
