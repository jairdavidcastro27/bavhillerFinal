<?php
// web/views/pages/providers_list.php
require_once '../../../api/models/connection.php';

// Obtener todos los proveedores
date_default_timezone_set('America/Bogota');
$link = Connection::connect();
$stmt = $link->prepare("SELECT * FROM providers ORDER BY created_at DESC");
$stmt->execute();
$providers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Proveedores</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            background: #f4f6f9;
        }
        .providers-list-container {
            width: 100vw;
            max-width: 100vw;
            min-height: 90vh;
            margin: 0;
            border-radius: 0;
            box-shadow: none;
            padding: 0;
        }
        .providers-header {
            background: linear-gradient(90deg, #6f42c1 0%, #20c997 100%);
            color: #fff;
            border-radius: 0;
            padding: 40px 5vw 32px 5vw;
            margin-bottom: 0;
        }
        .providers-header h2 {
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
        .providers-actions {
            margin: 32px 5vw 0 5vw;
        }
        .providers-actions .btn {
            font-weight: 600;
            margin-right: 10px;
            margin-bottom: 10px;
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
        .table tbody tr {
            font-size: 1.05em;
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
        .btn-sm {
            padding: 0.35rem 0.7rem;
            font-size: 1em;
        }
        .modal-content {
            border-radius: 16px;
        }
        .modal-header {
            background: linear-gradient(90deg, #6f42c1 0%, #20c997 100%);
            color: #fff;
            border-radius: 16px 16px 0 0;
        }
        @media (max-width: 991px) {
            .providers-header, .providers-actions {
                padding-left: 2vw !important;
                padding-right: 2vw !important;
            }
        }
        @media (max-width: 600px) {
            .providers-header, .providers-actions {
                padding-left: 0 !important;
                padding-right: 0 !important;
            }
        }
    </style>
</head>
<body>
    <div class="providers-header d-flex flex-wrap justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <h2 class="mb-0"><i class="fas fa-truck"></i> Proveedores</h2>
        </div>
        <a href="/admin/pedidos" class="btn btn-back ms-auto"><i class="fas fa-arrow-left"></i> Regresar a Pedidos</a>
    </div>
    <div class="providers-actions">
        <a href="/views/pages/provider.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nuevo Proveedor</a>
        <a href="/views/pages/register_purchase_price.php" class="btn btn-success"><i class="fas fa-dollar-sign"></i> Registrar precio de compra</a>
        <button type="button" class="btn btn-info" id="verDetallesProveedores">
            <i class="fas fa-list"></i> Ver detalles de proveedores
        </button>
    </div>
    <div class="table-responsive px-4">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Teléfono</th>
                    <th>Dirección</th>
                    <th>Fecha Registro</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($providers)): ?>
                    <tr><td colspan="7" class="text-center">No hay proveedores registrados.</td></tr>
                <?php else: foreach ($providers as $prov): ?>
                    <tr>
                        <td><?php echo $prov['id']; ?></td>
                        <td><?php echo htmlspecialchars($prov['name']); ?></td>
                        <td><?php echo htmlspecialchars($prov['email']); ?></td>
                        <td><?php echo htmlspecialchars($prov['phone']); ?></td>
                        <td><?php echo htmlspecialchars($prov['address']); ?></td>
                        <td><?php echo $prov['created_at'] ?? '-'; ?></td>
                        <td>
                            <a href="/views/pages/edit_provider.php?id=<?php echo $prov['id']; ?>" class="btn btn-sm btn-warning" title="Editar"><i class="fas fa-edit"></i></a>
                            <a href="/views/pages/delete_provider.php?id=<?php echo $prov['id']; ?>" class="btn btn-sm btn-danger" title="Eliminar" onclick="return confirm('¿Seguro que deseas eliminar este proveedor?');"><i class="fas fa-trash"></i></a>
                            <a href="/views/pages/products_by_provider.php?provider_id=<?php echo $prov['id']; ?>" class="btn btn-sm btn-info" title="Ver productos"><i class="fas fa-box"></i></a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <!-- Modal para mostrar detalles de proveedores -->
    <div class="modal fade" id="modalDetallesProveedores" tabindex="-1" aria-labelledby="modalDetallesProveedoresLabel" aria-hidden="true">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="modalDetallesProveedoresLabel">Detalles de Proveedores</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <div class="modal-body">
            <div id="tablaProveedoresContainer">
              <!-- Aquí se cargará la tabla directamente -->
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
          </div>
        </div>
      </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/2c36e9b7b1.js" crossorigin="anonymous"></script>
    <script>
        $(document).ready(function() {
            // Botón para ver detalles de proveedores
            $('#verDetallesProveedores').on('click', function() {
                // Generar la tabla directamente desde la variable PHP $providers
                let data = <?php echo json_encode($providers); ?>;
                let html = '';
                if (data && data.length > 0) {
                    html = `<div class=\"table-responsive\"><table class=\"table table-bordered table-striped table-hover\">\
                        <thead>\
                            <tr>\
                                <th>ID</th>\
                                <th>RUC</th>\
                                <th>Razón Social</th>\
                                <th>Nombre Comercial</th>\
                                <th>Tipo</th>\
                                <th>Estado SUNAT</th>\
                                <th>Condición SUNAT</th>\
                                <th>Email</th>\
                                <th>Teléfono</th>\
                                <th>Representante Legal</th>\
                                <th>Fecha Inicio</th>\
                                <th>Score Comercial</th>\
                                <th>Línea de Crédito</th>\
                            </tr>\
                        </thead>\
                        <tbody>`;
                    data.forEach(function(p) {
                        html += `<tr>\
                            <td>${p.id}</td>\
                            <td>${p.ruc || ''}</td>\
                            <td>${p.razon_social || ''}</td>\
                            <td>${p.name || ''}</td>\
                            <td>${p.tipo_proveedor || p.tipo || ''}</td>\
                            <td>${p.estado_sunat || ''}</td>\
                            <td>${p.condicion_sunat || ''}</td>\
                            <td>${p.email || ''}</td>\
                            <td>${p.phone || ''}</td>\
                            <td>${p.representante_legal || ''}</td>\
                            <td>${p.fecha_inicio_actividades || p.fecha_inicio || ''}</td>\
                            <td>${p.score_comercial || p.score || ''}</td>\
                            <td>${p.linea_credito || ''}</td>\
                        </tr>`;
                    });
                    html += '</tbody></table></div>';
                } else {
                    html = '<div class="alert alert-warning">No hay proveedores registrados.</div>';
                }
                $('#tablaProveedoresContainer').html(html);
                $('#modalDetallesProveedores').modal('show');
            });
        });
    </script>
</body>
</html>
