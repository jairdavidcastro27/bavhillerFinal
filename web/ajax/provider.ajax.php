<?php

require_once "../controllers/providers.controller.php";
require_once "../models/providers.model.php";
require_once "../models/connection.php";

class AjaxProvider {
    public function ajaxSaveProvider() {
        try {
            // Validar datos recibidos
            if (!isset($_POST['ruc']) || !isset($_POST['razon_social'])) {
                echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios']);
                return;
            }

            // Datos del proveedor
            $providerData = [
                'ruc' => $_POST['ruc'],
                'razon_social' => $_POST['razon_social'],
                'tipo' => $_POST['tipo'] ?? 'comercial',
                'estado_sunat' => $_POST['estado_sunat'] ?? 'activo',
                'score' => $_POST['score'] ?? 0,
                'linea_credito' => $_POST['linea_credito'] ?? 0,
                'dias_credito' => $_POST['dias_credito'] ?? 0,
                'status' => 1
            ];

            // Conectar a la base de datos
            $link = Connection::connect();
            $link->beginTransaction();

            // Insertar proveedor
            $stmt = $link->prepare("INSERT INTO providers 
                (ruc, razon_social, tipo, estado_sunat, score, linea_credito, dias_credito, status) 
                VALUES 
                (:ruc, :razon_social, :tipo, :estado_sunat, :score, :linea_credito, :dias_credito, :status)");
            $stmt->execute($providerData);
            $providerId = $link->lastInsertId();

            // Procesar direcciones
            $direcciones = json_decode($_POST['direcciones'], true);
            if (!empty($direcciones)) {
                $stmtDir = $link->prepare("INSERT INTO provider_addresses 
                    (provider_id, tipo, direccion, departamento, provincia, distrito) 
                    VALUES 
                    (:provider_id, :tipo, :direccion, :departamento, :provincia, :distrito)");
                
                foreach ($direcciones as $direccion) {
                    $direccion['provider_id'] = $providerId;
                    $stmtDir->execute($direccion);
                }
            }

            // Procesar aranceles
            $aranceles = json_decode($_POST['aranceles'], true);
            if (!empty($aranceles)) {
                $stmtArancel = $link->prepare("INSERT INTO provider_aranceles 
                    (provider_id, arancel_id, porcentaje) 
                    VALUES 
                    (:provider_id, :arancel_id, :porcentaje)");
                
                foreach ($aranceles as $arancel) {
                    $stmtArancel->execute([
                        'provider_id' => $providerId,
                        'arancel_id' => $arancel['id'],
                        'porcentaje' => $arancel['porcentaje']
                    ]);
                }
            }

            // Procesar precios
            $precios = json_decode($_POST['precios'], true);
            if (!empty($precios)) {
                $stmtPrecio = $link->prepare("INSERT INTO provider_precios 
                    (provider_id, product_id, precio_compra, precio_venta, moneda) 
                    VALUES 
                    (:provider_id, :product_id, :precio_compra, :precio_venta, :moneda)");
                
                foreach ($precios as $precio) {
                    $stmtPrecio->execute([
                        'provider_id' => $providerId,
                        'product_id' => $precio['product_id'],
                        'precio_compra' => $precio['precio_compra'],
                        'precio_venta' => $precio['precio_venta'],
                        'moneda' => $precio['currency']
                    ]);
                }
            }

            // Confirmar transacción
            $link->commit();
            echo json_encode(['success' => true]);

        } catch (Exception $e) {
            if (isset($link)) {
                $link->rollBack();
            }
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function ajaxGetAllProviders() {
        try {
            $link = Connection::connect();
            $stmt = $link->prepare("SELECT * FROM providers ORDER BY created_at DESC");
            $stmt->execute();
            $providers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $providers]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}

// Instanciar y ejecutar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ajax = new AjaxProvider();
    if (isset($_POST['action']) && $_POST['action'] === 'getAllProviders') {
        $ajax->ajaxGetAllProviders();
    } else {
        $ajax->ajaxSaveProvider();
    }
}
