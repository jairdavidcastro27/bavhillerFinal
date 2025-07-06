<?php
require_once '../../../api/models/connection.php';

$id = $_GET['id'] ?? 0;
$message = '';
$link = Connection::connect();

// Obtener información del proveedor
$stmt = $link->prepare("SELECT * FROM providers WHERE id = ?");
$stmt->execute([$id]);
$provider = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$provider) {
    header('Location: /views/pages/providers_list.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $calidad_servicio = $_POST['calidad_servicio'] ?? 0;
    $tiempo_entrega = $_POST['tiempo_entrega'] ?? 0;
    $nivel_precios = $_POST['nivel_precios'] ?? 0;
    $cumplimiento_documentario = $_POST['cumplimiento_documentario'] ?? 0;
    $observaciones = $_POST['observaciones'] ?? '';
    
    // Calcular score total
    $score_total = ($calidad_servicio + $tiempo_entrega + $nivel_precios + $cumplimiento_documentario) / 4;
    
    $stmt = $link->prepare("
        INSERT INTO provider_evaluaciones 
        (provider_id, periodo, calidad_servicio, tiempo_entrega, nivel_precios, 
         cumplimiento_documentario, score_total, observaciones) 
        VALUES (?, CURDATE(), ?, ?, ?, ?, ?, ?)
    ");
    
    if ($stmt->execute([
        $id, 
        $calidad_servicio, 
        $tiempo_entrega, 
        $nivel_precios,
        $cumplimiento_documentario,
        $score_total,
        $observaciones
    ])) {
        // Actualizar score comercial del proveedor
        $stmt = $link->prepare("
            UPDATE providers 
            SET score_comercial = (
                SELECT AVG(score_total) 
                FROM provider_evaluaciones 
                WHERE provider_id = ?
            )
            WHERE id = ?
        ");
        $stmt->execute([$id, $id]);
        
        $message = '<div class="alert alert-success">Evaluación registrada correctamente.</div>';
    } else {
        $message = '<div class="alert alert-danger">Error al registrar la evaluación.</div>';
    }
}

// Obtener historial de evaluaciones
$stmt = $link->prepare("
    SELECT * 
    FROM provider_evaluaciones 
    WHERE provider_id = ? 
    ORDER BY fecha_evaluacion DESC
");
$stmt->execute([$id]);
$evaluaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Evaluar Proveedor</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        .provider-eval-container {
            max-width: 1000px;
            margin: 40px auto;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.07);
            padding: 2.5rem 2rem;
        }
        .rating-group {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
        }
        .rating-item {
            flex: 1;
            margin: 0 0.5rem;
            text-align: center;
        }
    </style>
</head>
<body class="bg-light">
    <div class="provider-eval-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Evaluar Proveedor: <?php echo htmlspecialchars($provider['razon_social']); ?></h2>
            <a href="/views/pages/providers_list.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left"></i> Volver a la lista
            </a>
        </div>

        <?php echo $message; ?>

        <form method="POST" class="mb-5">
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="rating-item">
                        <label class="form-label">Calidad de Servicio</label>
                        <select name="calidad_servicio" class="form-select" required>
                            <option value="">Seleccione...</option>
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="rating-item">
                        <label class="form-label">Tiempo de Entrega</label>
                        <select name="tiempo_entrega" class="form-select" required>
                            <option value="">Seleccione...</option>
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="rating-item">
                        <label class="form-label">Nivel de Precios</label>
                        <select name="nivel_precios" class="form-select" required>
                            <option value="">Seleccione...</option>
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="rating-item">
                        <label class="form-label">Cumplimiento Documentario</label>
                        <select name="cumplimiento_documentario" class="form-select" required>
                            <option value="">Seleccione...</option>
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label for="observaciones" class="form-label">Observaciones</label>
                <textarea class="form-control" id="observaciones" name="observaciones" rows="3"></textarea>
            </div>

            <button type="submit" class="btn btn-primary">Registrar Evaluación</button>
        </form>

        <h3 class="mb-4">Historial de Evaluaciones</h3>
        <?php if (empty($evaluaciones)): ?>
            <p class="text-muted">No hay evaluaciones registradas.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha</th>
                            <th>Calidad</th>
                            <th>T. Entrega</th>
                            <th>Precios</th>
                            <th>Documentos</th>
                            <th>Score Total</th>
                            <th>Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($evaluaciones as $eval): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($eval['fecha_evaluacion'])); ?></td>
                                <td><?php echo $eval['calidad_servicio']; ?></td>
                                <td><?php echo $eval['tiempo_entrega']; ?></td>
                                <td><?php echo $eval['nivel_precios']; ?></td>
                                <td><?php echo $eval['cumplimiento_documentario']; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $eval['score_total'] >= 4 ? 'success' : ($eval['score_total'] >= 3 ? 'warning' : 'danger'); ?>">
                                        <?php echo number_format($eval['score_total'], 1); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($eval['observaciones']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <script src="https://kit.fontawesome.com/2c36e9b7b1.js" crossorigin="anonymous"></script>
</body>
</html>
