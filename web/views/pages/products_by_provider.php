<?php
// Mostrar productos de un proveedor específico
$pdo = new PDO("mysql:host=localhost;dbname=ecommerce", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$provider_id = isset($_GET['provider_id']) ? intval($_GET['provider_id']) : 0;

// Obtener datos del proveedor
$stmt = $pdo->prepare("SELECT name FROM providers WHERE id = ?");
$stmt->execute([$provider_id]);
$proveedor = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener productos del proveedor
$stmt = $pdo->prepare("SELECT id_product, name_product, url_product, image_product, description_product FROM products WHERE provider_id = ?");
$stmt->execute([$provider_id]);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Productos de Proveedor</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background: #f4f6f9; }
        .products-header {
            background: linear-gradient(90deg, #6f42c1 0%, #20c997 100%);
            color: #fff;
            border-radius: 0;
            padding: 40px 5vw 32px 5vw;
            margin-bottom: 0;
        }
        .products-header h2 {
            font-weight: 700;
            letter-spacing: 1px;
            margin-bottom: 0;
        }
        .btn-back {
            background: #fff;
            color: #6f42c1;
            border: 1.5px solid #6f42c1;
            font-weight: 600;
            margin-bottom: 1.5rem;
            transition: all 0.2s;
        }
        .btn-back:hover {
            background: #6f42c1;
            color: #fff;
        }
        .products-table-container {
            width: 100vw;
            max-width: 100vw;
            min-height: 90vh;
            margin: 0;
            border-radius: 0;
            box-shadow: none;
            padding: 0 5vw 40px 5vw;
            background: #fff;
        }
        .products-table-container .card {
            border-radius: 16px;
            box-shadow: 0 2px 16px rgba(111,66,193,0.10);
            border: none;
        }
        .products-table-container .card-body {
            padding: 2.5rem 2rem 2rem 2rem;
        }
        .table {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            margin-top: 24px;
        }
        .table thead th {
            background: #6f42c1;
            color: #fff;
            font-size: 1.1em;
            border: none;
        }
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: #f8f9fa;
        }
        .table-bordered th, .table-bordered td {
            border: 1px solid #e0e0e0 !important;
        }
        .table td, .table th {
            vertical-align: middle;
        }
        .img-thumbnail {
            max-width: 90px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(32,201,151,0.08);
        }
        @media (max-width: 991px) {
            .products-header, .products-table-container {
                padding-left: 2vw !important;
                padding-right: 2vw !important;
            }
        }
        @media (max-width: 600px) {
            .products-header, .products-table-container {
                padding-left: 0 !important;
                padding-right: 0 !important;
            }
            .products-table-container .card-body {
                padding: 1.2rem 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="products-header d-flex flex-wrap justify-content-between align-items-center">
        <h2 class="mb-0"><i class="fas fa-box"></i> Productos de <?= htmlspecialchars($proveedor['name'] ?? 'Proveedor desconocido') ?></h2>
        <a href="/views/pages/providers_list.php" class="btn btn-back ms-auto"><i class="fas fa-arrow-left"></i> Volver a proveedores</a>
    </div>
    <div class="products-table-container">
        <div class="card mt-4">
            <div class="card-body">
                <?php if (empty($productos)): ?>
                    <div class="alert alert-warning">Este proveedor no tiene productos registrados.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>URL</th>
                                    <th>Imagen</th>
                                    <th>Descripción</th>
                                    <th>Precios de compra</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($productos as $prod): ?>
                                <?php
                                // Obtener precios históricos de compra para este producto
                                $stmt = $pdo->prepare("SELECT purchase_price, purchase_date FROM product_purchases WHERE product_id = ? ORDER BY purchase_date DESC");
                                $stmt->execute([$prod['id_product']]);
                                $precios = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                ?>
                                <tr>
                                    <td><?= $prod['id_product'] ?></td>
                                    <td><?= htmlspecialchars($prod['name_product']) ?></td>
                                    <td><?= htmlspecialchars($prod['url_product']) ?></td>
                                    <td><img src="/views/assets/img/products/<?= htmlspecialchars($prod['url_product']) ?>/<?= htmlspecialchars($prod['image_product']) ?>" class="img-thumbnail rounded"></td>
                                    <td><?= htmlspecialchars($prod['description_product']) ?></td>
                                    <td>
                                        <?php if (empty($precios)): ?>
                                            <span class="text-muted">Sin historial</span>
                                        <?php else: ?>
                                            <ul class="mb-0 pl-3" style="font-size:0.98em;">
                                                <?php foreach ($precios as $precio): ?>
                                                    <li><b>$<?= number_format($precio['purchase_price'],2) ?></b> <span class="text-muted">(<?= htmlspecialchars($precio['purchase_date']) ?>)</span></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://kit.fontawesome.com/2c36e9b7b1.js" crossorigin="anonymous"></script>
</body>
</html>
