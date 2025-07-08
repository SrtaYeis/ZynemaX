<?php
ob_start(); // Iniciar el búfer de salida
header("Content-Type: text/html; charset=UTF-8");
session_start();

// Si el usuario ya está logueado, redirigir a pelicula.php
if (isset($_SESSION['dni'])) {
    header("Location: pelicula.php");
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
    error_log("Conexión fallida: " . print_r(sqlsrv_errors(), true));
    // Guardamos el error para mostrarlo después
    $error_message = "Error de conexión con la base de datos.";
}

$error_message = '';
$success_message = '';

// Procesar registro (solo cliente)
if (isset($_POST['register'])) {
    $dni = isset($_POST['dni']) ? (int)$_POST['dni'] : null;
    $nombre = isset($_POST['nombre']) ? substr($_POST['nombre'], 0, 50) : null;
    $email = isset($_POST['email']) ? substr($_POST['email'], 0, 50) : null;
    $contrasena = isset($_POST['contrasena']) ? password_hash($_POST['contrasena'], PASSWORD_DEFAULT) : null;
    $tipo_usuario = 'cliente';

    if ($dni && $nombre && $email && $contrasena) {
        $sql = "INSERT INTO Usuario (dni, nombre, email, contrasena, tipo_usuario) VALUES (?, ?, ?, ?, ?)";
        $params = [$dni, $nombre, $email, $contrasena, $tipo_usuario];
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            $error_message = "Error al registrarse. El DNI o correo ya podría existir.";
        } else {
            $success_message = "Registro exitoso. Por favor, inicia sesión.";
        }
        sqlsrv_free_stmt($stmt);
    } else {
        $error_message = "Faltan datos en el formulario de registro.";
    }
}

// Procesar login
if (isset($_POST['login'])) {
    $dni = isset($_POST['dni']) ? (int)$_POST['dni'] : null;
    $contrasena = isset($_POST['contrasena']) ? $_POST['contrasena'] : null;

    if ($dni && $contrasena) {
        $sql = "SELECT dni, nombre, email, contrasena, tipo_usuario FROM Usuario WHERE dni = ?";
        $params = [$dni];
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt && sqlsrv_has_rows($stmt)) {
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            if (password_verify($contrasena, $row['contrasena'])) {
                $_SESSION['dni'] = $row['dni'];
                $_SESSION['nombre'] = $row['nombre'];
                $_SESSION['email'] = $row['email'];
                $_SESSION['tipo_usuario'] = $row['tipo_usuario'];
                header("Location: pelicula.php");
                exit();
            } else {
                $error_message = "DNI o contraseña incorrectos.";
            }
        } else {
            $error_message = "DNI o contraseña incorrectos.";
        }
        sqlsrv_free_stmt($stmt);
    } else {
        $error_message = "Faltan datos para iniciar sesión.";
    }
}

if($conn) { sqlsrv_close($conn); }
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido a ZynemaX+</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="page-container">
        <header class="main-header">
            <a href="index.php" class="logo">ZYNEMAX+</a>
            <nav class="main-nav">
                <a href="index.php" class="active">INICIO</a>
                <a href="foro.php">FORO</a>
                <a href="#" class="nav-button" onclick="document.getElementById('login-form').style.display='block'; document.getElementById('register-form').style.display='none';">INICIAR SESIÓN</a>
            </nav>
        </header>
        <main>
            <section class="hero-section">
                <div class="form-overlay">
                    <div class="form-tabs">
                        <a href="#" class="tab-link active" onclick="showTab('login')">Iniciar Sesión</a>
                        <a href="#" class="tab-link" onclick="showTab('register')">Regístrate</a>
                    </div>

                    <?php if ($error_message): ?>
                        <p class="message error"><?php echo htmlspecialchars($error_message); ?></p>
                    <?php endif; ?>
                    <?php if ($success_message): ?>
                        <p class="message success"><?php echo htmlspecialchars($success_message); ?></p>
                    <?php endif; ?>

                    <!-- Formulario de Login -->
                    <div id="login-form">
                        <form method="POST" action="index.php">
                            <label for="login-dni">DNI:</label>
                            <input type="number" id="login-dni" name="dni" placeholder="Tu DNI" required>
                            <label for="login-pass">Contraseña:</label>
                            <input type="password" id="login-pass" name="contrasena" placeholder="Tu contraseña" required>
                            <button type="submit" name="login">Iniciar Sesión</button>
                        </form>
                    </div>

                    <!-- Formulario de Registro -->
                    <div id="register-form" style="display: none;">
                        <form method="POST" action="index.php">
                            <label for="reg-dni">DNI:</label>
                            <input type="number" id="reg-dni" name="dni" placeholder="Tu DNI" required>
                            <label for="reg-nombre">Nombre Completo:</label>
                            <input type="text" id="reg-nombre" name="nombre" placeholder="Tu nombre" required maxlength="50">
                            <label for="reg-email">Correo Electrónico:</label>
                            <input type="email" id="reg-email" name="email" placeholder="Tu correo" required maxlength="50">
                            <label for="reg-pass">Contraseña:</label>
                            <input type="password" id="reg-pass" name="contrasena" placeholder="Crea una contraseña" required>
                            <button type="submit" name="register">Registrarse</button>
                        </form>
                    </div>
                </div>
            </section>
            <section class="content-area">
                <h1 class="content-title">DISFRUTA DE EXPERIENCIAS SORPRENDENTES</h1>
            </section>
        </main>
    </div>
    <footer class="main-footer">
        <p>© 2025 Zynemax+ | Todos los derechos reservados</p>
    </footer>

    <script>
        // Pequeño script para manejar las pestañas de login/registro
        function showTab(tabName) {
            const loginForm = document.getElementById('login-form');
            const registerForm = document.getElementById('register-form');
            const tabs = document.querySelectorAll('.tab-link');

            if (tabName === 'login') {
                loginForm.style.display = 'block';
                registerForm.style.display = 'none';
                tabs[0].classList.add('active');
                tabs[1].classList.remove('active');
            } else {
                loginForm.style.display = 'none';
                registerForm.style.display = 'block';
                tabs[0].classList.remove('active');
                tabs[1].classList.add('active');
            }
        }
        <?php if(isset($_POST['register'])) echo "showTab('register');"; ?>
    </script>
</body>
</html>
<?php
ob_end_flush();
?>
