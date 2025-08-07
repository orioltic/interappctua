<?php
// enviar_pregunta.php
require_once 'config.php';
header('Content-Type: application/json');

try {
    // Verificar método POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método no permitido.");
    }

    // Obtener datos del POST
    $id_evento = isset($_POST['id_evento']) ? (int)$_POST['id_evento'] : 0;
    $id_destinatario = isset($_POST['id_destinatario']) ? (int)$_POST['id_destinatario'] : 0;
    $id_autor = isset($_POST['id_autor']) ? (int)$_POST['id_autor'] : 0; // En una implementación real, esto vendría de la sesión
    $texto = isset($_POST['texto']) ? trim($_POST['texto']) : '';

    // Validaciones
    if ($id_evento <= 0 || $id_destinatario <= 0 || $id_autor <= 0) {
        throw new Exception("Datos incompletos.");
    }
    
    if (empty($texto)) {
        throw new Exception("El texto de la pregunta no puede estar vacío.");
    }

    // Insertar pregunta en la base de datos
    $sql = "INSERT INTO preguntas (id_evento, id_destinatario, id_autor, texto) VALUES (?, ?, ?, ?)";
    $stmt = $conexion->prepare($sql);
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . $conexion->error);
    }
    $stmt->bind_param("iiis", $id_evento, $id_destinatario, $id_autor, $texto);
    
    if (!$stmt->execute()) {
        throw new Exception("Error al insertar la pregunta: " . $stmt->error);
    }
    
    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Pregunta enviada correctamente.'
    ]);

} catch (Exception $e) {
    error_log("Error en enviar_pregunta.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>