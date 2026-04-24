<?php
require __DIR__ . '../../../../config/database.php';
session_start();
if (empty($_SESSION['user_email'])) {
    header('Location: /analyseM/app/view/auth/login.php');
    exit;
}

$user_name  = $_SESSION['user_name']  ?? 'Utilisateur';
$user_email = $_SESSION['user_email'] ?? '';
$user_role  = $_SESSION['user_role']  ?? 'user';

if ($user_role === 'admin') {
    header('Location: /analyseM/app/view/dashboard/bashboard.php');
    exit;
}

$message      = '';
$preview      = [];
$importReport = null;

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $message = ['type' => 'danger', 'text' => 'Requête invalide (CSRF).'];
    } else {
        $file     = $_FILES['csv_file'];
        $tmpPath  = $file['tmp_name'];
        $origName = $file['name'];
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if (!in_array($ext, ['csv', 'json'])) {
            $message = ['type' => 'danger', 'text' => 'Format non supporté. Utilisez CSV ou JSON.'];
        } else {
            // Start a transaction for safety
            $conn->begin_transaction();

            try {
                // 1. Create import log
                $stmtLog = $conn->prepare('INSERT INTO import_logs (rowsInserted) VALUES (0)');
                $stmtLog->execute();
                $importLogId = $conn->insert_id;
                $stmtLog->close();

                // 2. Create file record
                $stmtFile = $conn->prepare('INSERT INTO data_files (filename, format, uploadedBy, rowCount) VALUES (?, ?, ?, 0)');
                $format = strtoupper($ext);
                $stmtFile->bind_param('ssi', $origName, $format, $_SESSION['user_id']);
                $stmtFile->execute();
                $fileId = $conn->insert_id;
                $stmtFile->close();

                // Prepare the record insert statement
                $stmtRecord = $conn->prepare('INSERT INTO data_records (date, temperature, humidity, precipitation, windSpeed, sourceFile, importLogId) VALUES (?,?,?,?,?,?,?)');

                $inserted = 0;
                $errors   = 0;
                $skipped  = 0;

                // ── JSON IMPORT ─────────────────────────────────────
                if ($ext === 'json') {
                    $rows = json_decode(file_get_contents($tmpPath), true);
                    if (!is_array($rows)) {
                        throw new Exception('JSON invalide ou mal formé.');
                    }

                    foreach ($rows as $row) {
                        $date        = trim($row['date'] ?? $row['Date'] ?? '');
                        $temperature = $row['temperature'] ?? $row['Temperature'] ?? null;
                        $humidity    = $row['humidite'] ?? $row['humidity'] ?? $row['Humidite'] ?? null;
                        $precip      = $row['precipitation'] ?? $row['Precipitation'] ?? null;
                        $wind        = $row['vent'] ?? $row['windSpeed'] ?? $row['WindSpeed'] ?? null;

                        // Validation
                        if (empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                            $errors++;
                            continue;
                        }
                        if (!is_numeric($temperature) || $temperature < -90 || $temperature > 60) {
                            $errors++;
                            continue;
                        }
                        if (!is_numeric($humidity) || $humidity < 0 || $humidity > 100) {
                            $errors++;
                            continue;
                        }
                        if (!is_numeric($precip) || $precip < 0) {
                            $errors++;
                            continue;
                        }
                        if (!is_numeric($wind) || $wind < 0) {
                            $errors++;
                            continue;
                        }

                        try {
                            $stmtRecord->bind_param('sddddii', $date, $temperature, $humidity, $precip, $wind, $fileId, $importLogId);
                            $stmtRecord->execute();
                            $inserted++;
                            if (count($preview) < 5) {
                                $preview[] = compact('date', 'temperature', 'humidity', 'precip', 'wind');
                            }
                        } catch (Exception $e) {
                            $errors++;
                        }
                    }
                } else { // ── CSV IMPORT ──────────────────────────
                    $handle = fopen($tmpPath, 'r');
                    $firstLine = fgetcsv($handle);
                    if ($firstLine === false) {
                        throw new Exception('Fichier vide.');
                    }

                    $expectedHeaders = ['date', 'temperature', 'humidite', 'precipitation', 'vent'];
                    $headers = array_map('trim', array_map('strtolower', $firstLine));
                    if ($headers !== $expectedHeaders) {
                        throw new Exception('En-têtes invalides. Attendus : ' . implode(', ', $expectedHeaders));
                    }

                    $rowNum = 1;
                    while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                        $rowNum++;
                        $row = array_pad(array_map('trim', $row), 5, null);
                        [$date, $temperature, $humidity, $precip, $wind] = $row;

                        if (empty($date)) {
                            $skipped++;
                            continue;
                        }
                        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                            $errors++;
                            continue;
                        }
                        if (!is_numeric($temperature) || $temperature < -90 || $temperature > 60) {
                            $errors++;
                            continue;
                        }
                        if (!is_numeric($humidity) || $humidity < 0 || $humidity > 100) {
                            $errors++;
                            continue;
                        }
                        if (!is_numeric($precip) || $precip < 0) {
                            $errors++;
                            continue;
                        }
                        if (!is_numeric($wind) || $wind < 0) {
                            $errors++;
                            continue;
                        }

                        try {
                            $stmtRecord->bind_param('sddddii', $date, $temperature, $humidity, $precip, $wind, $fileId, $importLogId);
                            $stmtRecord->execute();
                            $inserted++;
                            if (count($preview) < 5) {
                                $preview[] = compact('date', 'temperature', 'humidity', 'precip', 'wind');
                            }
                        } catch (Exception $e) {
                            $errors++;
                        }
                    }
                    fclose($handle);
                }

                $stmtRecord->close();

                // 3. Update counts
                $totalRows = $inserted + $errors + $skipped;
                $conn->query("UPDATE import_logs SET rowsInserted = {$inserted} WHERE id = {$importLogId}");
                $conn->query("UPDATE data_files SET rowCount = {$inserted} WHERE id = {$fileId}");

                $conn->commit();

                $importReport = [
                    'file'     => $origName,
                    'total'    => $ext === 'json' ? count($rows) : ($rowNum - 1),
                    'inserted' => $inserted,
                    'errors'   => $errors,
                    'skipped'  => $skipped,
                ];
                $message = ['type' => 'success', 'text' => "Import terminé : {$inserted} insérées, {$errors} erreurs, {$skipped} ignorées."];
            } catch (Exception $e) {
                $conn->rollback();
                $message = ['type' => 'danger', 'text' => $e->getMessage()];
            }
        }
    }
}

// ── FETCH DATA FOR DASHBOARD ────────────────────────────────────
$totalRecords = 0;
$filesUploaded = 0;
$r = $conn->query('SELECT COUNT(*) AS cnt FROM data_records');
if ($r) $totalRecords = $r->fetch_assoc()['cnt'];
$r = $conn->query('SELECT COUNT(*) AS cnt FROM data_files');
if ($r) $filesUploaded = $r->fetch_assoc()['cnt'];

$monthlyAvg = [];
$r = $conn->query("
    SELECT DATE_FORMAT(date,'%Y-%m') AS month,
           ROUND(AVG(temperature),2) AS avg_temp,
           ROUND(AVG(humidity),2) AS avg_hum,
           ROUND(AVG(precipitation),2) AS avg_precip,
           ROUND(AVG(windSpeed),2) AS avg_wind,
           COUNT(*) AS cnt
    FROM data_records
    WHERE date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY month ORDER BY month ASC
");
if ($r) while ($row = $r->fetch_assoc()) $monthlyAvg[] = $row;

$tempPeaks = [];
$r = $conn->query("
    SELECT date, temperature, zscore 
    FROM (
        SELECT date, temperature,
               (temperature - (SELECT AVG(temperature) FROM data_records)) / 
               NULLIF((SELECT STDDEV(temperature) FROM data_records), 0) AS zscore
        FROM data_records
        WHERE temperature IS NOT NULL
    ) AS calculated
    WHERE ABS(zscore) > 2.5 
    ORDER BY ABS(zscore) DESC 
    LIMIT 10
");
if ($r) while ($row = $r->fetch_assoc()) $tempPeaks[] = $row;

$annualData = [];
$r = $conn->query("
    SELECT YEAR(date) AS yr,
           ROUND(AVG(temperature),2) AS avg_temp,
           ROUND(AVG(humidity),2) AS avg_hum,
           ROUND(SUM(precipitation),2) AS total_precip,
           ROUND(AVG(windSpeed),2) AS avg_wind,
           COUNT(*) AS cnt
    FROM data_records
    WHERE YEAR(date) >= YEAR(CURDATE()) - 4
    GROUP BY yr ORDER BY yr ASC
");
if ($r) while ($row = $r->fetch_assoc()) $annualData[] = $row;

$correlation = null;
$r = $conn->query("
    SELECT (COUNT(*)*SUM(temperature*humidity)-SUM(temperature)*SUM(humidity)) /
           NULLIF(SQRT((COUNT(*)*SUM(temperature*temperature)-SUM(temperature)*SUM(temperature)) *
                        (COUNT(*)*SUM(humidity*humidity)-SUM(humidity)*SUM(humidity))), 0) AS pearson
    FROM data_records
    WHERE temperature IS NOT NULL AND humidity IS NOT NULL
");
if ($r) {
    $row = $r->fetch_assoc();
    $correlation = $row['pearson'] !== null ? round((float)$row['pearson'], 4) : null;
}

$recentData = [];
$r = $conn->query('SELECT date, temperature, humidity, precipitation, windSpeed FROM data_records ORDER BY date DESC LIMIT 10');
if ($r) while ($row = $r->fetch_assoc()) $recentData[] = $row;

$chartLabels  = array_column($monthlyAvg, 'month');
$chartTemp    = array_column($monthlyAvg, 'avg_temp');
$chartHum     = array_column($monthlyAvg, 'avg_hum');
$chartPrecip  = array_column($monthlyAvg, 'avg_precip');
$annualLabels = array_column($annualData, 'yr');
$annualTemp   = array_column($annualData, 'avg_temp');
$annualPrecip = array_column($annualData, 'total_precip');
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analyse Météorologique</title>
    <link href="../../../node_modules/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --bg: #f8fafc;
            --card: #ffffff;
            --border: #e2e8f0;
            --text: #0f172a;
            --text-light: #64748b;
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #06b6d4;
            --accent: #8b5cf6;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            display: flex;
        }

        /* SIDEBAR */
        #sidebar {
            width: 280px;
            background: var(--card);
            border-right: 1px solid var(--border);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            display: flex;
            flex-direction: column;
            padding: 24px 20px;
            z-index: 100;
        }

        .sl {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            padding: 8px 12px;
            margin-bottom: 32px;
            border-radius: 12px;
            transition: all 0.2s;
        }

        .sl:hover {
            background: var(--bg);
        }

        .ic {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.4rem;
        }

        .nm {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--text);
        }

        .sb {
            font-size: 0.75rem;
            color: var(--text-light);
        }

        .slabel {
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-light);
            margin: 20px 12px 12px 12px;
        }

        .snav {
            display: flex;
            flex-direction: column;
            gap: 4px;
            flex: 1;
        }

        .snav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 16px;
            border-radius: 10px;
            text-decoration: none;
            color: var(--text-light);
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .snav a i {
            font-size: 1.2rem;
        }

        .snav a:hover {
            background: var(--bg);
            color: var(--primary);
        }

        .snav a.active {
            background: var(--primary);
            color: white;
        }

        .sft {
            border-top: 1px solid var(--border);
            padding-top: 20px;
            margin-top: auto;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sav {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--accent), var(--primary));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1rem;
        }

        .sun {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text);
        }

        .sue {
            font-size: 0.7rem;
            color: var(--text-light);
        }

        .slo {
            color: var(--text-light);
            font-size: 1.2rem;
            transition: color 0.2s;
        }

        .slo:hover {
            color: var(--danger);
        }

        /* MAIN */
        #main {
            margin-left: 280px;
            flex: 1;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .topbar {
            background: var(--card);
            border-bottom: 1px solid var(--border);
            padding: 20px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 99;
        }

        .topbar h4 {
            font-weight: 700;
            margin-bottom: 4px;
        }

        .breadcrumb {
            margin: 0;
            padding: 0;
            background: transparent;
            font-size: 0.8rem;
        }

        .tbtn {
            background: transparent;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 8px 12px;
            color: var(--text-light);
            transition: all 0.2s;
        }

        .tbtn:hover {
            background: var(--bg);
            border-color: var(--primary);
            color: var(--primary);
        }

        .content {
            padding: 32px;
            flex: 1;
        }

        /* KPI Cards */
        .kpi {
            background: var(--card);
            border-radius: 16px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            border: 1px solid var(--border);
            transition: all 0.2s;
        }

        .kpi:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.04);
        }

        .kic {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .kic.bl {
            background: var(--primary);
        }

        .kic.gr {
            background: var(--success);
        }

        .kic.am {
            background: var(--warning);
        }

        .kic.rd {
            background: var(--danger);
        }

        .klb {
            font-size: 0.75rem;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .kvl {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text);
            line-height: 1.2;
        }

        .ksb {
            font-size: 0.7rem;
            color: var(--text-light);
        }

        /* Tabs */
        .mtabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            border-bottom: 2px solid var(--border);
        }

        .mtab {
            padding: 10px 20px;
            font-weight: 600;
            color: var(--text-light);
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
        }

        .mtab i {
            margin-right: 8px;
        }

        .mtab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        .mpanel {
            display: none;
        }

        .mpanel.active {
            display: block;
        }

        /* Cards */
        .sc {
            background: var(--card);
            border-radius: 16px;
            border: 1px solid var(--border);
            overflow: hidden;
            margin-bottom: 24px;
        }

        .sc-h {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .sc-h h5 {
            margin: 0;
            font-weight: 600;
            font-size: 1rem;
        }

        .sc-b {
            padding: 20px;
        }

        /* Tables */
        .xt {
            width: 100%;
            font-size: 0.85rem;
        }

        .xt th {
            text-align: left;
            padding: 12px;
            background: var(--bg);
            font-weight: 600;
            color: var(--text-light);
            border-bottom: 1px solid var(--border);
        }

        .xt td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border);
        }

        .tb {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            border-radius: 6px;
            font-weight: 500;
        }

        .tb.hot {
            background: #fee2e2;
            color: #dc2626;
        }

        .tb.warm {
            background: #fed7aa;
            color: #ea580c;
        }

        .tb.mild {
            background: #d1fae5;
            color: #059669;
        }

        .tb.cold {
            background: #dbeafe;
            color: #2563eb;
        }

        .mb {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .b {
            flex: 1;
            height: 6px;
            background: var(--border);
            border-radius: 3px;
            overflow: hidden;
        }

        .f {
            height: 100%;
            background: var(--primary);
            border-radius: 3px;
        }

        .xa {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
        }

        .xa.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .xa.danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .xa.warning {
            background: #fed7aa;
            color: #92400e;
            border: 1px solid #fed7aa;
        }

        .dz {
            border: 2px dashed var(--border);
            border-radius: 12px;
            padding: 40px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            background: var(--bg);
        }

        .dz.over {
            border-color: var(--primary);
            background: #eff6ff;
        }

        .dz i {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 12px;
        }

        .btn-p {
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            width: 100%;
            margin-top: 16px;
            transition: all 0.2s;
        }

        .btn-p:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .btn-p:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        #fnb {
            display: none;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: var(--bg);
            border-radius: 8px;
            font-size: 0.8rem;
            margin-top: 12px;
        }

        .rtiles {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-top: 12px;
        }

        .rt {
            text-align: center;
            padding: 12px;
            border-radius: 10px;
        }

        .rt.b {
            background: #eff6ff;
        }

        .rt.s {
            background: #d1fae5;
        }

        .rt.d {
            background: #fee2e2;
        }

        .rt.w {
            background: #fed7aa;
        }

        .rv {
            font-size: 1.3rem;
            font-weight: 700;
        }

        .rl {
            font-size: 0.7rem;
            color: var(--text-light);
        }

        .emp {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-light);
        }

        .emp i {
            font-size: 2rem;
            margin-bottom: 12px;
        }

        .cg {
            text-align: center;
            padding: 20px;
        }

        .cv {
            font-size: 2rem;
            font-weight: 700;
        }

        .cl {
            font-size: 0.85rem;
            margin: 8px 0;
        }

        .cbw {
            background: var(--border);
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            margin: 12px 0;
        }

        .cb {
            height: 100%;
            transition: width 0.3s;
        }

        .sr {
            display: flex;
            justify-content: space-between;
            font-size: 0.75rem;
            padding: 4px 0;
        }

        .pi {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }

        .pi:last-child {
            border-bottom: none;
        }

        .cw {
            height: 300px;
            position: relative;
        }

        .table-responsive {
            overflow-x: auto;
        }

        @media (max-width: 768px) {
            #sidebar {
                width: 80px;
                padding: 20px 12px;
            }

            .sl .nm,
            .sl .sb,
            .slabel,
            .sun,
            .sue,
            .snav a span {
                display: none;
            }

            .sav {
                margin: 0 auto;
            }

            #main {
                margin-left: 80px;
            }

            .content {
                padding: 20px;
            }
        }
    </style>
</head>

<body>

    <!-- SIDEBAR -->
    <aside id="sidebar">
        <a href="#" class="sl">
            <div class="ic"><i class="bi bi-cloud-sun-fill"></i></div>
            <div>
                <div class="nm">MétéoAnalyse</div>
                <div class="sb">Système d'analyse</div>
            </div>
        </a>
        <div class="slabel">Modules</div>
        <nav class="snav">
            <a href="#" class="active" id="snav-import" onclick="showTab('import');return false"><i class="bi bi-cloud-upload"></i> <span>Collecte & Stockage</span></a>
            <a href="#" id="snav-stats" onclick="showTab('stats');return false"><i class="bi bi-calculator"></i> <span>Analyse Statistique</span></a>
            <a href="#" id="snav-viz" onclick="showTab('viz');return false"><i class="bi bi-graph-up"></i> <span>Visualisation</span></a>
        </nav>
        <div class="sft">
            <div class="sav"><?= strtoupper(substr($user_name, 0, 1)) ?></div>
            <div style="flex:1;min-width:0">
                <div class="sun"><?= htmlspecialchars($user_name) ?></div>
                <div class="sue"><?= htmlspecialchars($user_email) ?></div>
            </div>
            <a href="/analyseM/app/view/auth/logout.php" class="slo" title="Déconnexion"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </aside>

    <!-- MAIN -->
    <div id="main">
        <header class="topbar">
            <div>
                <h4 id="page-title">Collecte &amp; Stockage</h4>
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item text-muted">Dashboard</li>
                        <li class="breadcrumb-item active" id="bc">Import</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex gap-2">
                <button class="tbtn" onclick="location.reload()" title="Rafraîchir"><i class="bi bi-arrow-clockwise"></i></button>
            </div>
        </header>

        <main class="content">

            <!-- KPIs -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-xl-3">
                    <div class="kpi">
                        <div class="kic bl"><i class="bi bi-database-fill"></i></div>
                        <div>
                            <div class="klb">Mesures totales</div>
                            <div class="kvl"><?= number_format($totalRecords) ?></div>
                            <div class="ksb">Enregistrements</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="kpi">
                        <div class="kic gr"><i class="bi bi-file-earmark-check-fill"></i></div>
                        <div>
                            <div class="klb">Fichiers importés</div>
                            <div class="kvl"><?= $filesUploaded ?></div>
                            <div class="ksb">CSV / JSON</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="kpi">
                        <div class="kic am"><i class="bi bi-calendar3"></i></div>
                        <div>
                            <div class="klb">Mois analysés</div>
                            <div class="kvl"><?= count($monthlyAvg) ?></div>
                            <div class="ksb">12 derniers mois</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="kpi">
                        <div class="kic rd"><i class="bi bi-exclamation-triangle-fill"></i></div>
                        <div>
                            <div class="klb">Pics détectés</div>
                            <div class="kvl"><?= count($tempPeaks) ?></div>
                            <div class="ksb">Anomalies |z|>2.5</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Module Tabs -->
            <div class="mtabs">
                <div class="mtab active" id="tab-import" onclick="showTab('import')"><i class="bi bi-cloud-arrow-up"></i> A – Collecte &amp; Stockage</div>
                <div class="mtab" id="tab-stats" onclick="showTab('stats')"><i class="bi bi-calculator"></i> B – Analyse Statistique</div>
                <div class="mtab" id="tab-viz" onclick="showTab('viz')"><i class="bi bi-bar-chart-fill"></i> C – Visualisation</div>
            </div>

            <!-- PANEL A : IMPORT -->
            <div class="mpanel active" id="panel-import">
                <div class="row g-4">
                    <div class="col-lg-5">
                        <div class="sc">
                            <div class="sc-h">
                                <h5><i class="bi bi-upload text-primary"></i> Importer un fichier</h5><span style="font-size:11.5px;color:var(--text-light)">CSV ou JSON</span>
                            </div>
                            <div class="sc-b">
                                <?php if ($message): ?>
                                    <div class="xa <?= htmlspecialchars($message['type']) ?>">
                                        <i class="bi bi-<?= $message['type'] === 'success' ? 'check-circle' : ($message['type'] === 'warning' ? 'exclamation-circle' : 'x-circle') ?>"></i>
                                        <?= htmlspecialchars($message['text']) ?>
                                    </div>
                                <?php endif; ?>
                                <form method="POST" enctype="multipart/form-data" onsubmit="document.getElementById('importBtn').disabled=true; document.getElementById('importSpinner').classList.remove('d-none');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <div class="dz" id="dz" onclick="document.getElementById('fi').click()">
                                        <i class="bi bi-file-earmark-spreadsheet"></i>
                                        <p><strong>Cliquez</strong> pour sélectionner ou <strong>glissez</strong> ici</p>
                                        <p style="font-size:12px;margin-top:4px;opacity:.7">Colonnes : date · temperature · humidite · precipitation · vent</p>
                                    </div>
                                    <div id="fnb"><i class="bi bi-file-check"></i><span id="fnt">—</span></div>
                                    <input type="file" id="fi" name="csv_file" accept=".csv,.json" class="d-none" required>
                                    <button type="submit" class="btn-p" id="importBtn">
                                        <i class="bi bi-upload"></i> Lancer l'import
                                        <span id="importSpinner" class="d-none spinner-border spinner-border-sm ms-2" role="status" aria-hidden="true"></span>
                                    </button>
                                </form>

                                <?php if ($importReport): ?>
                                    <div class="mt-3">
                                        <div style="font-size:12.5px;font-weight:600;margin-bottom:8px;color:var(--text-light)"><i class="bi bi-file-text me-1"></i><?= htmlspecialchars($importReport['file']) ?></div>
                                        <div class="rtiles">
                                            <div class="rt b">
                                                <div class="rv"><?= $importReport['total'] ?></div>
                                                <div class="rl">Total</div>
                                            </div>
                                            <div class="rt s">
                                                <div class="rv"><?= $importReport['inserted'] ?></div>
                                                <div class="rl">Insérées</div>
                                            </div>
                                            <div class="rt d">
                                                <div class="rv"><?= $importReport['errors'] ?></div>
                                                <div class="rl">Erreurs</div>
                                            </div>
                                            <div class="rt w">
                                                <div class="rv"><?= $importReport['skipped'] ?></div>
                                                <div class="rl">Ignorées</div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($preview)): ?>
                                    <div class="mt-4">
                                        <div style="font-size:13px;font-weight:600;margin-bottom:10px"><i class="bi bi-eye text-muted me-1"></i> Aperçu (5 premières lignes)</div>
                                        <div class="table-responsive" style="border-radius:9px;border:1px solid var(--border);overflow:hidden">
                                            <table class="xt">
                                                <thead>
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>Temp</th>
                                                        <th>Hum.</th>
                                                        <th>Précip.</th>
                                                        <th>Vent</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($preview as $r): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($r['date']) ?></td>
                                                            <td><?= htmlspecialchars($r['temperature']) ?> °C</td>
                                                            <td><?= htmlspecialchars($r['humidity']) ?> %</td>
                                                            <td><?= htmlspecialchars($r['precip']) ?> mm</td>
                                                            <td><?= htmlspecialchars($r['wind']) ?> km/h</td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-7">
                        <div class="sc">
                            <div class="sc-h">
                                <h5><i class="bi bi-table text-primary"></i> Données récentes</h5><span style="font-size:11.5px;color:var(--text-light)">10 derniers relevés</span>
                            </div>
                            <?php if (!empty($recentData)): ?>
                                <div class="table-responsive">
                                    <table class="xt">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Température</th>
                                                <th>Humidité</th>
                                                <th>Précip.</th>
                                                <th>Vent</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentData as $r):
                                                $t = (float)$r['temperature'];
                                                $cls = $t >= 35 ? 'hot' : ($t >= 25 ? 'warm' : ($t >= 10 ? 'mild' : 'cold'));
                                            ?>
                                                <tr>
                                                    <td style="font-weight:600"><?= htmlspecialchars($r['date']) ?></td>
                                                    <td><span class="tb <?= $cls ?>"><i class="bi bi-thermometer"></i><?= htmlspecialchars($r['temperature']) ?> °C</span></td>
                                                    <td>
                                                        <div class="mb">
                                                            <div class="b">
                                                                <div class="f" style="width:<?= min(100, (float)$r['humidity']) ?>%"></div>
                                                            </div><span><?= htmlspecialchars($r['humidity']) ?>%</span>
                                                        </div>
                                                    </td>
                                                    <td><?= htmlspecialchars($r['precipitation']) ?> mm</td>
                                                    <td><?= htmlspecialchars($r['windSpeed']) ?> km/h</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="emp"><i class="bi bi-cloud-slash"></i>
                                    <p>Aucune donnée. Importez un fichier pour commencer.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PANEL B : ANALYSE STATISTIQUE -->
            <div class="mpanel" id="panel-stats">
                <div class="row g-4">
                    <div class="col-lg-7">
                        <div class="sc">
                            <div class="sc-h">
                                <h5><i class="bi bi-calendar-month text-primary"></i> Moyennes mensuelles</h5><span style="font-size:11.5px;color:var(--text-light)">12 derniers mois</span>
                            </div>
                            <?php if (!empty($monthlyAvg)): ?>
                                <div class="table-responsive">
                                    <table class="xt">
                                        <thead>
                                            <tr>
                                                <th>Mois</th>
                                                <th>Temp. moy. (°C)</th>
                                                <th>Humidité (%)</th>
                                                <th>Précip. (mm)</th>
                                                <th>Vent (km/h)</th>
                                                <th>N</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($monthlyAvg as $m):
                                                $tc = (float)$m['avg_temp'] >= 25 ? 'warm' : ((float)$m['avg_temp'] < 10 ? 'cold' : 'mild');
                                            ?>
                                                <tr>
                                                    <td style="font-weight:600"><?= htmlspecialchars($m['month']) ?></td>
                                                    <td><span class="tb <?= $tc ?>"><?= htmlspecialchars($m['avg_temp']) ?></span></td>
                                                    <td><?= htmlspecialchars($m['avg_hum']) ?></td>
                                                    <td><?= htmlspecialchars($m['avg_precip']) ?></td>
                                                    <td><?= htmlspecialchars($m['avg_wind']) ?></td>
                                                    <td style="color:var(--text-light)"><?= $m['cnt'] ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?><div class="emp"><i class="bi bi-calendar-x"></i>
                                    <p>Aucune donnée mensuelle disponible.</p>
                                </div><?php endif; ?>
                        </div>
                    </div>

                    <div class="col-lg-5 d-flex flex-column gap-4">
                        <div class="sc">
                            <div class="sc-h">
                                <h5><i class="bi bi-diagram-3 text-primary"></i> Corrélation Temp / Humidité</h5>
                            </div>
                            <div class="sc-b">
                                <?php if ($correlation !== null):
                                    $abs = abs($correlation);
                                    $pct = round(($correlation + 1) / 2 * 100);
                                    $col = $abs >= .7 ? ($correlation > 0 ? '#059669' : '#dc2626') : ($abs >= .4 ? '#d97706' : '#6b7280');
                                    $interp = $abs >= .7 ? ($correlation > 0 ? 'Corrélation forte positive' : 'Corrélation forte négative') : ($abs >= .4 ? 'Corrélation modérée' : 'Corrélation faible');
                                ?>
                                    <div class="cg">
                                        <div class="cv" style="color:<?= $col ?>"><?= number_format($correlation, 4) ?></div>
                                        <div class="cl"><?= $interp ?> (r de Pearson)</div>
                                        <div class="cbw">
                                            <div class="cb" style="width:<?= $pct ?>%;background:<?= $col ?>"></div>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <div class="sr"><span>|r| ≥ 0.7</span><span>Forte</span></div>
                                        <div class="sr"><span>0.4 ≤ |r| &lt; 0.7</span><span>Modérée</span></div>
                                        <div class="sr"><span>|r| &lt; 0.4</span><span>Faible</span></div>
                                    </div>
                                <?php else: ?><div class="emp"><i class="bi bi-slash-circle"></i>
                                        <p>Données insuffisantes.</p>
                                    </div><?php endif; ?>
                            </div>
                        </div>

                        <div class="sc" style="flex:1">
                            <div class="sc-h">
                                <h5><i class="bi bi-exclamation-triangle text-warning"></i> Pics de température</h5><span style="font-size:11.5px;color:var(--text-light)">|z| > 2.5</span>
                            </div>
                            <div class="sc-b" style="padding:16px 20px">
                                <?php if (!empty($tempPeaks)): foreach ($tempPeaks as $p): $hi = (float)$p['zscore'] > 0; ?>
                                        <div class="pi">
                                            <div>
                                                <div style="font-size:13px;font-weight:600"><?= htmlspecialchars($p['date']) ?></div>
                                                <div style="font-size:11.5px;color:var(--text-light)">z = <?= round((float)$p['zscore'], 2) ?></div>
                                            </div>
                                            <span class="tb <?= $hi ? 'hot' : 'cold' ?>"><i class="bi bi-thermometer-<?= $hi ? 'high' : 'low' ?>"></i><?= htmlspecialchars($p['temperature']) ?> °C</span>
                                        </div>
                                    <?php endforeach;
                                else: ?><div class="emp" style="padding:28px 0"><i class="bi bi-check-circle" style="color:var(--success)"></i>
                                        <p>Aucun pic détecté.</p>
                                    </div><?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="sc">
                            <div class="sc-h">
                                <h5><i class="bi bi-calendar-range text-primary"></i> Comparaison annuelle</h5><span style="font-size:11.5px;color:var(--text-light)">5 dernières années</span>
                            </div>
                            <?php if (!empty($annualData)): ?>
                                <div class="table-responsive">
                                    <table class="xt">
                                        <thead>
                                            <tr>
                                                <th>Année</th>
                                                <th>Temp. moy. (°C)</th>
                                                <th>Δ Temp.</th>
                                                <th>Humidité moy. (%)</th>
                                                <th>Total précip. (mm)</th>
                                                <th>Vent moy. (km/h)</th>
                                                <th>Relevés</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $prev = null;
                                            foreach ($annualData as $a):
                                                $delta = $prev !== null ? round((float)$a['avg_temp'] - (float)$prev['avg_temp'], 2) : null;
                                                $dc = $delta === null ? '' : ($delta > 0 ? 'color:#ef4444' : 'color:#8b5cf6');
                                            ?>
                                                <tr>
                                                    <td style="font-weight:700"><?= $a['yr'] ?></td>
                                                    <td><?= htmlspecialchars($a['avg_temp']) ?></td>
                                                    <td style="<?= $dc ?>;font-weight:600"><?= $delta !== null ? ($delta > 0 ? '▲' : '▼') . ' ' . abs($delta) : '—' ?></td>
                                                    <td><?= htmlspecialchars($a['avg_hum']) ?></td>
                                                    <td><?= htmlspecialchars($a['total_precip']) ?></td>
                                                    <td><?= htmlspecialchars($a['avg_wind']) ?></td>
                                                    <td style="color:var(--text-light)"><?= $a['cnt'] ?></td>
                                                </tr>
                                            <?php $prev = $a;
                                            endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?><div class="emp"><i class="bi bi-calendar-x"></i>
                                    <p>Données annuelles insuffisantes.</p>
                                </div><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PANEL C : VISUALISATION -->
            <div class="mpanel" id="panel-viz">
                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="sc">
                            <div class="sc-h">
                                <h5><i class="bi bi-graph-up text-primary"></i> Courbes temporelles</h5>
                                <div class="d-flex gap-1">
                                    <button class="tbtn" onclick="toggleDS(0)" title="Température"><i class="bi bi-thermometer" style="color:#2563eb"></i></button>
                                    <button class="tbtn" onclick="toggleDS(1)" title="Humidité"><i class="bi bi-droplet" style="color:#0284c7"></i></button>
                                    <button class="tbtn" onclick="toggleDS(2)" title="Précipitations"><i class="bi bi-cloud-rain" style="color:#6366f1"></i></button>
                                </div>
                            </div>
                            <div class="sc-b">
                                <?php if (!empty($monthlyAvg)): ?>
                                    <div class="cw"><canvas id="lineChart"></canvas></div>
                                <?php else: ?><div class="emp"><i class="bi bi-graph-up-arrow"></i>
                                        <p>Importez des données pour afficher les courbes.</p>
                                    </div><?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="sc">
                            <div class="sc-h">
                                <h5><i class="bi bi-bar-chart text-primary"></i> Histogramme</h5>
                                <select id="hparam" class="form-select form-select-sm" style="width:auto;font-size:12px" onchange="buildHist(this.value)">
                                    <option value="temp">Température</option>
                                    <option value="hum">Humidité</option>
                                    <option value="precip">Précipitations</option>
                                </select>
                            </div>
                            <div class="sc-b">
                                <?php if (!empty($monthlyAvg)): ?>
                                    <div class="cw"><canvas id="histChart"></canvas></div>
                                <?php else: ?><div class="emp"><i class="bi bi-bar-chart"></i>
                                        <p>Aucune donnée.</p>
                                    </div><?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="sc">
                            <div class="sc-h">
                                <h5><i class="bi bi-bar-chart-steps text-primary"></i> Comparaison annuelle</h5>
                            </div>
                            <div class="sc-b">
                                <?php if (!empty($annualData)): ?><div class="cw"><canvas id="annChart"></canvas></div>
                                <?php else: ?><div class="emp"><i class="bi bi-calendar-x"></i>
                                        <p>Données insuffisantes.</p>
                                    </div><?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="sc">
                            <div class="sc-h">
                                <h5><i class="bi bi-diagram-3 text-primary"></i> Scatter – Temp / Humidité</h5><?php if ($correlation !== null): ?><span class="tb mild">r = <?= $correlation ?></span><?php endif; ?>
                            </div>
                            <div class="sc-b">
                                <?php if (!empty($monthlyAvg)): ?><div class="cw"><canvas id="scChart"></canvas></div>
                                <?php else: ?><div class="emp"><i class="bi bi-slash-circle"></i>
                                        <p>Données insuffisantes.</p>
                                    </div><?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../../../node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const labels = <?= json_encode($chartLabels) ?>;
        const tempData = <?= json_encode(array_map('floatval', $chartTemp)) ?>;
        const humData = <?= json_encode(array_map('floatval', $chartHum)) ?>;
        const precipData = <?= json_encode(array_map('floatval', $chartPrecip)) ?>;
        const annLabels = <?= json_encode($annualLabels) ?>;
        const annTemp = <?= json_encode(array_map('floatval', $annualTemp)) ?>;
        const annPrecip = <?= json_encode(array_map('floatval', $annualPrecip)) ?>;

        const tabMeta = {
            import: {
                title: 'Collecte & Stockage',
                bread: 'Import'
            },
            stats: {
                title: 'Analyse Statistique',
                bread: 'Statistiques'
            },
            viz: {
                title: 'Visualisation',
                bread: 'Visualisation'
            }
        };

        function showTab(id) {
            ['import', 'stats', 'viz'].forEach(t => {
                document.getElementById('tab-' + t).classList.toggle('active', t === id);
                document.getElementById('panel-' + t).classList.toggle('active', t === id);
                document.getElementById('snav-' + t).classList.toggle('active', t === id);
            });
            document.getElementById('page-title').innerHTML = tabMeta[id].title;
            document.getElementById('bc').textContent = tabMeta[id].bread;
            if (id === 'viz') setTimeout(initCharts, 60);
        }

        const fi = document.getElementById('fi'),
            dz = document.getElementById('dz'),
            fnb = document.getElementById('fnb'),
            fnt = document.getElementById('fnt');
        fi.addEventListener('change', () => {
            if (fi.files.length) {
                fnt.textContent = fi.files[0].name;
                fnb.style.display = 'flex';
            }
        });
        dz.addEventListener('dragover', e => {
            e.preventDefault();
            dz.classList.add('over');
        });
        dz.addEventListener('dragleave', () => dz.classList.remove('over'));
        dz.addEventListener('drop', e => {
            e.preventDefault();
            dz.classList.remove('over');
            if (e.dataTransfer.files.length) {
                fi.files = e.dataTransfer.files;
                fnt.textContent = e.dataTransfer.files[0].name;
                fnb.style.display = 'flex';
            }
        });

        Chart.defaults.font.family = "'DM Sans',sans-serif";
        Chart.defaults.font.size = 12;
        Chart.defaults.color = '#6b7280';

        let lineC, histC, annC, scC, ready = false;

        function initCharts() {
            if (ready) return;
            ready = true;

            const lel = document.getElementById('lineChart');
            if (lel && labels.length) {
                lineC = new Chart(lel, {
                    type: 'line',
                    data: {
                        labels,
                        datasets: [{
                                label: 'Température (°C)',
                                data: tempData,
                                borderColor: '#2563eb',
                                backgroundColor: 'rgba(37,99,235,.08)',
                                tension: .4,
                                fill: true,
                                pointRadius: 4,
                                pointHoverRadius: 6
                            },
                            {
                                label: 'Humidité (%)',
                                data: humData,
                                borderColor: '#0284c7',
                                backgroundColor: 'rgba(2,132,199,.08)',
                                tension: .4,
                                fill: true,
                                pointRadius: 4,
                                pointHoverRadius: 6
                            },
                            {
                                label: 'Précipitations (mm)',
                                data: precipData,
                                borderColor: '#6366f1',
                                backgroundColor: 'rgba(99,102,241,.08)',
                                tension: .4,
                                fill: true,
                                pointRadius: 4,
                                pointHoverRadius: 6
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false
                        },
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    boxWidth: 12,
                                    padding: 14
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: {
                                    color: '#f3f4f6'
                                }
                            },
                            y: {
                                grid: {
                                    color: '#f3f4f6'
                                }
                            }
                        }
                    }
                });
            }

            buildHist('temp');

            const ael = document.getElementById('annChart');
            if (ael && annLabels.length) {
                annC = new Chart(ael, {
                    type: 'bar',
                    data: {
                        labels: annLabels,
                        datasets: [{
                                label: 'Temp. moy. (°C)',
                                data: annTemp,
                                backgroundColor: 'rgba(37,99,235,.75)',
                                borderRadius: 6,
                                yAxisID: 'y'
                            },
                            {
                                label: 'Précip. totales (mm)',
                                data: annPrecip,
                                backgroundColor: 'rgba(99,102,241,.6)',
                                borderRadius: 6,
                                yAxisID: 'y1'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    boxWidth: 12
                                }
                            }
                        },
                        scales: {
                            y: {
                                position: 'left',
                                grid: {
                                    color: '#f3f4f6'
                                },
                                title: {
                                    display: true,
                                    text: '°C'
                                }
                            },
                            y1: {
                                position: 'right',
                                grid: {
                                    drawOnChartArea: false
                                },
                                title: {
                                    display: true,
                                    text: 'mm'
                                }
                            }
                        }
                    }
                });
            }

            const sel = document.getElementById('scChart');
            if (sel && tempData.length) {
                const pts = tempData.map((t, i) => ({
                    x: t,
                    y: humData[i]
                }));
                scC = new Chart(sel, {
                    type: 'scatter',
                    data: {
                        datasets: [{
                            label: 'Temp vs Humidité',
                            data: pts,
                            backgroundColor: 'rgba(37,99,235,.5)',
                            pointRadius: 5,
                            pointHoverRadius: 7
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            x: {
                                title: {
                                    display: true,
                                    text: 'Température (°C)'
                                },
                                grid: {
                                    color: '#f3f4f6'
                                }
                            },
                            y: {
                                title: {
                                    display: true,
                                    text: 'Humidité (%)'
                                },
                                grid: {
                                    color: '#f3f4f6'
                                }
                            }
                        }
                    }
                });
            }
        }

        function buildHist(p) {
            const el = document.getElementById('histChart');
            if (!el) return;
            const src = p === 'temp' ? tempData : (p === 'hum' ? humData : precipData);
            if (!src.length) return;
            if (histC) histC.destroy();
            const mn = Math.min(...src),
                mx = Math.max(...src);
            const bins = Math.max(5, Math.ceil(1 + 3.322 * Math.log10(src.length)));
            const step = (mx - mn) / bins;
            const counts = Array(bins).fill(0);
            const bLabels = [];
            for (let i = 0; i < bins; i++) bLabels.push((mn + i * step).toFixed(1) + '–' + (mn + (i + 1) * step).toFixed(1));
            src.forEach(v => {
                let i = Math.min(bins - 1, Math.floor((v - mn) / step));
                counts[i]++;
            });
            histC = new Chart(el, {
                type: 'bar',
                data: {
                    labels: bLabels,
                    datasets: [{
                        label: 'Fréquence',
                        data: counts,
                        backgroundColor: 'rgba(37,99,235,.7)',
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        x: {
                            ticks: {
                                maxRotation: 45,
                                font: {
                                    size: 10
                                }
                            },
                            grid: {
                                color: '#f3f4f6'
                            }
                        },
                        y: {
                            grid: {
                                color: '#f3f4f6'
                            }
                        }
                    }
                }
            });
        }

        function toggleDS(i) {
            if (!lineC) return;
            lineC.data.datasets[i].hidden = !lineC.data.datasets[i].hidden;
            lineC.update();
        }
    </script>
</body>

</html>