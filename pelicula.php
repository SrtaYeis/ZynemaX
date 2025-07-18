<?php
ob_start();
session_start();

if (!isset($_SESSION['dni'])) {
    header("Location: index.php");
    exit();
}

$serverName = "database-zynemaxplus-server.database.windows.net";
$connectionInfo = ["Database" => "database-zynemaxplus-server", "UID" => "zynemaxplus", "PWD" => "grupo2_1al10", "Encrypt" => true, "TrustServerCertificate" => false];
$conn = sqlsrv_connect($serverName, $connectionInfo);
if ($conn === false) { die("Error de conexión."); }

// Manejo de POST para selección de película
if (isset($_POST['select_movie'])) {
    $movie_id = isset($_POST['movie_id']) ? (int)$_POST['movie_id'] : null;
    if ($movie_id) { 
        $_SESSION['selected_movie'] = $movie_id; 
        header("Location: pelicula.php?step=sede"); 
        exit(); 
    } else { 
        header("Location: pelicula.php"); 
        exit(); 
    }
}

// Manejo de POST para selección de sede
if (isset($_POST['select_sede'])) {
    $sede_id = isset($_POST['sede_id']) ? (int)$_POST['sede_id'] : null;
    if ($sede_id) { 
        $_SESSION['selected_sede'] = $sede_id; 
        header("Location: pelicula.php?step=sala"); 
        exit(); 
    } else { 
        header("Location: pelicula.php?step=movies"); 
        exit(); 
    }
}

// Manejo de POST para selección de sala y función
if (isset($_POST['select_sala'])) {
    $sala_id = isset($_POST['sala_id']) ? (int)$_POST['sala_id'] : null;
    $funcion_id = isset($_POST['funcion_id']) ? (int)$_POST['funcion_id'] : null;
    $sala_name = isset($_POST['sala_name']) ? $_POST['sala_name'] : '';
    if ($sala_id && $funcion_id && $sala_name) {
        $dni_usuario = $_SESSION['dni'];
        $fecha_reserva = date('Y-m-d H:i:s');
        $sql = "INSERT INTO Reserva (dni_usuario, fecha_reserva) VALUES (?, ?); SELECT SCOPE_IDENTITY() AS id;";
        $params = [$dni_usuario, $fecha_reserva];
        $stmt = sqlsrv_query($conn, $sql, $params);
        sqlsrv_next_result($stmt);
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        $id_reserva = $row['id'];
        
        $sql = "INSERT INTO Reserva_funcion (id_reserva, id_funcion) VALUES (?, ?); SELECT SCOPE_IDENTITY() AS id;";
        $params = [$id_reserva, $funcion_id];
        $stmt = sqlsrv_query($conn, $sql, $params);
        sqlsrv_next_result($stmt);
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        $id_reserva_funcion = $row['id'];

        $_SESSION['selected_sala'] = $sala_id;
        $_SESSION['function_id'] = $funcion_id;
        $_SESSION['sala_name'] = $sala_name;
        $_SESSION['id_reserva'] = $id_reserva;
        $_SESSION['id_reserva_funcion'] = $id_reserva_funcion;
        header("Location: pelicula.php?step=butaca");
        exit();
    } else { 
        header("Location: pelicula.php?step=sede"); 
        exit(); 
    }
}

// Manejo de POST para selección de múltiples butacas
if (isset($_POST['select_butaca'])) {
    $butaca_ids = isset($_POST['butaca_ids']) && is_array($_POST['butaca_ids']) ? array_map('intval', $_POST['butaca_ids']) : [];
    if (!empty($butaca_ids) && isset($_SESSION['id_reserva_funcion'])) {
        // Iniciar transacción para evitar reservas dobles
        sqlsrv_begin_transaction($conn);
        try {
            // Verificar que todas las butacas seleccionadas estén disponibles
            $placeholders = implode(',', array_fill(0, count($butaca_ids), '?'));
            $sql_check = "SELECT id_butaca FROM Reserva_butaca rb 
                          JOIN Reserva_funcion rf ON rb.id_reserva_funcion = rf.id_reserva_funcion 
                          WHERE rf.id_funcion = ? AND rb.id_butaca IN ($placeholders)";
            $params = array_merge([$_SESSION['function_id']], $butaca_ids);
            $stmt_check = sqlsrv_query($conn, $sql_check, $params);
            $occupied_seats = [];
            while ($row = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC)) {
                $occupied_seats[] = $row['id_butaca'];
            }
            if (!empty($occupied_seats)) {
                sqlsrv_rollback($conn);
                echo "<script>
                    alert('Una o más butacas seleccionadas ya están reservadas. Por favor, selecciona otras.');
                    history.back();
                </script>";
                exit;
            }

            // Insertar cada butaca en Reserva_butaca
            $sql_insert = "INSERT INTO Reserva_butaca (id_reserva_funcion, id_butaca) VALUES (?, ?)";
            foreach ($butaca_ids as $butaca_id) {
                $params = [$_SESSION['id_reserva_funcion'], $butaca_id];
                if (!sqlsrv_query($conn, $sql_insert, $params)) {
                    sqlsrv_rollback($conn);
                    die("Error al reservar butacas.");
                }
            }

            // Confirmar transacción
            sqlsrv_commit($conn);
            $_SESSION['selected_butacas'] = $butaca_ids; // Guardar array de butacas seleccionadas
            header("Location: pelicula.php?step=summary");
            exit();
        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            die("Error en la transacción: " . $e->getMessage());
        }
    } else {
        header("Location: pelicula.php?step=sala");
        exit();
    }
}

// Manejo de POST para confirmar compra
if (isset($_POST['confirm_purchase'])) {
    $id_reserva_funcion = $_SESSION['id_reserva_funcion'];
    $fecha_pago = date('Y-m-d H:i:s');
    $sql = "INSERT INTO Pago (id_reserva_funcion, metodo_pago, fecha_pago, estado_pago) VALUES (?, 'efectivo', ?, 'completado'); SELECT SCOPE_IDENTITY() AS id;";
    $params = [$id_reserva_funcion, $fecha_pago];
    $stmt = sqlsrv_query($conn, $sql, $params);
    sqlsrv_next_result($stmt);
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $_SESSION['id_pago'] = $row['id'];
    header("Location: pelicula.php?step=receipt");
    exit();
}

$step = $_GET['step'] ?? 'movies'; 
$title_map = [
    'movies' => 'Selecciona una Película', 
    'sede' => 'Selecciona una Sede',
    'sala' => 'Selecciona una Sala', 
    'butaca' => 'Selecciona una Butaca',
    'summary' => 'Resumen de tu Compra', 
    'receipt' => 'Comprobante de Pago'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title_map[$step] ?? 'Proceso de Compra'; ?> - ZynemaX+</title>
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
                <h1 class="page-title-overlay"><?php echo $title_map[$step] ?? 'Proceso de Compra'; ?></h1>
            </section>
            <div class="content-area">
                <?php if ($step === 'movies'): ?>
                    <div class="card-grid">
                        <?php
                        $sql = "SELECT id_pelicula, titulo, duracion, clasificacion, fecha_estreno, precio FROM Pelicula ORDER BY fecha_estreno DESC";
                        $stmt = sqlsrv_query($conn, $sql);
                        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)):
                        ?>
                        <div class="card" id="movie-<?php echo $row['id_pelicula']; ?>">
                            <div class="card-image-placeholder"></div>
                            <div class="card-content">
                                <h3><?php echo htmlspecialchars($row['titulo']); ?></h3>
                                <p><strong>Duración:</strong> <?php echo $row['duracion']; ?> min</p>
                                <p><strong>Clasificación:</strong> <?php echo htmlspecialchars($row['clasificacion']); ?></p>
                                <p><strong>Precio:</strong> S/ <?php echo number_format($row['precio'], 2); ?></p>
                                <form method="POST" action="pelicula.php" style="margin-top: auto;">
                                    <input type="hidden" name="movie_id" value="<?php echo $row['id_pelicula']; ?>">
                                    <button type="submit" name="select_movie" class="button">Seleccionar</button>
                                </form>
                            </div>
                        </div>
                        <?php endwhile; sqlsrv_free_stmt($stmt); ?>
                    </div>

                <?php elseif ($step === 'sede'): ?>
                    <div class="form-container">
                        <?php
                        $sql = "SELECT DISTINCT s.id_sede, s.ciudad_sede, s.direccion_sede FROM Sede s JOIN Sala sa ON s.id_sede = sa.id_sede JOIN Funcion f ON sa.id_sala = f.id_sala WHERE f.id_pelicula = ?";
                        $stmt = sqlsrv_query($conn, $sql, [$_SESSION['selected_movie']]);
                        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): ?>
                            <div class="selection-item">
                                <p><strong>Ciudad:</strong> <?php echo htmlspecialchars($row['ciudad_sede']); ?></p>
                                <p><strong>Dirección:</strong> <?php echo htmlspecialchars($row['direccion_sede']); ?></p>
                                <form method="POST">
                                    <input type="hidden" name="sede_id" value="<?php echo $row['id_sede']; ?>">
                                    <button type="submit" name="select_sede">Seleccionar</button>
                                </form>
                            </div>
                        <?php endwhile; sqlsrv_free_stmt($stmt); ?>
                    </div>

                <?php elseif ($step === 'sala'): ?>
                    <div class="form-container">
                        <?php
                        $sql = "SELECT s.id_sala, s.nombre_sala, f.id_funcion, f.fecha_hora FROM Sala s JOIN Funcion f ON s.id_sala = f.id_sala WHERE s.id_sede = ? AND f.id_pelicula = ?";
                        $stmt = sqlsrv_query($conn, $sql, [$_SESSION['selected_sede'], $_SESSION['selected_movie']]);
                        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): ?>
                            <div class="selection-item">
                                <p><strong>Sala:</strong> <?php echo htmlspecialchars($row['nombre_sala']); ?></p>
                                <p><strong>Fecha y Hora:</strong> <?php echo $row['fecha_hora']->format('Y-m-d H:i:s'); ?></p>
                                <form method="POST">
                                    <input type="hidden" name="sala_id" value="<?php echo $row['id_sala']; ?>">
                                    <input type="hidden" name="funcion_id" value="<?php echo $row['id_funcion']; ?>">
                                    <input type="hidden" name="sala_name" value="<?php echo htmlspecialchars($row['nombre_sala']); ?>">
                                    <button type="submit" name="select_sala">Seleccionar</button>
                                </form>
                            </div>
                        <?php endwhile; sqlsrv_free_stmt($stmt); ?>
                    </div>

                <?php elseif ($step === 'butaca'): 
                    $sql_price = "SELECT precio FROM Pelicula WHERE id_pelicula = ?";
                    $stmt_price = sqlsrv_query($conn, $sql_price, [$_SESSION['selected_movie']]);
                    $precio_por_butaca = sqlsrv_fetch_array($stmt_price, SQLSRV_FETCH_ASSOC)['precio'] ?? 0;
                ?>
                    <div class="seating-chart-container">
                        <div class="screen">PANTALLA</div>
                        <div id="selected-seat" style="color: var(--color-texto-claro); font-size: 1.2rem; margin-bottom: 1.5rem; text-align: center;">
                            Selecciona una o más butacas
                        </div>
                        <div id="total-price" style="color: var(--color-texto-claro); font-size: 1.2rem; margin-bottom: 1.5rem; text-align: center; font-weight: bold;">
                            Precio Total: S/ 0.00
                        </div>
                        <form method="POST" action="pelicula.php">
                            <div class="seat-grid">
                                <?php
                                // Obtener TODAS las butacas de la sala para saber la estructura
                                $sql_all = "SELECT id_butaca, fila, numero_butaca FROM Butaca WHERE id_sala = ? ORDER BY fila DESC, numero_butaca ASC";
                                $stmt_all = sqlsrv_query($conn, $sql_all, [$_SESSION['selected_sala']]);
                                $all_seats = [];
                                while ($row = sqlsrv_fetch_array($stmt_all, SQLSRV_FETCH_ASSOC)) {
                                    $all_seats[$row['fila']][$row['numero_butaca']] = $row['id_butaca'];
                                }
                                
                                // Obtener las butacas OCUPADAS
                                $sql_occupied = "SELECT rb.id_butaca FROM Reserva_butaca rb JOIN Reserva_funcion rf ON rb.id_reserva_funcion = rf.id_reserva_funcion WHERE rf.id_funcion = ?";
                                $stmt_occupied = sqlsrv_query($conn, $sql_occupied, [$_SESSION['function_id']]);
                                $occupied_seats_ids = [];
                                while ($row = sqlsrv_fetch_array($stmt_occupied, SQLSRV_FETCH_ASSOC)) {
                                    $occupied_seats_ids[] = $row['id_butaca'];
                                }
                                
                                // Dibujar la rejilla
                                foreach ($all_seats as $fila => $butacas):
                                ?>
                                <div class="seat-row">
                                    <div class="seat-row-label"><?php echo htmlspecialchars($fila); ?></div>
                                    <?php foreach ($butacas as $numero => $id_butaca): 
                                        $is_occupied = in_array($id_butaca, $occupied_seats_ids);
                                    ?>
                                        <label class="seat-button <?php echo $is_occupied ? 'occupied' : ''; ?>" data-fila="<?php echo htmlspecialchars($fila); ?>" data-numero="<?php echo $numero; ?>">
                                            <input type="checkbox" name="butaca_ids[]" value="<?php echo $id_butaca; ?>" class="seat-input" <?php echo $is_occupied ? 'disabled' : ''; ?>>
                                            <?php echo $numero; ?>
                                        </label>
                                    <?php endforeach; ?>
                                    <div class="seat-row-label"><?php echo htmlspecialchars($fila); ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="submit" name="select_butaca" class="button confirm-button" style="margin-top: 2rem;">Confirmar Butacas</button>
                        </form>
                    </div>

                <?php elseif ($step === 'summary'): ?>
                    <div class="form-container">
                        <?php
                        $sql_p = "SELECT titulo, precio FROM Pelicula WHERE id_pelicula = ?"; 
                        $stmt_p = sqlsrv_query($conn, $sql_p, [$_SESSION['selected_movie']]); 
                        $pelicula_data = sqlsrv_fetch_array($stmt_p, SQLSRV_FETCH_ASSOC);
                        $sql_s = "SELECT ciudad_sede FROM Sede WHERE id_sede = ?"; 
                        $stmt_s = sqlsrv_query($conn, $sql_s, [$_SESSION['selected_sede']]); 
                        $sede_data = sqlsrv_fetch_array($stmt_s, SQLSRV_FETCH_ASSOC);
                        $sql_f = "SELECT fecha_hora FROM Funcion WHERE id_funcion = ?"; 
                        $stmt_f = sqlsrv_query($conn, $sql_f, [$_SESSION['function_id']]); 
                        $funcion_data = sqlsrv_fetch_array($stmt_f, SQLSRV_FETCH_ASSOC);
                        $sql_b = "SELECT fila, numero_butaca FROM Butaca WHERE id_butaca IN (" . implode(',', array_fill(0, count($_SESSION['selected_butacas']), '?')) . ")";
                        $stmt_b = sqlsrv_query($conn, $sql_b, $_SESSION['selected_butacas']);
                        $butacas = [];
                        while ($row = sqlsrv_fetch_array($stmt_b, SQLSRV_FETCH_ASSOC)) {
                            $butacas[] = htmlspecialchars($row['fila'] . $row['numero_butaca']);
                        }
                        ?>
                        <p><strong>Usuario:</strong> <?php echo htmlspecialchars($_SESSION['nombre']); ?></p>
                        <p><strong>Película:</strong> <?php echo htmlspecialchars($pelicula_data['titulo']); ?></p>
                        <p><strong>Sede:</strong> <?php echo htmlspecialchars($sede_data['ciudad_sede']); ?></p>
                        <p><strong>Sala:</strong> <?php echo htmlspecialchars($_SESSION['sala_name']); ?></p>
                        <p><strong>Butacas:</strong> <?php echo implode('; ', $butacas); ?></p>
                        <p><strong>Fecha y Hora:</strong> <?php echo $funcion_data['fecha_hora']->format('Y-m-d H:i:s'); ?></p>
                        <p><strong>Precio Total:</strong> S/ <?php echo number_format($pelicula_data['precio'] * count($_SESSION['selected_butacas']), 2); ?></p>
                        <form method="POST"><button type="submit" name="confirm_purchase">Confirmar Compra</button></form>
                    </div>

                <?php elseif ($step === 'receipt'): ?>
                    <div class="form-container">
                        <?php
                        $sql = "SELECT u.nombre AS un, p.titulo AS pt, p.precio AS pp, s.ciudad_sede AS sc, sa.nombre_sala AS sn, f.fecha_hora AS ffh, pa.fecha_pago AS pfp, pa.metodo_pago AS pmp 
                                FROM Pago pa 
                                JOIN Reserva_funcion rf ON pa.id_reserva_funcion = rf.id_reserva_funcion 
                                JOIN Reserva r ON rf.id_reserva = r.id_reserva 
                                JOIN Usuario u ON r.dni_usuario = u.dni 
                                JOIN Funcion f ON rf.id_funcion = f.id_funcion 
                                JOIN Pelicula p ON f.id_pelicula = p.id_pelicula 
                                JOIN Sala sa ON f.id_sala = sa.id_sala 
                                JOIN Sede s ON sa.id_sede = s.id_sede 
                                WHERE pa.id_pago = ?";
                        $stmt = sqlsrv_query($conn, $sql, [$_SESSION['id_pago']]);
                        $comprobante = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                        
                        $sql_b = "SELECT fila, numero_butaca FROM Butaca WHERE id_butaca IN (" . implode(',', array_fill(0, count($_SESSION['selected_butacas']), '?')) . ")";
                        $stmt_b = sqlsrv_query($conn, $sql_b, $_SESSION['selected_butacas']);
                        $butacas = [];
                        while ($row = sqlsrv_fetch_array($stmt_b, SQLSRV_FETCH_ASSOC)) {
                            $butacas[] = htmlspecialchars($row['fila'] . $row['numero_butaca']);
                        }
                        ?>
                        <h3>Zynemax+ | Tu Cine Favorito</h3><hr>
                        <p><strong>Usuario:</strong> <?php echo htmlspecialchars($comprobante['un']); ?></p>
                        <p><strong>Película:</strong> <?php echo htmlspecialchars($comprobante['pt']); ?></p>
                        <p><strong>Sede:</strong> <?php echo htmlspecialchars($comprobante['sc']); ?></p>
                        <p><strong>Sala:</strong> <?php echo htmlspecialchars($comprobante['sn']); ?></p>
                        <p><strong>Butacas:</strong> <?php echo implode('; ', $butacas); ?></p>
                        <p><strong>Fecha y Hora de la Función:</strong> <?php echo $comprobante['ffh']->format('Y-m-d H:i:s'); ?></p>
                        <p><strong>Monto Pagado:</strong> S/ <?php echo number_format($comprobante['pp'] * count($_SESSION['selected_butacas']), 2); ?></p>
                        <p><strong>Método de Pago:</strong> <?php echo htmlspecialchars(ucfirst($comprobante['pmp'])); ?></p>
                        <p><strong>Fecha de Pago:</strong> <?php echo $comprobante['pfp']->format('Y-m-d H:i:s'); ?></p><hr>
                        <p>¡Gracias por tu compra en Zynemax+! Disfruta tu película.</p>
                        <a href='pelicula.php' class='button'>Volver al Inicio</a>
                        <?php
                        unset($_SESSION['selected_movie'], $_SESSION['selected_sede'], $_SESSION['selected_sala'], $_SESSION['sala_name'], $_SESSION['selected_butacas'], $_SESSION['function_id'], $_SESSION['id_reserva'], $_SESSION['id_reserva_funcion'], $_SESSION['id_pago']);
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <footer class="main-footer">
        <p>© 2025 Zynemax+ | Todos los derechos reservados</p>
    </footer>
    <?php if ($step === 'butaca'): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const seatButtons = document.querySelectorAll('.seat-button:not(.occupied)');
            const selectedSeatDisplay = document.getElementById('selected-seat');
            const totalPriceDisplay = document.getElementById('total-price');
            const maxSeats = 10;
            const precioPorButaca = <?php echo $precio_por_butaca; ?>;

            function updateSelectedSeats() {
                const selectedSeats = document.querySelectorAll('.seat-button.selected');
                const selectedCount = selectedSeats.length;
                const totalPrice = (selectedCount * precioPorButaca).toFixed(2);
                if (selectedCount === 0) {
                    selectedSeatDisplay.textContent = 'Selecciona una o más butacas';
                    totalPriceDisplay.textContent = 'Precio Total: S/ 0.00';
                } else {
                    const seatList = Array.from(selectedSeats).map(button => {
                        const fila = button.getAttribute('data-fila');
                        const numero = button.getAttribute('data-numero');
                        return `${fila}${numero}`;
                    });
                    selectedSeatDisplay.textContent = `Butacas seleccionadas: ${seatList.join('; ')}`;
                    totalPriceDisplay.textContent = `Precio Total: S/ ${totalPrice}`;
                }
            }

            seatButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const input = this.querySelector('.seat-input');
                    const selectedSeats = document.querySelectorAll('.seat-button.selected').length;

                    if (!input.checked && selectedSeats >= maxSeats) {
                        alert(`No puedes seleccionar más de ${maxSeats} butacas por compra.`);
                        return;
                    }

                    this.classList.toggle('selected');
                    input.checked = !input.checked;
                    updateSelectedSeats();
                });
            });

            const seatInputs = document.querySelectorAll('.seat-input:not([disabled])');
            seatInputs.forEach(input => {
                input.addEventListener('change', function() {
                    const label = input.closest('.seat-button');
                    if (input.checked && document.querySelectorAll('.seat-button.selected').length > maxSeats) {
                        input.checked = false;
                        alert(`No puedes seleccionar más de ${maxSeats} butacas por compra.`);
                        return;
                    }
                    label.classList.toggle('selected', input.checked);
                    updateSelectedSeats();
                });
            });
        });
    </script>
    <?php endif; ?>
    <?php sqlsrv_close($conn); ?>
</body>
</html>
<?php ob_end_flush(); ?>
