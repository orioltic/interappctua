<?php
// editar_pregunta.php
require_once 'config.php';
header('Content-Type: application/json');

try {
    // Verificar método POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método no permitido.");
    }

    // Obtener datos del POST
    $id_pregunta = isset($_POST['id_pregunta']) ? (int)$_POST['id_pregunta'] : 0;
    $nuevo_texto = isset($_POST['nuevo_texto']) ? trim($_POST['nuevo_texto']) : '';
    // En una implementación real, $id_usuario vendría de la sesión/cookie
    $id_usuario = isset($_COOKIE['usuario_id']) ? (int)$_COOKIE['usuario_id'] : 0;

    // Validaciones
    if ($id_pregunta <= 0) {
        throw new Exception("ID de pregunta no válido.");
    }
    
    if (empty($nuevo_texto)) {
        throw new Exception("El texto de la pregunta no puede estar vacío.");
    }

    // Verificar que la pregunta pertenece al usuario y no tiene votos
    // NOTA: Usamos id_autor de la tabla preguntas, no el id del usuario que vota
    $sql_check = "SELECT id, id_autor, votos FROM preguntas WHERE id = ?";
    $stmt_check = $conexion->prepare($sql_check);
    if (!$stmt_check) {
        throw new Exception("Error al preparar la consulta de verificación: " . $conexion->error);
    }
    $stmt_check->bind_param("i", $id_pregunta);
    $stmt_check->execute();
    $resultado_check = $stmt_check->get_result();
    
    if ($resultado_check->num_rows === 0) {
        $stmt_check->close();
        throw new Exception("Pregunta no encontrada.");
    }
    
    $pregunta = $resultado_check->fetch_assoc();
    $stmt_check->close();
    
    // Verificar que el usuario es el autor de la pregunta
    if ($pregunta['id_autor'] != $id_usuario) {
        throw new Exception("No tienes permiso para editar esta pregunta.");
    }
    
    // Verificar que la pregunta no tiene votos
    if ($pregunta['votos'] > 0) {
        throw new Exception("No se puede editar una pregunta que ya tiene votos.");
    }

    // Actualizar el texto de la pregunta
    $sql_update = "UPDATE preguntas SET texto = ? WHERE id = ?";
    $stmt_update = $conexion->prepare($sql_update);
    if (!$stmt_update) {
        throw new Exception("Error al preparar la consulta de actualización: " . $conexion->error);
    }
    $stmt_update->bind_param("si", $nuevo_texto, $id_pregunta);
    
    if (!$stmt_update->execute()) {
        $stmt_update->close();
        throw new Exception("Error al actualizar la pregunta: " . $stmt_update->error);
    }
    
    $stmt_update->close();

    echo json_encode([
        'success' => true,
        'message' => 'Pregunta actualizada correctamente.'
    ]);

} catch (Exception $e) {
    error_log("Error en editar_pregunta.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>