<?php
require_once __DIR__ . '/../../../config/database.php';

if (!isset($conexion) || !$conexion instanceof PDO) {
    die("Error: revisa config/database.php. La conexión debe llamarse \$conexion y usar PDO.");
}

$db = $conexion;

$stmt = $db->query("
    SELECT
        id,
        tipo_programa,
        titulo,
        descripcion,
        version,
        archivo_original,
        ruta_archivo,
        created_at
    FROM reglamentos
    WHERE estado = 'ACTIVO'
    ORDER BY created_at DESC
");

$reglamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<section class="page-card reglamentacion-estudiante">

    <div class="page-header">
        <h1>Reglamentación del Participante</h1>
        <p>En este apartado puedes visualizar los documentos oficiales disponibles para tu proceso académico.</p>
    </div>

    <?php if (empty($reglamentos)): ?>
        <div class="empty-state">
            Actualmente no hay reglamentos disponibles.
        </div>
    <?php else: ?>
        <div class="student-doc-grid">
            <?php foreach ($reglamentos as $r): ?>
                <div class="student-doc-card">

                    <div class="doc-icon">📄</div>

                    <div class="doc-content">
                        <span class="doc-tag">
                            <?= htmlspecialchars($r['tipo_programa']) ?> | Versión <?= htmlspecialchars($r['version']) ?>
                        </span>

                        <h3><?= htmlspecialchars($r['titulo']) ?></h3>

                        <p>
                            <?= nl2br(htmlspecialchars($r['descripcion'] ?: 'Documento oficial disponible para consulta.')) ?>
                        </p>

                        <small>
                            Archivo: <?= htmlspecialchars($r['archivo_original']) ?>
                        </small>

                        <a href="modules/estudiante/reglamentacion/ver.php?id=<?= $r['id'] ?>" target="_blank" class="btn-doc">
                            Ver documento PDF
                        </a>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</section>