<?php
ob_start();
header("Content-Type: text/html; charset=UTF-8");
session_start();

if (isset($_SESSION['dni'])) {
    header("Location: pelicula.php");
    exit();
}

$error_message = '';
$success_message = '';

if (isset($_GET['error'])) {
    $error_code = $_GET['error'];
    if ($error_code == 1) $error_message = "Error al registrarse. El DNI o correo ya podría existir.";
    if ($error_code == 2) $error_message = "Faltan datos en el formulario de registro.";
    if ($error_code == 3) $error_message = "DNI o contraseña incorrectos.";
    if ($error_code == 4) $error_message = "Usuario no encontrado.";
    if ($error_code == 5) $error_message = "Faltan datos para iniciar sesión.";
}
if (isset($_GET['register_success'])) {
    $success_message = "Registro exitoso. Por favor, inicia sesión.";
}

// Mantenemos la lógica de POST del archivo original
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $serverName = "database-zynemaxplus-server.database.windows.net";
    $connectionInfo = ["Database" => "database-zynemaxplus-server", "UID" => "zynemaxplus", "PWD" => "grupo2_1al10", "Encrypt" => true, "TrustServerCertificate" => false];
    $conn = sqlsrv_connect($serverName, $connectionInfo);

    if ($conn) {
        if (isset($_POST['register'])) {
            $dni = filter_input(INPUT_POST, 'dni', FILTER_VALIDATE_INT);
            $nombre = filter_input(INPUT_POST, 'nombre', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
            $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
            $contrasena = $_POST['contrasena'];
            
            if ($dni && $nombre && $email && !empty($contrasena)) {
                $hashed_password = password_hash($contrasena, PASSWORD_DEFAULT);
                $sql = "INSERT INTO Usuario (dni, nombre, email, contrasena, tipo_usuario) VALUES (?, ?, ?, ?, 'cliente')";
                $params = [$dni, $nombre, $email, $hashed_password];
                $stmt = sqlsrv_query($conn, $sql, $params);
                if ($stmt) {
                    header("Location: index.php?register_success=1");
                    exit();
                } else {
                    header("Location: index.php?error=1");
                    exit();
                }
            } else {
                header("Location: index.php?error=2");
                exit();
            }
        }
        if (isset($_POST['login'])) {
            $dni = filter_input(INPUT_POST, 'dni', FILTER_VALIDATE_INT);
            $contrasena = $_POST['contrasena'];
            if ($dni && !empty($contrasena)) {
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
                        header("Location: index.php?error=3");
                        exit();
                    }
                } else {
                    header("Location: index.php?error=4");
                    exit();
                }
            } else {
                header("Location: index.php?error=5");
                exit();
            }
        }
        sqlsrv_close($conn);
    }
}
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
                <a href="#" class="nav-button" onclick="event.preventDefault(); showTab('login');">INICIAR SESIÓN</a>
            </nav>
        </header>
        <main>
            <section class="hero-section">
                <div class="form-overlay">
                    <div class="form-tabs">
                        <button class="tab-link active" onclick="showTab('login')">Iniciar Sesión</button>
                        <button class="tab-link" onclick="showTab('register')">Regístrate</button>
                    </div>

                    <?php if ($error_message): ?>
                        <p style="color:red; text-align:center;"><?php echo htmlspecialchars($error_message); ?></p>
                    <?php endif; ?>
                    <?php if ($success_message): ?>
                        <p style="color:green; text-align:center;"><?php echo htmlspecialchars($success_message); ?></p>
                    <?php endif; ?>

                    <div id="login-form">
                        <form method="POST" action="index.php">
                            <label for="login-dni">DNI:</label>
                            <input type="number" id="login-dni" name="dni" placeholder="Tu DNI" required>
                            <label for="login-pass">Contraseña:</label>
                            <input type="password" id="login-pass" name="contrasena" placeholder="Tu contraseña" required>
                            <button type="submit" name="login">Iniciar Sesión</button>
                        </form>
                    </div>
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
                            <button type="submit" name="register">REGISTRARSE</button>
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
        function showTab(tabName) {
            document.getElementById('login-form').style.display = (tabName === 'login') ? 'block' : 'none';
            document.getElementById('register-form').style.display = (tabName === 'register') ? 'block' : 'none';
            document.querySelectorAll('.tab-link').forEach((tab, index) => {
                if ((index === 0 && tabName === 'login') || (index === 1 && tabName === 'register')) {
                    tab.classList.add('active');
                } else {
                    tab.classList.remove('active');
                }
            });
        }
        <?php if(isset($_POST['register']) || (isset($_GET['error']) && in_array($_GET['error'], [1,2]))) echo "showTab('register');"; ?>
        <?php if(isset($_GET['register_success'])) echo "showTab('login');"; ?>
    </script>
</body>
</html>
<?php
ob_end_flush();
?>
