<?php
// web/views/pages/provider.php
require_once '../../../api/models/connection.php';

// Procesar formulario
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $ruc = $_POST['ruc'] ?? '';
    $tipo_proveedor = $_POST['tipo_proveedor'] ?? '';
    $razon_social = $_POST['razon_social'] ?? '';
    $estado_sunat = $_POST['estado_sunat'] ?? '';
    $condicion_sunat = $_POST['condicion_sunat'] ?? '';
    $representante_legal = $_POST['representante_legal'] ?? '';
    $fecha_inicio = $_POST['fecha_inicio'] ?? '';
    $score_comercial = $_POST['score_comercial'] ?? 0;
    $linea_credito = $_POST['linea_credito'] ?? 0;

    // Validaciones
    if (!$ruc || !$razon_social) {
        $message = '<div class="alert alert-danger">RUC y Razón Social son obligatorios.</div>';
    } else {
        $link = Connection::connect();
        $stmt = $link->prepare("INSERT INTO providers (
            name, email, phone, ruc, tipo_proveedor, razon_social, 
            estado_sunat, condicion_sunat, representante_legal, 
            fecha_inicio_actividades, score_comercial, linea_credito
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $result = $stmt->execute([
            $name,
            $email,
            $phone,
            $ruc,
            $tipo_proveedor,
            $razon_social,
            $estado_sunat,
            $condicion_sunat,
            $representante_legal,
            $fecha_inicio,
            $score_comercial,
            $linea_credito
        ]);
        if ($result) {
            $message = '<div class="alert alert-success">Proveedor creado correctamente.</div>';
        } else {
            $errorInfo = $stmt->errorInfo();
            $message = '<div class="alert alert-danger">Error al guardar en la base de datos: ' . htmlspecialchars(print_r($errorInfo, true)) . '</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Proveedor</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Estilos existentes -->
    <style>
        body {
            background: #f4f6f9;
        }
        .provider-header {
            background: linear-gradient(90deg, #6f42c1 0%, #20c997 100%);
            color: #fff;
            border-radius: 0;
            padding: 40px 5vw 32px 5vw;
            margin-bottom: 0;
        }
        .provider-header h2 {
            font-weight: 700;
            letter-spacing: 1px;
            margin-bottom: 0;
        }
        .provider-actions {
            margin: 32px 5vw 0 5vw;
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
        .provider-form-container {
            width: 100vw;
            max-width: 100vw;
            min-height: 90vh;
            margin: 0;
            border-radius: 0;
            box-shadow: none;
            padding: 0 5vw 40px 5vw;
            background: #fff;
        }
        .provider-form-container form {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.07);
            padding: 2.5rem 2rem 2rem 2rem;
            max-width: 900px;
            margin: 0 auto;
        }
        .provider-form-container h2 {
            font-weight: 700;
            color: #6f42c1;
            margin-bottom: 1.5rem;
        }
        .btn-primary.w-100 {
            font-size: 1.2em;
            padding: 0.8em 0;
            border-radius: 0.7em;
            font-weight: 700;
        }
        .card.direccion-item {
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(32,201,151,0.08);
        }
        @media (max-width: 991px) {
            .provider-header, .provider-actions, .provider-form-container {
                padding-left: 2vw !important;
                padding-right: 2vw !important;
            }
        }
        @media (max-width: 600px) {
            .provider-header, .provider-actions, .provider-form-container {
                padding-left: 0 !important;
                padding-right: 0 !important;
            }
            .provider-form-container form {
                padding: 1.2rem 0.5rem;
            }
        }
        .provider-container {
            max-width: 900px;
            margin: 40px auto;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.07);
            padding: 2.5rem 2rem 2rem 2rem;
        }
        .provider-container h2 {
            font-weight: 700;
            color: #6f42c1;
            margin-bottom: 1.5rem;
        }
        .btn-back {
            background: #f4f6f9;
            color: #6f42c1;
            border: 1px solid #6f42c1;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }
        .btn-back:hover {
            background: #6f42c1;
            color: #fff;
        }
        .score-badge {
            padding: 0.5em 1em;
            border-radius: 20px;
            font-weight: 600;
        }
        .score-high {
            background-color: #d4edda;
            color: #155724;
        }
        .score-medium {
            background-color: #fff3cd;
            color: #856404;
        }
        .score-low {
            background-color: #f8d7da;
            color: #721c24;
        }
        .select2-container .select2-selection--single {
            height: 38px;
            border: 1px solid #ced4da;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 38px;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px;
        }
    </style>
</head>
<body>
    <div class="provider-header d-flex flex-wrap justify-content-between align-items-center">
        <h2 class="mb-0"><i class="fas fa-truck"></i> Crear Proveedor</h2>
        <a href="/views/pages/providers_list.php" class="btn btn-back ms-auto"><i class="fas fa-arrow-left"></i> Ver Proveedores</a>
    </div>
    <div class="provider-form-container">
        <?php echo $message; ?>
        <form method="POST" id="providerForm" class="mt-4" autocomplete="off">
            <div class="mt-4">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="ruc" class="form-label">RUC</label>
                        <input type="text" class="form-control" id="ruc" name="ruc" maxlength="11" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="razon_social" class="form-label">Razón Social</label>
                        <input type="text" class="form-control" id="razon_social" name="razon_social" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label">Nombre Comercial</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="tipo_proveedor" class="form-label">Tipo de Proveedor</label>
                        <select class="form-select" id="tipo_proveedor" name="tipo_proveedor" required>
                            <option value="">Seleccione...</option>
                            <option value="mayorista">Mayorista</option>
                            <option value="minorista">Minorista</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="estado_sunat" class="form-label">Estado SUNAT</label>
                        <select class="form-select" id="estado_sunat" name="estado_sunat" required>
                            <option value="">Seleccione...</option>
                            <option value="activo">Activo</option>
                            <option value="inactivo">Inactivo</option>
                            <option value="suspendido">Suspendido</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="condicion_sunat" class="form-label">Condición SUNAT</label>
                        <select class="form-select" id="condicion_sunat" name="condicion_sunat" required>
                            <option value="">Seleccione...</option>
                            <option value="habido">Habido</option>
                            <option value="no habido">No Habido</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="phone" class="form-label">Teléfono</label>
                        <input type="text" class="form-control" id="phone" name="phone">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label for="representante_legal" class="form-label">Representante Legal</label>
                        <input type="text" class="form-control" id="representante_legal" name="representante_legal">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="fecha_inicio" class="form-label">Fecha Inicio Actividades</label>
                        <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="score_comercial" class="form-label">Score Comercial</label>
                        <input type="number" class="form-control" id="score_comercial" name="score_comercial" min="0" max="100">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="linea_credito" class="form-label">Línea de Crédito</label>
                        <input type="number" class="form-control" id="linea_credito" name="linea_credito" step="0.01" min="0">
                    </div>
                </div>

                <!-- DIRECCIONES: Sección agregada aquí -->
                <div class="row">
                    <div class="col-12">
                        <label class="form-label">Direcciones</label>
                        <div id="direccionesList">
                            <!-- Aquí se agregarán dinámicamente las direcciones -->
                        </div>
                        <button type="button" class="btn btn-success mt-2" id="addDireccion">
                            <i class="fas fa-plus"></i> Agregar Dirección
                        </button>
                    </div>
                </div>
                <!-- FIN DIRECCIONES -->

            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary w-100">Guardar Proveedor</button>
            </div>
        </form>
    </div>
    <script src="https://kit.fontawesome.com/2c36e9b7b1.js" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            // Inicializar todos los select2
            $('.form-select').select2({
                width: '100%',
                placeholder: 'Seleccione una opción'
            });

            // Validación de RUC
            $('#ruc').on('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '');
                if (this.value.length > 11) {
                    this.value = this.value.slice(0, 11);
                }
            });

            // Validación del Score Comercial
            $('#score_comercial').on('input', function() {
                let value = parseInt(this.value);
                if (value > 100) this.value = 100;
                if (value < 0) this.value = 0;
            });

            // DIRECCIONES: Lógica para agregar y eliminar direcciones
            $('#addDireccion').on('click', function() {
                const direccionTemplate = `
                    <div class="card mb-3 direccion-item">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <label class="form-label">Tipo de Dirección</label>
                                    <select class="form-select" name="direccion_tipo[]">
                                        <option value="fiscal">Fiscal</option>
                                        <option value="comercial">Comercial</option>
                                        <option value="almacen">Almacén</option>
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">Dirección</label>
                                    <input type="text" class="form-control" name="direccion[]" required>
                                </div>
                            </div>
                            <button type="button" class="btn btn-danger btn-sm mt-3 remove-direccion">
                                <i class="fas fa-trash"></i> Eliminar
                            </button>
                        </div>
                    </div>
                `;
                $('#direccionesList').append(direccionTemplate);
                // Evento para eliminar dirección
                $('.remove-direccion').off('click').on('click', function() {
                    $(this).closest('.direccion-item').remove();
                });
            });
            // FIN DIRECCIONES
        });
    </script>
</body>
</html>
