<?php
require_once '../../../../api/models/connection.php';

$message = "";
$link = Connection::connect();

// Obtener lista de proveedores
$stmt = $link->query("SELECT id, razon_social, ruc FROM providers WHERE status = 1 ORDER BY razon_social");
$providers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener lista de productos
$stmt = $link->query("
    SELECT 
        p.id, 
        p.name, 
        p.sku,
        c.name as category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.status = 1 
    ORDER BY p.category_id, p.name
");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $provider_id = $_POST['provider_id'] ?? '';
        $product_id = $_POST['product_id'] ?? '';
        $precio_compra = $_POST['precio_compra'] ?? '';
        $margen = $_POST['margen'] ?? '';
        $moneda = $_POST['moneda'] ?? 'PEN';
        $cantidad_minima = $_POST['cantidad_minima'] ?? 1;
        $fecha_registro = date('Y-m-d H:i:s');

        // Validaciones
        if (!$provider_id || !$product_id || !$precio_compra || !$margen) {
            throw new Exception('Todos los campos marcados son obligatorios');
        }

        // Calcular precio de venta basado en el margen
        $precio_venta = $precio_compra * (1 + ($margen / 100));

        // Procesar descuentos por volumen si existen
        $descuentos = [];
        if (!empty($_POST['cantidad']) && !empty($_POST['descuento'])) {
            foreach ($_POST['cantidad'] as $key => $cantidad) {
                if ($cantidad && isset($_POST['descuento'][$key])) {
                    $descuentos[] = [
                        'cantidad' => $cantidad,
                        'descuento' => $_POST['descuento'][$key]
                    ];
                }
            }
        }

        $link->beginTransaction();

        // Verificar si ya existe un precio para este producto y proveedor
        $stmt = $link->prepare("
            SELECT id, precio_compra 
            FROM provider_precios 
            WHERE provider_id = ? AND product_id = ?
        ");
        $stmt->execute([$provider_id, $product_id]);
        $existing_price = $stmt->fetch();
        
        if ($existing_price) {
            // Si el precio cambió más de 10%, registrar en el historial
            $precio_anterior = $existing_price['precio_compra'];
            $variacion = abs(($precio_compra - $precio_anterior) / $precio_anterior * 100);
            
            if ($variacion >= 10) {
                // Registrar en historial
                $stmt = $link->prepare("
                    INSERT INTO price_history 
                    (provider_id, product_id, precio_anterior, precio_nuevo, 
                     variacion_porcentaje, fecha_cambio) 
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $provider_id,
                    $product_id,
                    $precio_anterior,
                    $precio_compra,
                    $variacion
                ]);
            }

            // Actualizar precio existente
            $stmt = $link->prepare("
                UPDATE provider_precios 
                SET 
                    precio_compra = ?,
                    precio_venta = ?,
                    margen = ?,
                    moneda = ?,
                    cantidad_minima = ?,
                    descuentos = ?,
                    fecha_actualizacion = ?
                WHERE provider_id = ? AND product_id = ?
            ");
            
            $stmt->execute([
                $precio_compra,
                $precio_venta,
                $margen,
                $moneda,
                $cantidad_minima,
                json_encode($descuentos),
                $fecha_registro,
                $provider_id,
                $product_id
            ]);
            
            $message = '<div class="alert alert-success">Precio actualizado correctamente</div>';
        } else {
            // Insertar nuevo precio
            $stmt = $link->prepare("
                INSERT INTO provider_precios 
                (provider_id, product_id, precio_compra, precio_venta, 
                 margen, moneda, cantidad_minima, descuentos, fecha_registro) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $provider_id,
                $product_id,
                $precio_compra,
                $precio_venta,
                $margen,
                $moneda,
                $cantidad_minima,
                json_encode($descuentos),
                $fecha_registro
            ]);
            
            $message = '<div class="alert alert-success">Precio registrado correctamente</div>';
        }

        $link->commit();
    } catch (Exception $e) {
        $link->rollBack();
        $message = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    }
}

// Obtener últimos precios registrados con variación
$stmt = $link->query("
    SELECT 
        pp.*,
        p.name as product_name,
        p.sku,
        pr.razon_social as provider_name,
        pr.ruc,
        ph.precio_anterior,
        ph.variacion_porcentaje,
        ph.fecha_cambio
    FROM provider_precios pp
    JOIN products p ON p.id = pp.product_id
    JOIN providers pr ON pr.id = pp.provider_id
    LEFT JOIN (
        SELECT 
            provider_id,
            product_id,
            precio_anterior,
            variacion_porcentaje,
            fecha_cambio,
            ROW_NUMBER() OVER (PARTITION BY provider_id, product_id ORDER BY fecha_cambio DESC) as rn
        FROM price_history
    ) ph ON ph.provider_id = pp.provider_id 
        AND ph.product_id = pp.product_id 
        AND ph.rn = 1
    ORDER BY pp.fecha_actualizacion DESC
    LIMIT 10
");
$ultimos_precios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de Precios de Compra</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        .container {
            max-width: 1200px;
            margin: 40px auto;
        }
        .price-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.07);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .price-history {
            margin-top: 3rem;
        }
        .select2-container .select2-selection--single {
            height: 38px;
            border: 1px solid #ced4da;
        }
        .discount-row {
            margin-bottom: 10px;
        }
        .variation-up {
            color: #dc3545;
        }
        .variation-down {
            color: #198754;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container">
        <div class="price-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Registro de Precios de Compra</h2>
                <a href="../products/list.php" class="btn btn-outline-primary">
                    <i class="fas fa-box"></i> Ver Productos
                </a>
            </div>
            
            <?php echo $message; ?>
            
            <form method="POST" id="priceForm">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="provider_id" class="form-label">Proveedor *</label>
                        <select class="form-select" id="provider_id" name="provider_id" required>
                            <option value="">Seleccione un proveedor...</option>
                            <?php foreach($providers as $provider): ?>
                                <option value="<?php echo $provider['id']; ?>">
                                    <?php echo htmlspecialchars($provider['razon_social'] . ' - ' . $provider['ruc']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="product_id" class="form-label">Producto *</label>
                        <select class="form-select" id="product_id" name="product_id" required>
                            <option value="">Seleccione un producto...</option>
                            <?php 
                            $current_category = '';
                            foreach($products as $product): 
                                if ($current_category != $product['category_name']):
                                    if ($current_category != '') echo '</optgroup>';
                                    $current_category = $product['category_name'];
                                    echo '<optgroup label="' . htmlspecialchars($current_category) . '">';
                                endif;
                            ?>
                                <option value="<?php echo $product['id']; ?>">
                                    <?php echo htmlspecialchars($product['name'] . ' - ' . $product['sku']); ?>
                                </option>
                            <?php 
                            endforeach; 
                            if ($current_category != '') echo '</optgroup>';
                            ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="precio_compra" class="form-label">Precio de Compra *</label>
                        <input type="number" class="form-control" id="precio_compra" name="precio_compra" step="0.01" min="0" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="margen" class="form-label">Margen (%) *</label>
                        <input type="number" class="form-control" id="margen" name="margen" step="0.1" min="0" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="precio_venta" class="form-label">Precio de Venta Sugerido</label>
                        <input type="number" class="form-control" id="precio_venta" step="0.01" readonly>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="moneda" class="form-label">Moneda *</label>
                        <select class="form-select" id="moneda" name="moneda" required>
                            <option value="PEN">PEN</option>
                            <option value="USD">USD</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="cantidad_minima" class="form-label">Cantidad Mínima</label>
                        <input type="number" class="form-control" id="cantidad_minima" name="cantidad_minima" min="1" value="1">
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Descuentos por Volumen</h5>
                            <button type="button" class="btn btn-sm btn-success" id="addDiscountRow">
                                <i class="fas fa-plus"></i> Agregar Descuento
                            </button>
                        </div>
                    </div>
                    <div class="card-body" id="discountContainer">
                        <!-- Aquí se agregarán dinámicamente las filas de descuento -->
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Registrar Precio</button>
                </div>
            </form>
        </div>

        <div class="price-history price-card">
            <h3 class="mb-4">Últimos Precios Registrados</h3>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Proveedor</th>
                            <th>Producto</th>
                            <th>SKU</th>
                            <th>P. Compra</th>
                            <th>Margen</th>
                            <th>P. Venta</th>
                            <th>Variación</th>
                            <th>Moneda</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($ultimos_precios as $precio): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($precio['fecha_registro'])); ?></td>
                                <td><?php echo htmlspecialchars($precio['provider_name']); ?></td>
                                <td><?php echo htmlspecialchars($precio['product_name']); ?></td>
                                <td><?php echo htmlspecialchars($precio['sku']); ?></td>
                                <td><?php echo number_format($precio['precio_compra'], 2); ?></td>
                                <td><?php echo number_format($precio['margen'], 1); ?>%</td>
                                <td><?php echo number_format($precio['precio_venta'], 2); ?></td>
                                <td>
                                    <?php if (isset($precio['variacion_porcentaje'])): ?>
                                        <span class="<?php echo $precio['variacion_porcentaje'] > 0 ? 'variation-up' : 'variation-down'; ?>">
                                            <?php echo ($precio['variacion_porcentaje'] > 0 ? '+' : '') . number_format($precio['variacion_porcentaje'], 1); ?>%
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($precio['moneda']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            // Inicializar Select2
            $('.form-select').select2({
                width: '100%',
                placeholder: 'Seleccione una opción'
            });

            // Cargar último precio al seleccionar producto y proveedor
            async function cargarUltimoPrecio() {
                const provider_id = $('#provider_id').val();
                const product_id = $('#product_id').val();

                if (provider_id && product_id) {
                    try {
                        const response = await fetch(`/ajax/prices.ajax.php?action=getLastPrice&provider_id=${provider_id}&product_id=${product_id}`);
                        const data = await response.json();

                        if (data.success && data.price) {
                            $('#precio_compra').val(data.price.precio_compra);
                            $('#margen').val(data.price.margen);
                            $('#moneda').val(data.price.moneda).trigger('change');
                            $('#cantidad_minima').val(data.price.cantidad_minima);
                            
                            // Cargar descuentos
                            if (data.price.descuentos) {
                                const descuentos = JSON.parse(data.price.descuentos);
                                $('#discountContainer').empty();
                                descuentos.forEach(d => addDiscountRow(d.cantidad, d.descuento));
                            }
                            
                            calcularPrecioVenta();
                        }
                    } catch (error) {
                        console.error('Error al cargar último precio:', error);
                    }
                }
            }

            $('#provider_id, #product_id').on('change', cargarUltimoPrecio);

            // Calcular precio de venta según margen
            function calcularPrecioVenta() {
                const precioCompra = parseFloat($('#precio_compra').val()) || 0;
                const margen = parseFloat($('#margen').val()) || 0;
                const precioVenta = precioCompra * (1 + (margen / 100));
                $('#precio_venta').val(precioVenta.toFixed(2));
            }

            $('#precio_compra, #margen').on('input', calcularPrecioVenta);

            // Manejo de descuentos por volumen
            function addDiscountRow(cantidad = '', descuento = '') {
                const row = `
                    <div class="row discount-row">
                        <div class="col-md-5">
                            <div class="input-group">
                                <span class="input-group-text">Desde</span>
                                <input type="number" class="form-control" name="cantidad[]" min="1" value="${cantidad}">
                                <span class="input-group-text">unidades</span>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="input-group">
                                <input type="number" class="form-control" name="descuento[]" min="0" max="100" step="0.1" value="${descuento}">
                                <span class="input-group-text">% descuento</span>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-danger w-100 remove-discount">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
                $('#discountContainer').append(row);
            }

            $('#addDiscountRow').click(() => addDiscountRow());

            $(document).on('click', '.remove-discount', function() {
                $(this).closest('.discount-row').remove();
            });

            // Validar formulario
            $('#priceForm').on('submit', function(e) {
                const precioCompra = parseFloat($('#precio_compra').val());
                const margen = parseFloat($('#margen').val());

                if (!precioCompra || !margen) {
                    e.preventDefault();
                    Swal.fire('Error', 'Precio de compra y margen son obligatorios', 'error');
                }
            });
        });
    </script>
</body>
</html>
