<?php
// enviar_votacion.php
require_once 'config.php';
header('Content-Type: application/json');

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido.'
    ]);
    exit(); // Salir inmediatamente si no es POST
}

try {
    // Obtener datos del POST
    $id_evento = isset($_POST['id_evento']) ? (int)$_POST['id_evento'] : 0;
    $id_votante = isset($_POST['id_votante']) ? (int)$_POST['id_votante'] : 0; // En una implementación real, esto vendría de la sesión/cookie
    $id_ponente = isset($_POST['id_ponente']) ? (int)$_POST['id_ponente'] : 0;
    $votaciones_json = isset($_POST['votaciones']) ? $_POST['votaciones'] : '[]';

    // Validaciones
    if ($id_evento <= 0) {
        throw new Exception("ID de evento no válido.");
    }
    
    if ($id_votante <= 0) {
        throw new Exception("ID de votante no válido.");
    }
    
    if ($id_ponente <= 0) {
        throw new Exception("ID de ponente no válido.");
    }

    // Decodificar las votaciones
    $votaciones = json_decode($votaciones_json, true);
    if (!is_array($votaciones)) {
        throw new Exception("Formato de votaciones inválido.");
    }

    // Procesar cada votación
    foreach ($votaciones as $votacion) {
        $criterio = isset($votacion['criterio']) ? $votacion['criterio'] : '';
        $puntos = isset($votacion['puntos']) ? (int)$votacion['puntos'] : 0;
        
        // Validar puntos (debe ser 0, 10, 20, 30, 40, o 50)
        if (!in_array($puntos, [0, 10, 20, 30, 40, 50])) {
            throw new Exception("Puntos inválidos para el criterio: " . $criterio);
        }
        
        // Insertar o actualizar la votación en la base de datos
        // Primero verificamos si ya existe una votación para este criterio
        $sql_check = "SELECT id FROM votaciones_ponente WHERE id_evento = ? AND id_votante = ? AND id_ponente = ? AND criterio = ?";
        $stmt_check = $conexion->prepare($sql_check);
        if (!$stmt_check) {
            throw new Exception("Error al preparar la consulta de verificación: " . $conexion->error);
        }
        $stmt_check->bind_param("iiis", $id_evento, $id_votante, $id_ponente, $criterio);
        $stmt_check->execute();
        $resultado_check = $stmt_check->get_result();
        
        if ($resultado_check->num_rows > 0) {
            // Actualizar votación existente
            $voto = $resultado_check->fetch_assoc();
            $sql_update = "UPDATE votaciones_ponente SET puntos = ? WHERE id = ?";
            $stmt_update = $conexion->prepare($sql_update);
            if (!$stmt_update) {
                throw new Exception("Error al preparar la consulta de actualización: " . $conexion->error);
            }
            $stmt_update->bind_param("ii", $puntos, $voto['id']);
            $stmt_update->execute();
            $stmt_update->close();
        } else {
            // Insertar nueva votación
            $sql_insert = "INSERT INTO votaciones_ponente (id_evento, id_votante, id_ponente, criterio, puntos) VALUES (?, ?, ?, ?, ?)";
            $stmt_insert = $conexion->prepare($sql_insert);
            if (!$stmt_insert) {
                throw new Exception("Error al preparar la consulta de inserción: " . $conexion->error);
            }
            $stmt_insert->bind_param("iiissi", $id_evento, $id_votante, $id_ponente, $criterio, $puntos);
            $stmt_insert->execute();
            $stmt_insert->close();
        }
        
        $stmt_check->close();
        
        // También guardar en la tabla de puntuaciones (suma de todos los criterios)
        // Primero calculamos la suma total de puntos para este ponente
        $suma_puntos = 0;
        foreach ($votaciones as $v) {
            $suma_puntos += (int)$v['puntos'];
        }
        
        // Verificar si ya existe una puntuación de tipo 'votacion' para este usuario y evento
        $sql_check_puntos = "SELECT id FROM puntuaciones WHERE id_usuario = ? AND id_evento = ? AND tipo = 'votacion'";
        $stmt_check_puntos = $conexion->prepare($sql_check_puntos);
        if (!$stmt_check_puntos) {
            throw new Exception("Error al preparar la consulta de verificación de puntos: " . $conexion->error);
        }
        $stmt_check_puntos->bind_param("ii", $id_ponente, $id_evento);
        $stmt_check_puntos->execute();
        $resultado_check_puntos = $stmt_check_puntos->get_result();
        
        if ($resultado_check_puntos->num_rows > 0) {
            // Actualizar puntuación existente
            $puntuacion = $resultado_check_puntos->fetch_assoc();
            $sql_update_puntos = "UPDATE puntuaciones SET puntos = ? WHERE id = ?";
            $stmt_update_puntos = $conexion->prepare($sql_update_puntos);
            if (!$stmt_update_puntos) {
                throw new Exception("Error al preparar la consulta de actualización de puntos: " . $conexion->error);
            }
            $stmt_update_puntos->bind_param("ii", $suma_puntos, $puntuacion['id']);
            $stmt_update_puntos->execute();
            $stmt_update_puntos->close();
        } else {
            // Insertar nueva puntuación
            $sql_insert_puntos = "INSERT INTO puntuaciones (id_usuario, id_evento, tipo, puntos) VALUES (?, ?, 'votacion', ?)";
            $stmt_insert_puntos = $conexion->prepare($sql_insert_puntos);
            if (!$stmt_insert_puntos) {
                throw new Exception("Error al preparar la consulta de inserción de puntos: " . $conexion->error);
            }
            $stmt_insert_puntos->bind_param("iii", $id_ponente, $id_evento, $suma_puntos);
            $stmt_insert_puntos->execute();
            $stmt_insert_puntos->close();
        }
        
        $stmt_check_puntos->close();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Votación enviada correctamente.'
    ]);

} catch (Exception $e) {
    error_log("Error en enviar_votacion.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>