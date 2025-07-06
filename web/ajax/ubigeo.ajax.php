<?php
require_once '../../../api/models/connection.php';

header('Content-Type: application/json');

try {
    $link = Connection::connect();
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'getDepartamentos':
            $stmt = $link->query("SELECT DISTINCT departamento as id, departamento as nombre FROM ubigeo ORDER BY departamento");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'getProvincias':
            $departamento = $_GET['departamento'] ?? '';
            if (!$departamento) {
                throw new Exception('Departamento no especificado');
            }
            $stmt = $link->prepare("SELECT DISTINCT provincia as id, provincia as nombre FROM ubigeo WHERE departamento = ? ORDER BY provincia");
            $stmt->execute([$departamento]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'getDistritos':
            $provincia = $_GET['provincia'] ?? '';
            if (!$provincia) {
                throw new Exception('Provincia no especificada');
            }
            $stmt = $link->prepare("SELECT DISTINCT distrito as id, distrito as nombre FROM ubigeo WHERE provincia = ? ORDER BY distrito");
            $stmt->execute([$provincia]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        default:
            throw new Exception('Acción no válida');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
