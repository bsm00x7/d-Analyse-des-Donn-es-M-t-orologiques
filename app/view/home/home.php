<?php
require __DIR__ . '/../../../config/database.php';
session_start();

// Check if the user is logged in

$user_name  = $_SESSION['user_name']  ?? 'Utilisateur';
$user_email = $_SESSION['user_email'] ?? '';
$user_role  = $_SESSION['user_role']  ?? 'user'; // 'admin' or 'user'

$message      = '';
$preview      = [];
$importReport = null;

// CSRF token for import form 
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle CSV Import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $message = '❌ Requête invalide.';
    } else {

        $file     = $_FILES['csv_file'];
        $tmpPath  = $file['tmp_name'];
        $origName = $file['name'];
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        // validate real type .csv
        $finfo        = new finfo(FILEINFO_MIME_TYPE);
        $mimeType     = $finfo->file($tmpPath);
        $allowedMimes = ['text/plain', 'text/csv', 'application/csv'];

        if (!in_array($mimeType, $allowedMimes) || $ext !== 'csv') {
            $message = '❌ Erreur format : le fichier doit être un CSV valide.';

        } else {
            $handle    = fopen($tmpPath, 'r');
            $firstLine = fgetcsv($handle);

            // empty file
            if ($firstLine === false || $firstLine === null) {
                $message = '⚠️ Fichier vide : aucune donnée à importer.';
                fclose($handle);

            } else {
                $expectedHeaders = ['station', 'date', 'temperature', 'humidite', 'precipitation', 'vent'];
                $headers         = array_map('trim', array_map('strtolower', $firstLine));

                if ($headers !== $expectedHeaders) {
                    $message = '❌ En-têtes invalides. Attendus : ' . implode(', ', $expectedHeaders);
                    fclose($handle);

                } else {
                    // import valid CSV
                    $inserted = 0;
                    $errors   = 0;
                    $skipped  = 0;
                    $rowNum   = 1;

                    $stmt = $conn->prepare('
                        INSERT INTO data_records (date, temperature, humidity, precipitation, windSpeed)
                        VALUES (?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                            temperature   = VALUES(temperature),
                            humidity      = VALUES(humidity),
                            precipitation = VALUES(precipitation),
                            windSpeed     = VALUES(windSpeed)
                    ');

                    while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                        $rowNum++;
                        if (count($row) !== 5) { $errors++; continue; }

                        [$date, $temp, $humidity, $precip, $wind] = array_map('trim', $row);

                        // validate & clean
                        if (empty($date))                              { $skipped++; continue; }
                        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date))                  { $errors++;  continue; }
                        if (!is_numeric($temp)     || $temp < -90     || $temp > 60)      { $errors++;  continue; }
                        if (!is_numeric($humidity) || $humidity < 0   || $humidity > 100) { $errors++;  continue; }
                        if (!is_numeric($precip)   || $precip < 0)                        { $errors++;  continue; }
                        if (!is_numeric($wind)     || $wind < 0)                          { $errors++;  continue; }

                        try {
                            $stmt->bind_param('ssdddd', $date, $temp, $humidity, $precip, $wind);
                            $stmt->execute();
                            $inserted++;
                            if (count($preview) < 5) {
                                $preview[] = compact('station', 'date', 'temp', 'humidity', 'precip', 'wind');
                            }
                        } catch (Exception $e) {
                            $errors++;
                        }
                    }

                    $stmt->close();
                    fclose($handle);

                    $importReport = [
                        'file'     => $origName,
                        'total'    => $rowNum - 1,
                        'inserted' => $inserted,
                        'errors'   => $errors,
                        'skipped'  => $skipped,
                    ];

                    $message = "✅ Import terminé : {$inserted} mesures insérées, {$errors} erreurs, {$skipped} ignorées.";
                }
            }
        }
    }
}

// Load recent data for display
$recentData = [];
$result = $conn->query('SELECT date, temperature, humidity, precipitation, windSpeed FROM  data_records ORDER BY date DESC LIMIT 10');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recentData[] = $row;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home</title>
     <link href="../../../node_modules/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <header>
    <h1>🌤 Système d'Analyse Météorologique</h1>
    <p>Bienvenue, <strong><?= htmlspecialchars($user_name) ?></strong>
       (<?= htmlspecialchars($user_email) ?>)
       &nbsp;|&nbsp;
       <a href="/analyseM/logout.php">Déconnexion</a>
    </p>
</header>
<!-- ── Import Form ────────────────────────────────────────────── -->
<section>
    <h2>📂 Importer des données </h2>
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="file" name="csv_file" accept=".csv" required>
        <button type="submit">Importer</button>
    </form>

    <?php if ($message): ?>
        <p><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <?php if ($importReport): ?>
        <h3>Rapport d'import</h3>
        <table border="1">
            <tr><td>Fichier</td>       <td><?= htmlspecialchars($importReport['file'])     ?></td></tr>
            <tr><td>Total lignes</td>  <td><?= $importReport['total']    ?></td></tr>
            <tr><td>Insérées</td>      <td><?= $importReport['inserted'] ?></td></tr>
            <tr><td>Erreurs</td>       <td><?= $importReport['errors']   ?></td></tr>
            <tr><td>Ignorées</td>      <td><?= $importReport['skipped']  ?></td></tr>
        </table>
    <?php endif; ?>

    <?php if (!empty($preview)): ?>
        <h3>Aperçu (5 premières lignes)</h3>
        <table border="1">
            <tr>
                <th>Station</th><th>Date</th><th>Temp (°C)</th>
                <th>Humidité (%)</th><th>Précip (mm)</th><th>Vent (km/h)</th>
            </tr>
            <?php foreach ($preview as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['station'])  ?></td>
                <td><?= htmlspecialchars($r['date'])     ?></td>
                <td><?= htmlspecialchars($r['temp'])     ?></td>
                <td><?= htmlspecialchars($r['humidity']) ?></td>
                <td><?= htmlspecialchars($r['precip'])   ?></td>
                <td><?= htmlspecialchars($r['wind'])     ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</section>
<!-- ── Recent Data (all users) ─────────────────────────────────────────────── -->
<section>
    <h2>📊 Dernières données météo</h2>
    <?php if (!empty($recentData)): ?>
    <table border="1">
        <tr>
            <th>Station</th><th>Date</th><th>Temp (°C)</th>
            <th>Humidité (%)</th><th>Précip (mm)</th><th>Vent (km/h)</th>
        </tr>
        <?php foreach ($recentData as $r): ?>
        <tr>
            <td><?= htmlspecialchars($r['station'])       ?></td>
            <td><?= htmlspecialchars($r['date'])          ?></td>
            <td><?= htmlspecialchars($r['temperature'])   ?></td>
            <td><?= htmlspecialchars($r['humidite'])      ?></td>
            <td><?= htmlspecialchars($r['precipitation']) ?></td>
            <td><?= htmlspecialchars($r['vent'])          ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php else: ?>
        <p>Aucune donnée disponible.</p>
    <?php endif; ?>
</section>
</body>

</html>