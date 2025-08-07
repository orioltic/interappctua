<?php
// obtener_preguntas.php
require_once 'config.php';
header('Content-Type: application/json');

try {
    // Verificar parámetros
    if (!isset($_GET['id_evento']) || !isset($_GET['id_expositor'])) {
        throw new Exception("Parámetros incompletos.");
    }

    $id_evento = (int)$_GET['id_evento'];
    $id_expositor = (int)$_GET['id_expositor'];
    // En una implementación real, $id_usuario_actual vendría de la sesión/cookie
    $id_usuario_actual = isset($_COOKIE['usuario_id']) ? (int)$_COOKIE['usuario_id'] : 0;

    // Obtener preguntas para el expositor actual
    $sql = "SELECT p.*, 
                   u_dest.nombre as nombre_destinatario, 
                   u_dest.apellidos as apellidos_destinatario,
                   u_autor.nombre as nombre_autor, 
                   u_autor.apellidos as apellidos_autor,
                   u_autor.id as id_autor_real  -- Añadimos el ID real del autor
            FROM preguntas p
            JOIN usuarios u_dest ON p.id_destinatario = u_dest.id
            JOIN usuarios u_autor ON p.id_autor = u_autor.id
            WHERE p.id_evento = ? AND p.id_destinatario = ?
            ORDER BY p.fecha_creacion ASC";
    
    $stmt = $conexion->prepare($sql);
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . $conexion->error);
    }
    $stmt->bind_param("ii", $id_evento, $id_expositor);
    $stmt->execute();
    $resultado = $stmt->get_result();

    $preguntas = [];
    
    while ($row = $resultado->fetch_assoc()) {
        // Verificar si el usuario actual ha votado esta pregunta
        $sql_voto = "SELECT id FROM votos_pregunta WHERE id_pregunta = ? AND id_votante = ?";
        $stmt_voto = $conexion->prepare($sql_voto);
        $stmt_voto->bind_param("ii", $row['id'], $id_usuario_actual);
        $stmt_voto->execute();
        $resultado_voto = $stmt_voto->get_result();
        $usuario_ha_votado = ($resultado_voto->num_rows > 0);
        $stmt_voto->close();
        
        // Verificar si el usuario actual es el autor de la pregunta
        $es_propia = ($row['id_autor_real'] == $id_usuario_actual);
        
        $preguntas[] = [
            'id' => (int)$row['id'],
            'texto' => $row['texto'],
            'votos' => (int)$row['votos'],
            'editable' => (bool)$row['editable'],
            'nombre_destinatario' => $row['nombre_destinatario'],
            'apellidos_destinatario' => $row['apellidos_destinatario'],
            'nombre_autor' => $row['nombre_autor'],
            'apellidos_autor' => $row['apellidos_autor'],
            'id_autor' => (int)$row['id_autor_real'],  // ID real del autor
            'es_propia' => $es_propia,  // Indicador si es pregunta del usuario actual
            'usuario_ha_votado' => $usuario_ha_votado  // Indicador si el usuario ya votó
        ];
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'preguntas' => $preguntas
    ]);

} catch (Exception $e) {
    error_log("Error en obtener_preguntas.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener las preguntas.'
    ]);
}
?>