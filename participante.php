<?php
// participante.php
require_once 'config.php';

// Verificar si se proporcionó un código de evento
if (!isset($_GET['codigo'])) {
    die("Código de evento no proporcionado.");
}

$codigo_evento = $_GET['codigo'];

// Verificar si el evento existe
$sql = "SELECT id, nombre FROM eventos WHERE codigo_evento = ?";
$stmt = $conexion->prepare($sql);
if (!$stmt) {
    die("Error en la base de datos (consulta de evento).");
}
$stmt->bind_param("s", $codigo_evento);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows == 0) {
    die("Evento no encontrado.");
}

$evento = $resultado->fetch_assoc();
$id_evento = $evento['id'];
$stmt->close();

$errores = [];
$mensaje_exito = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recoger datos del formulario
    $nombre = trim($_POST['nombre'] ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');
    $tipo = $_POST['tipo'] ?? '';
    
    // Validaciones
    if (empty($nombre)) {
        $errores[] = "El nombre es obligatorio.";
    }
    
    if (empty($apellidos)) {
        $errores[] = "Los apellidos son obligatorios.";
    }
    
    if (!in_array($tipo, ['ponente', 'oyente'])) {
        $errores[] = "Tipo de participación no válido.";
    }
    
    // Si no hay errores, guardar en la base de datos
    if (empty($errores)) {
        $sql = "INSERT INTO usuarios (nombre, apellidos, tipo, id_evento) VALUES (?, ?, ?, ?)";
        $stmt = $conexion->prepare($sql);
        if (!$stmt) {
            $errores[] = "Error en la base de datos (preparación de inserción).";
        } else {
            $stmt->bind_param("sssi", $nombre, $apellidos, $tipo, $id_evento);
            
            if ($stmt->execute()) {
                // Obtener el ID del usuario recién creado
                $id_usuario = $stmt->insert_id;
                $stmt->close();
                
                // Establecer una cookie para identificar al usuario
                setcookie('usuario_id', $id_usuario, time() + (86400 * 30), "/"); // 30 días
                
                $mensaje_exito = "Registro completado correctamente. Bienvenido al evento: " . htmlspecialchars($evento['nombre']);
            } else {
                $errores[] = "Error al registrar: " . $stmt->error;
                $stmt->close();
            }
        }
    }
}
// Asegurarse de que no hay salida antes de este punto
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InterAPPctúa - Registro de Participante</title>
    <link rel="stylesheet" href="./css/style.css">
</head>
<body>
    <header>
        <h1>InterAPPctúa</h1>
        <p>Plataforma de Gamificación para Ponencias Interactivas</p>
    </header>
    
    <div class="container">
        <div class="card">
            <h2 class="card-title">Registro de Participante</h2>
            
            <div class="text-center mb-2">
                <h3>Evento: <?php echo htmlspecialchars($evento['nombre']); ?></h3>
            </div>
            
            <?php if (!empty($errores)): ?>
                <div class="alert alert-error">
                    <ul>
                        <?php foreach ($errores as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <!-- Mostrar formulario o mensaje de éxito -->
            <?php if (empty($mensaje_exito)): ?>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="nombre">Nombre:</label>
                        <input type="text" id="nombre" name="nombre" value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="apellidos">Apellidos:</label>
                        <input type="text" id="apellidos" name="apellidos" value="<?php echo isset($_POST['apellidos']) ? htmlspecialchars($_POST['apellidos']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Tipo de participación:</label>
                        <div class="mt-1">
                            <label>
                                <input type="radio" name="tipo" value="ponente" <?php echo (!isset($_POST['tipo']) || (isset($_POST['tipo']) && $_POST['tipo'] == 'ponente')) ? 'checked' : ''; ?>> 
                                Ponente (puedo exponer y preguntar)
                            </label>
                        </div>
                        <div class="mt-1">
                            <label>
                                <input type="radio" name="tipo" value="oyente" <?php echo (isset($_POST['tipo']) && $_POST['tipo'] == 'oyente') ? 'checked' : ''; ?>> 
                                Oyente (solo puedo preguntar)
                            </label>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-success btn-block">Registrarme</button>
                </form>
            <?php endif; ?>
            
            <!-- Mostrar mensaje de éxito o pantalla de espera -->
            <?php if (!empty($mensaje_exito)): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($mensaje_exito); ?>
                </div>
            <?php endif; ?>
            
            <!-- Sección de espera que siempre se muestra -->
            <div class="text-center mt-2">
                <p>Esperando a que el administrador inicie el evento...</p>
                <div class="timer" id="waiting-timer">--:--</div>
            </div>
        </div>
    </div>
    
    <footer>
        <div class="container">
            <p>InterAPPctúa - Plataforma de Gamificación por Oriol Borrás-Gené (@OriolTIC)</p>
        </div>
    </footer>

    <script>
        // Verificar periódicamente si el evento HA INICIADO REALMENTE 
        function verificarInicioEvento() {
            fetch('verificar_inicio_evento.php?id_evento=<?php echo $id_evento; ?>')
                .then(response => response.json())
                .then(data => {
                    // data.iniciado será true SOLO si hay turnos creados y al menos uno está activo (no finalizado)
                    if (data.iniciado) {
                        // Redirigir a la sesión del participante
                        window.location.href = 'sesion_participante.php?codigo=<?php echo urlencode($codigo_evento); ?>';
                    }
                })
                .catch(error => {
                    console.error('Error al verificar inicio del evento:', error);
                });
        }
        
        // Iniciar la verificación inmediatamente
        verificarInicioEvento();
        
        // Verificar cada 5 segundos
        setInterval(verificarInicioEvento, 5000);
    </script>
</body>
</html>