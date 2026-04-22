<?php
require(__DIR__ . '/../../../config/database.php');
session_start();

/* ========= FIX SESSION ========= */
$user_name  = $_SESSION['user_name'] ?? 'admin';
$user_email = $_SESSION['user_email'] ?? 'admin@admin.com';

/* ========= API (SAME FILE) ========= */
if (isset($_GET['action'])) {

    header('Content-Type: application/json');

    /* ===== USERS ===== */
    if ($_GET['action'] === 'get_users') {
        $result = $conn->query("SELECT id, name, email, role, isActive FROM users");

        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = [
                "id" => $row['id'],
                "name" => $row['name'],
                "email" => $row['email'],
                "role" => $row['role'],
                "status" => $row['isActive'] ? 'active' : 'suspended',
                "last" => "—",
                "created" => "—",
                "avatar" => strtoupper($row['name'][0]),
                "color" => "linear-gradient(135deg,#4fd1c5,#63b3ed)"
            ];
        }

        echo json_encode($users);
        exit;
    }

    /* ===== FILES ===== */
    if ($_GET['action'] === 'get_files') {
        $result = $conn->query("SELECT * FROM data_files");

        $files = [];
        while ($row = $result->fetch_assoc()) {
            $files[] = $row;
        }

        echo json_encode($files);
        exit;
    }
}

/* ========= COUNT USERS ========= */
$stmt = $conn->prepare("SELECT COUNT(*) FROM users");
$stmt->execute();
$stmt->bind_result($number_of_user);
$stmt->fetch();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>

    <link rel="stylesheet" href="../../../style/dashboradStyle.css">

</head>

<body>

    <div class="shell">

        <!-- ===== SIDEBAR ===== -->
        <aside class="sidebar">

            <div class="brand">
                <div class="brand-icon">A</div>
                <div>
                    <div class="brand-name">AdminCore</div>
                </div>
            </div>

            <a class="nav-link active" onclick="navigate('home', this)">Home</a>
            <a class="nav-link" onclick="navigate('users', this)">Users</a>
            <a class="nav-link" onclick="navigate('files', this)">Files</a>

            <div class="sidebar-bottom">
                <div class="user-card">
                    <div class="user-avatar"><?= strtoupper($user_name[0]) ?></div>
                    <div>
                        <div><?= $user_name ?></div>
                        <div><?= $user_email ?></div>
                    </div>
                </div>
            </div>

        </aside>

        <!-- ===== MAIN ===== -->
        <div class="main">

            <div class="content">

                <!-- HOME -->
                <div class="page active" id="page-home">
                    <h2>Total Users: <?= $number_of_user ?></h2>
                </div>

                <!-- USERS -->
                <div class="page" id="page-users">
                    <h2>Users</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="user-table"></tbody>
                    </table>
                </div>

                <!-- FILES -->
                <div class="page" id="page-files">
                    <h2>Files</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Filename</th>
                                <th>Format</th>
                                <th>Rows</th>
                            </tr>
                        </thead>
                        <tbody id="file-table"></tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>

    <script>
        /* ========= NAVIGATION ========= */
        function navigate(page, el) {
            document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));

            document.getElementById('page-' + page).classList.add('active');
            el.classList.add('active');
        }

        /* ========= LOAD USERS ========= */
        function loadUsers() {
            fetch('?action=get_users')
                .then(res => res.json())
                .then(data => {
                    let html = '';
                    data.forEach(u => {
                        html += `
<tr>
<td>${u.name}</td>
<td>${u.email}</td>
<td>${u.role}</td>
<td>${u.status}</td>
</tr>`;
                    });
                    document.getElementById('user-table').innerHTML = html;
                });
        }

        /* ========= LOAD FILES ========= */
        function loadFiles() {
            fetch('?action=get_files')
                .then(res => res.json())
                .then(data => {
                    let html = '';
                    data.forEach(f => {
                        html += `
<tr>
<td>${f.id}</td>
<td>${f.filename}</td>
<td>${f.format}</td>
<td>${f.rowCount}</td>
</tr>`;
                    });
                    document.getElementById('file-table').innerHTML = html;
                });
        }

        /* ========= INIT ========= */
        loadUsers();
        loadFiles();
    </script>

</body>

</html>