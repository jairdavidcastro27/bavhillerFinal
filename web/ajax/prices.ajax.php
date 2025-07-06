<?php
require_once '../../../api/models/connection.php';

header('Content-Type: application/json');

try {
    $link = Connection::connect();
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'getLastPrice':
            $provider_id = $_GET['provider_id'] ?? '';
            $product_id = $_GET['product_id'] ?? '';

            if (!$provider_id || !$product_id) {
                throw new Exception('Proveedor y producto son requeridos');
            }

            // Obtener el último precio registrado para este producto y proveedor
            $stmt = $link->prepare("
                SELECT precio_compra, precio_venta, moneda, fecha_registro 
                FROM provider_precios 
                WHERE provider_id = ? AND product_id = ?
                ORDER BY fecha_registro DESC
                LIMIT 1
            ");
            $stmt->execute([$provider_id, $product_id]);
            $price = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($price) {
                echo json_encode([
                    'success' => true,
                    'price' => $price
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'price' => null
                ]);
            }
            break;

        case 'getPriceHistory':
            $provider_id = $_GET['provider_id'] ?? '';
            $product_id = $_GET['product_id'] ?? '';

            if (!$provider_id || !$product_id) {
                throw new Exception('Proveedor y producto son requeridos');
            }

            // Obtener historial de precios
            $stmt = $link->prepare("
                SELECT 
                    pp.*,
                    p.name as product_name,
                    pr.razon_social as provider_name
                FROM provider_precios pp
                JOIN products p ON p.id = pp.product_id
                JOIN providers pr ON pr.id = pp.provider_id
                WHERE pp.provider_id = ? AND pp.product_id = ?
                ORDER BY pp.fecha_registro DESC
            ");
            $stmt->execute([$provider_id, $product_id]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'history' => $history
            ]);
            break;

        default:
            throw new Exception('Acción no válida');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
