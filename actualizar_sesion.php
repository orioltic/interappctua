<?php
// actualizar_sesion.php
require_once 'config.php';
header('Content-Type: application/json');

try {
    // Verificar parámetro
    if (!isset($_GET['id_evento'])) {
        throw new Exception("ID de evento no proporcionado.");
    }

    $id_evento = (int)$_GET['id_evento'];

    // --- CONSULTA CORREGIDA ---
    // Obtiene el turno ACTIVO (el primero no finalizado)
    // Si no hay no finalizados, no devuelve nada.
    $sql_turno_actual = "SELECT t.id, t.id_usuario, t.finalizado, t.next, u.nombre, u.apellidos 
                         FROM turnos t 
                         JOIN usuarios u ON t.id_usuario = u.id 
                         WHERE t.id_evento = ? AND t.finalizado = 0 
                         ORDER BY t.orden LIMIT 1";
    $stmt_turno_actual = $conexion->prepare($sql_turno_actual);
    $stmt_turno_actual->bind_param("i", $id_evento);
    $stmt_turno_actual->execute();
    $resultado_turno_actual = $stmt_turno_actual->get_result();
    
    if ($resultado_turno_actual->num_rows > 0) {
        $turno_actual = $resultado_turno_actual->fetch_assoc();
        $stmt_turno_actual->close();
        
        echo json_encode([
            'success' => true,
            'nuevo_turno' => true,
            'turno_actual' => [
                'id' => (int)$turno_actual['id'],
                'id_usuario' => (int)$turno_actual['id_usuario'],
                'finalizado' => (int)$turno_actual['finalizado'], // 0 o 1
                'next' => (int)$turno_actual['next'],             // 0 o 1
                'nombre' => $turno_actual['nombre'],
                'apellidos' => $turno_actual['apellidos']
            ]
        ]);
    } else {
        $stmt_turno_actual->close();
        
        echo json_encode([
            'success' => true,
            'nuevo_turno' => false
        ]);
    }

} catch (Exception $e) {
    error_log("Error en actualizar_sesion.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al actualizar la sesión.'
    ]);
}
?>