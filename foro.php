<?php
ob_start();
session_start();

function makeApiRequest($url, $method = 'GET', $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    if ($method === 'POST' || $method === 'PUT') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['http_code' => $httpCode, 'response' => json_decode($response, true)];
}

$apiBaseUrl = 'https://rest-api-app-e7f4cfdzg0caf7c8.eastus-01.azurewebsites.net/api/reviews/';
$movieReviews = [];
$venueReviews = [];

$movieResponse = makeApiRequest($apiBaseUrl . 'pelicula');
if ($movieResponse['http_code'] === 200) { $movieReviews = $movieResponse['response']; }
$venueResponse = makeApiRequest($apiBaseUrl . 'sede');
if ($venueResponse['http_code'] === 200) { $venueReviews = $venueResponse['response']; }

if (isset($_POST['submit_review']) && isset($_SESSION['dni'])) {
    $type = $_POST['review_type'] ?? null;
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $comment = filter_input(INPUT_POST, 'comment', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    $rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);
    if ($type && $id && $comment && $rating && in_array($type, ['pelicula', 'sede']) && $rating >= 1 && $rating <= 5) {
        $data = ['dni' => (string)$_SESSION['dni'], 'nombre' => $_SESSION['nombre'], 'comentario' => $comment, 'puntuacion' => $rating, 'id' => (string)$id];
        $postUrl = 'https://rest-api-app-e7f4cfdzg0caf7c8.eastus-01.azurewebsites.net/api/review/' . $type;
        $response = makeApiRequest($postUrl, 'POST', $data);
        if ($response['http_code'] === 201) {
            header("Location: foro.php?success=1");
            exit();
        } else {
            header("Location: foro.php?error=1");
            exit();
        }
    } else {
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
    <title>Foro - ZynemaX+</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="page-container">
        <header class="main-header">
            <a href="<?php echo isset($_SESSION['dni']) ? 'pelicula.php' : 'index.php'; ?>" class="logo">ZYNEMAX+</a>
            <nav class="main-nav">
                <?php if (isset($_SESSION['dni'])): ?>
                    <a href="perfil.php">PERFIL</a>
                    <a href="pelicula.php">PELÍCULAS</a>
                    <a href="foro.php" class="active">FORO</a>
                    <a href="logout.php" class="nav-button">LOGOUT</a>
                <?php else: ?>
                    <a href="index.php">INICIO</a>
                    <a href="foro.php" class="active">FORO</a>
                    <a href="index.php" class="nav-button">INICIAR SESIÓN</a>
                <?php endif; ?>
            </nav>
        </header>
        <main>
            <section class="hero-section">
                <h1 class="page-title-overlay">Foro de Reseñas</h1>
            </section>
            <div class="content-area">
                <?php if (isset($_SESSION['dni'])): ?>
                    <div class="form-container">
                        <h2>Publicar una Reseña</h2>
                         <?php
                            $error = $_GET['error'] ?? 0;
                            if ($error == 1) echo "<p style='color:red;'>Error al enviar la reseña. Por favor intenta de nuevo.</p>";
                            if ($error == 2) echo "<p style='color:red;'>Faltan datos o los datos son inválidos.</p>";
                            if (isset($_GET['success'])) echo "<p style='color:green;'>Reseña enviada exitosamente.</p>";
                         ?>
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
                                <option value="1">1/5</option><option value="2">2/5</option><option value="3">3/5</option><option value="4">4/5</option><option value="5">5/5</option>
                            </select>
                            <button type="submit" name="submit_review">Enviar Reseña</button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="form-container" style="text-align:center;">
                        <h2>Participa en la Conversación</h2>
                        <p>Debes iniciar sesión para poder publicar y comentar reseñas.</p>
                        <a href="index.php" class="button" style="max-width: 250px; margin: 1rem auto;">Iniciar Sesión</a>
                    </div>
                <?php endif; ?>

                <h2 class="content-title" style="margin-top: 3rem;">Reseñas de Películas</h2>
                <div class="card-grid">
                    <?php if (!empty($movieReviews)): foreach ($movieReviews as $review): ?>
                    <div class="card">
                        <div class="card-content">
                            <h3>Película ID: <?php echo htmlspecialchars($review['id_pelicula'] ?? $review['id']); ?></h3>
                            <p><strong>Puntuación:</strong> <?php echo htmlspecialchars($review['puntuacion']); ?>/5</p>
                            <p><em>"<?php echo htmlspecialchars($review['comentario']); ?>"</em></p>
                            <p style="text-align: right; font-size: 0.8rem; margin-top: auto;">- <?php echo htmlspecialchars($review['nombre']); ?></p>
                        </div>
                    </div>
                    <?php endforeach; else: ?>
                        <p>No hay reseñas de películas disponibles.</p>
                    <?php endif; ?>
                </div>

                <h2 class="content-title" style="margin-top: 3rem;">Reseñas de Sedes</h2>
                <div class="card-grid">
                    <?php if (!empty($venueReviews)): foreach ($venueReviews as $review): ?>
                    <div class="card">
                        <div class="card-content">
                            <h3>Sede ID: <?php echo htmlspecialchars($review['id_sede'] ?? $review['id']); ?></h3>
                            <p><strong>Puntuación:</strong> <?php echo htmlspecialchars($review['puntuacion']); ?>/5</p>
                            <p><em>"<?php echo htmlspecialchars($review['comentario']); ?>"</em></p>
                            <p style="text-align: right; font-size: 0.8rem; margin-top: auto;">- <?php echo htmlspecialchars($review['nombre']); ?></p>
                        </div>
                    </div>
                    <?php endforeach; else: ?>
                        <p>No hay reseñas de sedes disponibles.</p>
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
<?php ob_end_flush(); ?>
