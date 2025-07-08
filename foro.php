<?php
ob_start(); // Iniciar el búfer de salida
header("Content-Type: text/html; charset=UTF-8");
session_start();

// Función para hacer solicitudes HTTP a la API
function makeApiRequest($url, $method = 'GET', $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 30-second timeout
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // 10-second connect timeout
    
    if ($method === 'POST' || $method === 'PUT') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    
    $response = curl_exec($ch);
    if ($response === false) {
        error_log("cURL Error: " . curl_error($ch));
    }
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ['http_code' => $httpCode, 'response' => json_decode($response, true)];
}

// Obtener reseñas de películas y sedes
$apiBaseUrl = 'https://rest-api-app-e7f4cfdzg0caf7c8.eastus-01.azurewebsites.net/api/reviews/';
$movieReviews = [];
$venueReviews = [];

$movieResponse = makeApiRequest($apiBaseUrl . 'peliculas');
if ($movieResponse['http_code'] === 200) {
    $movieReviews = $movieResponse['response'];
} else {
    error_log("Error al obtener reseñas de películas: " . print_r($movieResponse['response'], true));
}

$venueResponse = makeApiRequest($apiBaseUrl . 'sedes');
if ($venueResponse['http_code'] === 200) {
    $venueReviews = $venueResponse['response'];
} else {
    error_log("Error al obtener reseñas de sedes: " . print_r($venueResponse['response'], true));
}

// Procesar la creación de una nueva reseña
if (isset($_POST['submit_review']) && isset($_SESSION['dni'])) {
    $type = isset($_POST['review_type']) ? $_POST['review_type'] : null;
    $id = isset($_POST['id']) ? (int)$_POST['id'] : null;
    $comment = isset($_POST['comment']) ? substr($_POST['comment'], 0, 500) : null;
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : null;

    if ($type && $id && $comment && $rating && in_array($type, ['pelicula', 'sede']) && $rating >= 1 && $rating <= 5) {
        $data = [
            'dni' => (string)$_SESSION['dni'],
            'nombre' => $_SESSION['nombre'],
            'comentario' => $comment,
            'puntuacion' => $rating,
            'id' => $id,
            'timestamp' => date('c')
        ];
        
        $endpoint = ($type === 'pelicula') ? 'peliculas' : 'sedes';
        error_log("Sending POST to /api/reviews/$endpoint with data: " . print_r($data, true));
        $response = makeApiRequest($apiBaseUrl . $endpoint, 'POST', $data);
        
        error_log("API Response - HTTP Code: " . $response['http_code'] . ", Response: " . print_r($response['response'], true));
        
        if ($response['http_code'] === 201) {
            header("Location: foro.php?success=1");
            exit();
        } else {
            error_log("Error al enviar reseña: HTTP Code " . $response['http_code'] . ", Details: " . print_r($response['response'], true));
            header("Location: foro.php?error=1");
            exit();
        }
    } else {
        error_log("Invalid input: type=$type, id=$id, comment=$comment, rating=$rating");
        header("Location: foro.php?error=2");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zynemax+ | Foro</title>
    <link rel="stylesheet" href="style.css">
    <script src="scrip.js" defer></script>
</head>
<body>
    <header>
        <h1>Zynemax+ | Tu Cine Favorito</h1>
    </header>
    <nav>
        <?php if (!isset($_SESSION['dni'])): ?>
            <a href="#" onclick="showForm('login')">Login</a>
            <a href="#" onclick="showForm('register')">Register</a>
            <a href="foro.php">Foro</a>
        <?php else: ?>
            <a href="#" onclick="showForm('profile')">Perfil (<?php echo htmlspecialchars($_SESSION['nombre']); ?>)</a>
            <a href="pelicula.php">Películas</a>
            <a href="foro.php">Foro</a>
            <a href="logout.php">Logout</a>
        <?php endif; ?>
    </nav>
    <div class="container">
        <?php if (!isset($_SESSION['dni'])): ?>
            <div class="welcome-message">
                <h2>Bienvenido al Foro de Zynemax+</h2>
                <p>Inicia sesión para publicar tus propias reseñas.</p>
            </div>
        <?php else: ?>
            <div class="welcome-message">
                <h2>Bienvenido al Foro, <?php echo htmlspecialchars($_SESSION['nombre']); ?>!</h2>
            </div>
        <?php endif; ?>

        <?php
        $error = isset($_GET['error']) ? (int)$_GET['error'] : 0;
        $success = isset($_GET['success']) ? true : false;
        if ($error == 1) echo "<p style='color:red;'>Error al enviar la reseña. Por favor intenta de nuevo.</p>";
        if ($error == 2) echo "<p style='color:red;'>Faltan datos o los datos son inválidos.</p>";
        if ($success) echo "<p style='color:green;'>Reseña enviada exitosamente.</p>";
        ?>

        <!-- Formulario para crear reseñas -->
        <?php if (isset($_SESSION['dni'])): ?>
            <div class="form-container">
                <h2>Publicar una Reseña</h2>
                <form method="POST">
                    <select name="review_type" required>
                        <option value="">Selecciona el tipo de reseña</option>
                        <option value="pelicula">Película</option>
                        <option value="sede">Sede</option>
                    </select>
                    <input type="number" name="id" placeholder="ID de Película o Sede" required>
                    <textarea name="comment" placeholder="Tu comentario (máx. 500 caracteres)" required maxlength="500"></textarea>
                    <select name="rating" required>
                        <option value="">Selecciona una puntuación</option>
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                        <option value="5">5</option>
                    </select>
                    <button type="submit" name="submit_review">Enviar Reseña</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Mostrar reseñas de películas -->
        <div class="section">
            <h2>Reseñas de Películas</h2>
            <?php if (!empty($movieReviews)): ?>
                <div class="movie-grid">
                    <?php foreach ($movieReviews as $review): ?>
                        <div class="movie-card">
                            <div class="movie-info">
                                <h3>Película ID: <?php echo htmlspecialchars($review['id_pelicula'] ?? $review['id']); ?></h3>
                                <p><strong>Usuario:</strong> <?php echo htmlspecialchars($review['nombre']); ?> (DNI: <?php echo htmlspecialchars($review['dni']); ?>)</p>
                                <p><strong>Comentario:</strong> <?php echo htmlspecialchars($review['comentario']); ?></p>
                                <p><strong>Puntuación:</strong> <?php echo htmlspecialchars($review['puntuacion']); ?>/5</p>
                                <p><strong>Fecha:</strong> <?php echo htmlspecialchars($review['timestamp']); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>No hay reseñas de películas disponibles.</p>
            <?php endif; ?>
        </div>

        <!-- Mostrar reseñas de sedes -->
        <div class="section">
            <h2>Reseñas de Sedes</h2>
            <?php if (!empty($venueReviews)): ?>
                <div class="movie-grid">
                    <?php foreach ($venueReviews as $review): ?>
                        <div class="movie-card">
                            <div class="movie-info">
                                <h3>Sede ID: <?php echo htmlspecialchars($review['id_sede'] ?? $review['id']); ?></h3>
                                <p><strong>Usuario:</strong> <?php echo htmlspecialchars($review['nombre']); ?> (DNI: <?php echo htmlspecialchars($review['dni']); ?>)</p>
                                <p><strong>Comentario:</strong> <?php echo htmlspecialchars($review['comentario']); ?></p>
                                <p><strong>Puntuación:</strong> <?php echo htmlspecialchars($review['puntuacion']); ?>/5</p>
                                <p><strong>Fecha:</strong> <?php echo htmlspecialchars($review['timestamp']); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>No hay reseñas de sedes disponibles.</p>
            <?php endif; ?>
        </div>
    </div>
    <footer>
        <p>© 2025 Zynemax+ | Todos los derechos reservados</p>
    </footer>
</body>
</html>
<?php ob_end_flush(); ?>
