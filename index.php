<?php
session_start();

if (isset($_SESSION['dni'])) {
    header("Location: pelicula.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $serverName = "database-zynemaxplus-server.database.windows.net";
    $connectionInfo = ["Database" => "database-zynemaxplus-server", "UID" => "zynemaxplus", "PWD" => "grupo2_1al10", "Encrypt" => true, "TrustServerCertificate" => false];
    $conn = sqlsrv_connect($serverName, $connectionInfo);

    if ($conn === false) {
        die("Error de conexión: " . print_r(sqlsrv_errors(), true));
    }

    $dni = $_POST['dni'];
    $contrasena = $_POST['contrasena'];

    $sql = "SELECT dni, nombre, email, tipo_usuario FROM Usuario WHERE dni = ? AND contrasena = ?";
    $params = array($dni, $contrasena);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        die("Error en la consulta: " . print_r(sqlsrv_errors(), true));
    }

    $user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

    if ($user) {
        $_SESSION['dni'] = $user['dni'];
        $_SESSION['nombre'] = $user['nombre'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['tipo_usuario'] = $user['tipo_usuario'];
        header("Location: pelicula.php");
        exit();
    } else {
        $error = "DNI o contraseña incorrectos.";
    }

    sqlsrv_close($conn);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - ZynemaX+</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="page-container">
        <header class="main-header">
            <a href="index.php" class="logo">ZYNEMAX+</a>
            <div class="hamburger">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </div>
            <nav class="main-nav">
                <a href="index.php" class="active">INICIO</a>
                <a href="foro.php">FORO</a>
                <a href="index.php#login-tab" class="nav-button">INICIAR SESIÓN</a>
                <a href="index.php#register-tab" class="nav-button">REGISTRATE</a>
            </nav>
        </header>
        <main>
            <section class="hero-section">
                <h1 class="page-title-overlay">Inicia Sesión o Regístrate</h1>
            </section>
            <div class="content-area">
                <div class="form-overlay">
                    <div class="form-tabs">
                        <a href="#login-tab" class="tab-link active" onclick="showTab('login')">Iniciar Sesión</a>
                        <a href="#register-tab" class="tab-link" onclick="showTab('register')">Registrate</a>
                    </div>
                    <div id="login-tab" class="form-tab active">
                        <?php if (isset($error)) echo "<p style='color: red;'>$error</p>"; ?>
                        <form method="POST" action="index.php">
                            <label for="dni">DNI:</label>
                            <input type="text" id="dni" name="dni" required>
                            <label for="contrasena">Contraseña:</label>
                            <input type="password" id="contrasena" name="contrasena" required>
                            <button type="submit" class="button">Iniciar Sesión</button>
                        </form>
                    </div>
                    <div id="register-tab" class="form-tab" style="display: none;">
                        <form method="POST" action="registro.php">
                            <label for="dni_reg">DNI:</label>
                            <input type="text" id="dni_reg" name="dni" required>
                            <label for="nombre_reg">Nombre:</label>
                            <input type="text" id="nombre_reg" name="nombre" required>
                            <label for="email_reg">Email:</label>
                            <input type="email" id="email_reg" name="email" required>
                            <label for="contrasena_reg">Contraseña:</label>
                            <input type="password" id="contrasena_reg" name="contrasena" required>
                            <button type="submit" class="button">Registrarse</button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <footer class="main-footer">
        <p>© 2025 Zynemax+ | Todos los derechos reservados | Dibujitos al mando</p>
    </footer>
    <script>
        function showTab(tabId) {
            const tabs = document.querySelectorAll('.form-tab');
            const links = document.querySelectorAll('.tab-link');
            tabs.forEach(tab => tab.style.display = 'none');
            links.forEach(link => link.classList.remove('active'));
            document.getElementById(tabId).style.display = 'block';
            document.querySelector(`[href="#${tabId}"]`).classList.add('active');
        }

        // JavaScript para el menú hamburguesa
        document.addEventListener('DOMContentLoaded', function() {
            const hamburger = document.querySelector('.hamburger');
            const navMenu = document.querySelector('.main-nav');

            hamburger.addEventListener('click', function() {
                navMenu.classList.toggle('active');
                hamburger.classList.toggle('active');
            });

            // Cerrar el menú si se hace clic fuera
            document.addEventListener('click', function(event) {
                if (!hamburger.contains(event.target) && !navMenu.contains(event.target)) {
                    navMenu.classList.remove('active');
                    hamburger.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>
