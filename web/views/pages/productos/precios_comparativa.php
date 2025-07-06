<?php
require_once '../../../api/models/connection.php';

try {
    $link = Connection::connect();

    // Obtener productos con sus últimos precios por proveedor
    $stmt = $link->query("
        SELECT 
            p.id_product,
            p.name_product,
            p.image_product,
            p.sku,
            c.name as category_name,
            sc.name as subcategory_name,
            pr.id as provider_id,
            pr.razon_social as provider_name,
            pp.precio_compra,
            pp.precio_venta,
            pp.margen,
            pp.moneda,
            pp.fecha_registro,
            pp.cantidad_minima,
            pp.descuentos,
            ph.variacion_porcentaje as ultima_variacion
        FROM products p
        LEFT JOIN categories c ON p.id_category = c.id
        LEFT JOIN subcategories sc ON p.id_subcategory = sc.id
        LEFT JOIN provider_precios pp ON p.id_product = pp.product_id
        LEFT JOIN providers pr ON pp.provider_id = pr.id
        LEFT JOIN price_history ph ON (
            ph.product_id = pp.product_id 
            AND ph.provider_id = pp.provider_id
            AND ph.fecha_cambio = (
                SELECT MAX(fecha_cambio) 
                FROM price_history 
                WHERE product_id = pp.product_id 
                AND provider_id = pp.provider_id
            )
        )
        WHERE pp.id IS NOT NULL
        ORDER BY p.name_product, pp.precio_compra ASC
    ");
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Agrupar productos por ID para mostrar todos sus proveedores
    $productosAgrupados = [];
    foreach ($productos as $prod) {
        $id = $prod['id_product'];
        if (!isset($productosAgrupados[$id])) {
            $productosAgrupados[$id] = [
                'info' => [
                    'id' => $prod['id_product'],
                    'name' => $prod['name_product'],
                    'image' => $prod['image_product'],
                    'sku' => $prod['sku'],
                    'category' => $prod['category_name'],
                    'subcategory' => $prod['subcategory_name']
                ],
                'providers' => []
            ];
        }
        if ($prod['provider_id']) {
            $descuentos = json_decode($prod['descuentos'], true) ?: [];
            $productosAgrupados[$id]['providers'][] = [
                'id' => $prod['provider_id'],
                'name' => $prod['provider_name'],
                'precio_compra' => $prod['precio_compra'],
                'precio_venta' => $prod['precio_venta'],
                'margen' => $prod['margen'],
                'moneda' => $prod['moneda'],
                'fecha' => $prod['fecha_registro'],
                'cantidad_minima' => $prod['cantidad_minima'],
                'descuentos' => $descuentos,
                'ultima_variacion' => $prod['ultima_variacion']
            ];
        }
    }

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comparativa de Precios por Producto</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .product-card {
            transition: transform 0.2s;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .price-badge {
            position: absolute;
            top: -10px;
            right: -10px;
            z-index: 2;
        }
        .provider-price {
            border-left: 4px solid transparent;
        }
        .provider-price.best-price {
            border-left-color: #28a745;
        }
        .provider-price.warning-price {
            border-left-color: #ffc107;
        }
        .trend-icon {
            font-size: 0.8rem;
            margin-left: 0.3rem;
        }
        .price-history-chart {
            height: 200px;
        }
    </style>
</head>
<body>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Comparativa de Precios por Producto</h2>
        </div>
        <div class="col-auto">
            <a href="/admin/productos" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver a productos
            </a>
            <a href="/views/pages/purchase_prices/register.php" class="btn btn-success ms-2">
                <i class="fas fa-plus"></i> Registrar precio
            </a>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <label class="form-label">Categoría</label>
                    <select class="form-select select2" id="filterCategory">
                        <option value="">Todas</option>
                        <?php
                        $categories = array_unique(array_column(array_column($productosAgrupados, 'info'), 'category'));
                        foreach ($categories as $cat):
                            if ($cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                            <?php endif;
                        endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Proveedor</label>
                    <select class="form-select select2" id="filterProvider">
                        <option value="">Todos</option>
                        <?php
                        $providers = [];
                        foreach ($productosAgrupados as $prod) {
                            foreach ($prod['providers'] as $prov) {
                                $providers[$prov['id']] = $prov['name'];
                            }
                        }
                        asort($providers);
                        foreach ($providers as $id => $name): ?>
                            <option value="<?= $id ?>"><?= htmlspecialchars($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Variación de precio</label>
                    <select class="form-select" id="filterVariation">
                        <option value="">Todas</option>
                        <option value="increase">Aumentos</option>
                        <option value="decrease">Reducciones</option>
                        <option value="stable">Sin cambios</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Ordenar por</label>
                    <select class="form-select" id="sortBy">
                        <option value="name">Nombre</option>
                        <option value="price_low">Menor precio</option>
                        <option value="price_high">Mayor precio</option>
                        <option value="recent">Más recientes</option>
                        <option value="variation">Mayor variación</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de productos -->
    <div class="row" id="productsList">
        <?php foreach ($productosAgrupados as $producto): 
            $minPrice = min(array_column($producto['providers'], 'precio_compra'));
            $maxPrice = max(array_column($producto['providers'], 'precio_compra'));
            $avgPrice = array_sum(array_column($producto['providers'], 'precio_compra')) / count($producto['providers']);
        ?>
        <div class="col-12 mb-4">
            <div class="card product-card">
                <div class="card-body">
                    <div class="row">
                        <!-- Info producto -->
                        <div class="col-md-3">
                            <div class="d-flex">
                                <img src="<?= $producto['info']['image'] ?: '/views/assets/img/products/default/default-image.jpg' ?>" 
                                     class="img-thumbnail me-3" style="width: 80px; height: 80px; object-fit: cover;">
                                <div>
                                    <h5 class="mb-1"><?= htmlspecialchars($producto['info']['name']) ?></h5>
                                    <small class="text-muted">SKU: <?= htmlspecialchars($producto['info']['sku']) ?></small><br>
                                    <small class="text-muted"><?= htmlspecialchars($producto['info']['category']) ?> / <?= htmlspecialchars($producto['info']['subcategory']) ?></small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Resumen precios -->
                        <div class="col-md-2">
                            <div class="text-center py-2">
                                <div class="small text-muted mb-1">Rango de precios</div>
                                <h6 class="mb-0">
                                    S/ <?= number_format($minPrice, 2) ?> - S/ <?= number_format($maxPrice, 2) ?>
                                </h6>
                                <small class="text-muted">Promedio: S/ <?= number_format($avgPrice, 2) ?></small>
                            </div>
                        </div>

                        <!-- Lista de proveedores -->
                        <div class="col-md-7">
                            <div class="row">
                                <?php foreach ($producto['providers'] as $provider): 
                                    $isPriceBest = $provider['precio_compra'] == $minPrice;
                                    $isPriceHigh = $provider['precio_compra'] > $avgPrice * 1.1; // 10% sobre promedio
                                ?>
                                <div class="col-md-6 mb-2">
                                    <div class="card provider-price <?= $isPriceBest ? 'best-price' : ($isPriceHigh ? 'warning-price' : '') ?>">
                                        <div class="card-body p-2">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1">
                                                        <?= htmlspecialchars($provider['name']) ?>
                                                        <?php if ($provider['ultima_variacion']): ?>
                                                            <span class="trend-icon text-<?= $provider['ultima_variacion'] > 0 ? 'danger' : 'success' ?>">
                                                                <i class="fas fa-arrow-<?= $provider['ultima_variacion'] > 0 ? 'up' : 'down' ?>"></i>
                                                                <?= abs($provider['ultima_variacion']) ?>%
                                                            </span>
                                                        <?php endif; ?>
                                                    </h6>
                                                    <div class="small">
                                                        Compra: <strong>S/ <?= number_format($provider['precio_compra'], 2) ?></strong>
                                                        <span class="text-muted mx-2">|</span>
                                                        Venta: S/ <?= number_format($provider['precio_venta'], 2) ?>
                                                    </div>
                                                    <div class="small text-muted">
                                                        Actualizado: <?= date('d/m/Y', strtotime($provider['fecha'])) ?>
                                                    </div>
                                                </div>
                                                <button class="btn btn-sm btn-outline-primary view-history" 
                                                        data-product="<?= $producto['info']['id'] ?>" 
                                                        data-provider="<?= $provider['id'] ?>">
                                                    <i class="fas fa-history"></i>
                                                </button>
                                            </div>
                                            <?php if (!empty($provider['descuentos'])): ?>
                                            <div class="mt-2 small">
                                                <i class="fas fa-tag text-success"></i> 
                                                <?php foreach ($provider['descuentos'] as $desc): ?>
                                                    <span class="badge bg-light text-dark border">
                                                        <?= $desc['cantidad'] ?>+ unid: -<?= $desc['descuento'] ?>%
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal Historial -->
<div class="modal fade" id="historyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Historial de Precios</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="price-history-chart mb-4">
                    <canvas id="priceHistoryChart"></canvas>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm" id="historyTable">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Precio anterior</th>
                                <th>Precio nuevo</th>
                                <th>Variación</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://kit.fontawesome.com/2c36e9b7b1.js"></script>

<script>
$(document).ready(function() {
    // Inicializar Select2
    $('.select2').select2({
        width: '100%'
    });

    // Función para aplicar filtros
    function applyFilters() {
        const category = $('#filterCategory').val();
        const provider = $('#filterProvider').val();
        const variation = $('#filterVariation').val();
        const sortBy = $('#sortBy').val();

        $('.product-card').parent().show();

        // Filtrar por categoría
        if (category) {
            $('.product-card').each(function() {
                const productCategory = $(this).find('.text-muted').text();
                if (!productCategory.includes(category)) {
                    $(this).parent().hide();
                }
            });
        }

        // Filtrar por proveedor
        if (provider) {
            $('.product-card').each(function() {
                const hasProvider = $(this).find('.provider-price').toArray()
                    .some(el => $(el).find('h6').text().includes(provider));
                if (!hasProvider) {
                    $(this).parent().hide();
                }
            });
        }

        // Filtrar por variación
        if (variation) {
            $('.product-card').each(function() {
                const card = $(this);
                const hasVariation = card.find('.trend-icon').toArray()
                    .some(el => {
                        const trend = $(el).find('i').hasClass('fa-arrow-up') ? 'increase' : 'decrease';
                        return variation === trend;
                    });
                if (variation === 'stable' && card.find('.trend-icon').length === 0) {
                    return; // Mantener visible
                }
                if (!hasVariation) {
                    card.parent().hide();
                }
            });
        }

        // Ordenar productos
        const products = $('#productsList .col-12').toArray();
        products.sort((a, b) => {
            const cardA = $(a).find('.product-card');
            const cardB = $(b).find('.product-card');
            
            switch (sortBy) {
                case 'name':
                    return $(cardA).find('h5').text().localeCompare($(cardB).find('h5').text());
                case 'price_low':
                    return parseFloat($(cardA).find('.provider-price:first .small strong').text().replace('S/ ', '')) -
                           parseFloat($(cardB).find('.provider-price:first .small strong').text().replace('S/ ', ''));
                case 'price_high':
                    return parseFloat($(cardB).find('.provider-price:first .small strong').text().replace('S/ ', '')) -
                           parseFloat($(cardA).find('.provider-price:first .small strong').text().replace('S/ ', ''));
                case 'recent':
                    return new Date($(cardB).find('.text-muted:contains("Actualizado")').text().replace('Actualizado: ', '')) -
                           new Date($(cardA).find('.text-muted:contains("Actualizado")').text().replace('Actualizado: ', ''));
                case 'variation':
                    const getMaxVariation = card => {
                        const variations = $(card).find('.trend-icon').toArray()
                            .map(el => parseFloat($(el).text().replace('%', '')));
                        return Math.max(0, ...variations);
                    };
                    return getMaxVariation(cardB) - getMaxVariation(cardA);
            }
        });
        
        $('#productsList').empty().append(products);
    }

    // Eventos de filtros
    $('#filterCategory, #filterProvider, #filterVariation, #sortBy').on('change', applyFilters);

    // Ver historial de precios
    $('.view-history').click(async function() {
        const productId = $(this).data('product');
        const providerId = $(this).data('provider');
        
        try {
            const response = await fetch(`/ajax/prices.ajax.php?action=getPriceHistory&provider_id=${providerId}&product_id=${productId}`);
            const data = await response.json();
            
            if (data.success) {
                // Limpiar tabla
                const tbody = $('#historyTable tbody').empty();
                
                // Preparar datos para el gráfico
                const dates = [];
                const prices = [];
                
                data.history.forEach(record => {
                    // Agregar fila a la tabla
                    tbody.append(`
                        <tr>
                            <td>${new Date(record.fecha_registro).toLocaleDateString()}</td>
                            <td>S/ ${parseFloat(record.precio_anterior).toFixed(2)}</td>
                            <td>S/ ${parseFloat(record.precio_nuevo).toFixed(2)}</td>
                            <td>
                                <span class="badge bg-${record.variacion_porcentaje > 0 ? 'danger' : 'success'}">
                                    ${record.variacion_porcentaje > 0 ? '+' : ''}${record.variacion_porcentaje}%
                                </span>
                            </td>
                        </tr>
                    `);
                    
                    // Datos para el gráfico
                    dates.push(new Date(record.fecha_registro).toLocaleDateString());
                    prices.push(record.precio_nuevo);
                });
                
                // Crear gráfico
                const ctx = document.getElementById('priceHistoryChart');
                if (window.priceChart) {
                    window.priceChart.destroy();
                }
                window.priceChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: dates.reverse(),
                        datasets: [{
                            label: 'Precio de compra',
                            data: prices.reverse(),
                            borderColor: '#0d6efd',
                            tension: 0.1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: false,
                                ticks: {
                                    callback: value => 'S/ ' + value.toFixed(2)
                                }
                            }
                        },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: context => 'S/ ' + context.parsed.y.toFixed(2)
                                }
                            }
                        }
                    }
                });
                
                // Mostrar modal
                new bootstrap.Modal(document.getElementById('historyModal')).show();
            }
        } catch (error) {
            console.error('Error al cargar historial:', error);
            alert('Error al cargar el historial de precios');
        }
    });
});
</script>

</body>
</html>
