<?php
// toggle_like.php
require_once 'config.php';
header('Content-Type: application/json');

try {
    // Verificar método POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método no permitido.");
    }

    // Obtener datos del POST
    $id_pregunta = isset($_POST['id_pregunta']) ? (int)$_POST['id_pregunta'] : 0;
    $id_usuario = isset($_COOKIE['usuario_id']) ? (int)$_COOKIE['usuario_id'] : 0; // En una implementación real, esto vendría de la sesión

    // Validaciones
    if ($id_pregunta <= 0 || $id_usuario <= 0) {
        throw new Exception("Datos incompletos.");
    }

    // Verificar si el usuario es el autor de la pregunta (evitar auto-votación)
    $sql_check_autor = "SELECT id_autor FROM preguntas WHERE id = ?";
    $stmt_check_autor = $conexion->prepare($sql_check_autor);
    if (!$stmt_check_autor) {
        throw new Exception("Error al preparar la consulta de verificación de autor: " . $conexion->error);
    }
    $stmt_check_autor->bind_param("i", $id_pregunta);
    $stmt_check_autor->execute();
    $resultado_check_autor = $stmt_check_autor->get_result();
    
    if ($resultado_check_autor->num_rows === 0) {
        $stmt_check_autor->close();
        throw new Exception("Pregunta no encontrada.");
    }
    
    $pregunta = $resultado_check_autor->fetch_assoc();
    $stmt_check_autor->close();
    
    // Verificar que el usuario NO es el autor de la pregunta
    if ($pregunta['id_autor'] == $id_usuario) {
        throw new Exception("No puedes votar tu propia pregunta.");
    }

    // Verificar si el usuario ya ha votado esta pregunta
    $sql_check = "SELECT id FROM votos_pregunta WHERE id_pregunta = ? AND id_votante = ?";
    $stmt_check = $conexion->prepare($sql_check);
    if (!$stmt_check) {
        throw new Exception("Error al preparar la consulta de verificación: " . $conexion->error);
    }
    $stmt_check->bind_param("ii", $id_pregunta, $id_usuario);
    $stmt_check->execute();
    $resultado_check = $stmt_check->get_result();
    
    if ($resultado_check->num_rows > 0) {
        // Ya ha votado, eliminar el voto (quitar "me gusta")
        $voto = $resultado_check->fetch_assoc();
        $sql_delete = "DELETE FROM votos_pregunta WHERE id = ?";
        $stmt_delete = $conexion->prepare($sql_delete);
        if (!$stmt_delete) {
            throw new Exception("Error al preparar la consulta de eliminación: " . $conexion->error);
        }
        $stmt_delete->bind_param("i", $voto['id']);
        $stmt_delete->execute();
        $stmt_delete->close();
        
        // Actualizar contador de votos en la pregunta
        $sql_update = "UPDATE preguntas SET votos = votos - 1 WHERE id = ?";
        $stmt_update = $conexion->prepare($sql_update);
        if (!$stmt_update) {
            throw new Exception("Error al preparar la consulta de actualización (decremento): " . $conexion->error);
        }
        $stmt_update->bind_param("i", $id_pregunta);
        $stmt_update->execute();
        $stmt_update->close();
        
        $accion = 'eliminado';
    } else {
        // No ha votado, agregar el voto (dar "me gusta")
        $sql_insert = "INSERT INTO votos_pregunta (id_pregunta, id_votante) VALUES (?, ?)";
        $stmt_insert = $conexion->prepare($sql_insert);
        if (!$stmt_insert) {
            throw new Exception("Error al preparar la consulta de inserción: " . $conexion->error);
        }
        $stmt_insert->bind_param("ii", $id_pregunta, $id_usuario);
        $stmt_insert->execute();
        $stmt_insert->close();
        
        // Actualizar contador de votos en la pregunta
        $sql_update = "UPDATE preguntas SET votos = votos + 1 WHERE id = ?";
        $stmt_update = $conexion->prepare($sql_update);
        if (!$stmt_update) {
            throw new Exception("Error al preparar la consulta de actualización (incremento): " . $conexion->error);
        }
        $stmt_update->bind_param("i", $id_pregunta);
        $stmt_update->execute();
        $stmt_update->close();
        
        $accion = 'agregado';
    }
    
    $stmt_check->close();

    // Obtener el nuevo número de votos
    $sql_votos = "SELECT votos FROM preguntas WHERE id = ?";
    $stmt_votos = $conexion->prepare($sql_votos);
    if (!$stmt_votos) {
        throw new Exception("Error al preparar la consulta de votos: " . $conexion->error);
    }
    $stmt_votos->bind_param("i", $id_pregunta);
    $stmt_votos->execute();
    $resultado_votos = $stmt_votos->get_result();
    $nuevos_votos = $resultado_votos->fetch_assoc()['votos'];
    $stmt_votos->close();

    echo json_encode([
        'success' => true,
        'accion' => $accion,
        'nuevos_votos' => (int)$nuevos_votos
    ]);

} catch (Exception $e) {
    error_log("Error en toggle_like.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>