<?php
require(__DIR__ . '/../../../config/database.php');
session_start();

$_SESSION['user_email'] = $_SESSION['user_email'] ?? 'admin@admin.com';
$_SESSION['user_name'] = $_SESSION['user_name'] ?? 'Admin';
$_SESSION['user_role'] = $_SESSION['user_role'] ?? 'admin';
$_SESSION['user_id'] = $_SESSION['user_id'] ?? 1;

$user_email = $_SESSION['user_email'];
$user_name = $_SESSION['user_name'];

// Get total users
$stmt = $conn->prepare("SELECT COUNT(*) as number_of_user FROM users");
$stmt->execute();
$stmt->bind_result($number_of_user);
$stmt->fetch();
$stmt->close();

// Get active users
$act = $conn->prepare("SELECT COUNT(*) as number_of_user FROM users where isActive=1");
$act->execute();
$act->bind_result($number_of_isActive);
$act->fetch();
$act->close();

// Get inactive users
$actn = $conn->prepare("SELECT COUNT(*) as number_of_user FROM users where isActive=0");
$actn->execute();
$actn->bind_result($number_of_isActive_not);
$actn->fetch();
$actn->close();

// Get admin count
$ad = $conn->prepare("SELECT COUNT(*) as number_of_user FROM users where role='admin'");
$ad->execute();
$ad->bind_result($admin_number);
$ad->fetch();
$ad->close();

// Fetch users for customers table
$users_query = $conn->prepare('SELECT id, name, email, isActive, role FROM users');
$users_query->execute();
$users_result_customers = $users_query->get_result();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Mono:wght@300;400;500&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../../../style/dashboradStyle.css">
</head>

<body>
    <div class="shell">
        <aside class="sidebar">
            <div class="brand">
                <div class="brand-icon">A</div>
                <div>
                    <div class="brand-name">AdminCore</div>
                    <div class="brand-sub">v1.0.0 SYSTEM</div>
                </div>
            </div>

            <div class="nav-section">
                <div class="nav-label">Main</div>
                <a class="nav-link active" href="#" onclick="navigate('home', this); return false;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                        <polyline points="9 22 9 12 15 12 15 22" />
                    </svg>
                    Home
                </a>
                <a class="nav-link" href="#" onclick="navigate('files', this); return false;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                    </svg>
                    File Uploaded
                </a>
                <a class="nav-link" href="#" onclick="navigate('customers', this); return false;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                        <circle cx="9" cy="7" r="4" />
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                        <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                    </svg>
                    Customers
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-label">Management</div>
                <a class="nav-link" href="#" onclick="navigate('users', this); return false;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                        <circle cx="12" cy="8" r="4" />
                        <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" />
                        <circle cx="19" cy="8" r="2" />
                        <path d="M21 14c1.5.5 2.5 2 2.5 3.5" />
                    </svg>
                    User Management
                    <span class="badge-pill green"><?= $number_of_user ?? 0 ?></span>
                </a>
                <a class="nav-link" href="#" onclick="navigate('errors', this); return false;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="12" y1="8" x2="12" y2="12" />
                        <line x1="12" y1="16" x2="12.01" y2="16" />
                    </svg>
                    Error Management
                    <span class="badge-pill">7</span>
                </a>
            </div>

            <div class="sidebar-bottom">
                <div class="user-card">
                    <div class="user-avatar"><?= strtoupper(substr($user_name, 0, 1)) ?></div>
                    <div class="user-info">
                        <div class="user-name"><?= htmlspecialchars($user_name) ?></div>
                        <div class="user-role"><?= htmlspecialchars($user_email) ?></div>
                    </div>
                    <div><img class="img-logout" src="../../../assets/img/logout.svg" width="24px" alt=""></div>
                </div>
            </div>
        </aside>

        <div class="main">
            <div class="topbar">
                <div>
                    <div class="page-title" id="topbar-title">Home</div>
                    <div class="page-crumb" id="topbar-crumb">ADMINCORE / HOME</div>
                </div>

            </div>

            <div class="content">
                <!-- Home Page -->
                <div class="page active" id="page-home">
                    <div class="stat-grid">
                        <div class="stat-card yellow">
                            <div class="stat-icon">👥</div>
                            <div class="stat-label">Total Users</div>
                            <div class="stat-value"><?= $number_of_user ?? 0 ?></div>
                        </div>
                        <div class="stat-card red">
                            <div class="stat-icon">⚠️</div>
                            <div class="stat-label">Active Errors</div>
                            <div class="stat-value">7</div>
                            <div class="stat-sub">3 critical</div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <div class="card-title">Recent Activity</div>
                                <div class="card-sub">LAST 7 DAYS</div>
                            </div>
                        </div>
                        <table>
                            <thead>
                                <tr>
                                    <th>Event</th>
                                    <th>User</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>User login</td>
                                    <td>ahmed@gmail.com</td>
                                    <td>2 min ago</td>
                                    <td><span class="badge badge-success">OK</span></td>
                                </tr>
                                <tr>
                                    <td>User login</td>
                                    <td>bassem@mail.com</td>
                                    <td>15 min ago</td>
                                    <td><span class="badge badge-success">OK</span></td>
                                </tr>

                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Files Page -->
                <div class="page" id="page-files">
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <div class="card-title">File Uploaded</div>
                            </div>
                            <div class="search-bar">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="2.5">
                                    <circle cx="11" cy="11" r="8" />
                                    <line x1="21" y1="21" x2="16.65" y2="16.65" />
                                </svg>
                                <input placeholder="Search File" />
                            </div>
                        </div>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>File name</th>
                                    <th>Category</th>
                                    <th>format</th>
                                    <th>uploaded By </th>
                                    <th>row Count</th>

                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $conn->prepare("SELECT id, filename, format, uploadedBy, rowCount FROM data_files ORDER BY id");
                                $stmt->execute();
                                $result = $stmt->get_result();

                                while ($row = $result->fetch_assoc()) {
                                    echo "
                                                <tr>
                                  <td>" . htmlspecialchars($row['id']) . "</td>
                                  <td>" . htmlspecialchars($row['filename']) . "</td>
                                 <td>" . htmlspecialchars($row['format']) . "</td>
                                     <td> <span class='badge badge-success'>" . htmlspecialchars($row['uploadedBy']) . " </span></td>
                                 <td>" . htmlspecialchars($row['rowCount']) . "</td>
                                 </tr>
    ";
                                }
                                ?>


                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Customers Page -->
                <div class="page" id="page-customers">
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <div class="card-title">Customers</div>
                                <div class="card-sub">ALL CUSTOMERS</div>
                            </div>
                        </div>
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Active</th>
                                    <th>Role</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $users_result_customers->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div class="user-row">
                                                <div class="row-avatar"><?= strtoupper(substr($row['name'], 0, 1)) ?></div>
                                                <div class="row-name"><?= htmlspecialchars($row['name']) ?></div>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($row['email']) ?></td>
                                        <td><?= $row['isActive'] ? 'Active' : 'Inactive' ?></td>
                                        <td><?= htmlspecialchars($row['role']) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- User Management Page -->
                <div class="page" id="page-users">
                    <div class="stat-grid" style="margin-bottom:20px">
                        <div class="stat-card teal">
                            <div class="stat-label">Total Users</div>
                            <div class="stat-value" id="total-users-count"><?= $number_of_user ?? 0 ?></div>
                        </div>
                        <div class="stat-card yellow">
                            <div class="stat-label">Admins</div>
                            <div class="stat-value" id="admin-count"><?= $admin_number ?? 0 ?></div>
                        </div>
                        <div class="stat-card red">
                            <div class="stat-label">Suspended</div>
                            <div class="stat-value" id="suspended-count"><?= $number_of_isActive_not ?? 0 ?></div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <div class="card-title">User Management</div>
                                <div class="card-sub">ALL ACCOUNTS</div>
                            </div>
                            <div class="filters-row">
                                <div class="search-bar">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="2.5">
                                        <circle cx="11" cy="11" r="8" />
                                        <line x1="21" y1="21" x2="16.65" y2="16.65" />
                                    </svg>
                                    <input id="user-search" placeholder="Search user…" onkeyup="filterUsers()" />
                                </div>
                                <button class="filter-chip active" onclick="setUserFilter('all', this)">All</button>
                                <button class="filter-chip" onclick="setUserFilter('admin', this)">Admin</button>
                                <button class="filter-chip" onclick="setUserFilter('user', this)">User</button>
                                <button class="filter-chip" onclick="setUserFilter('suspended', this)">Suspended</button>
                            </div>
                        </div>
                        <table>
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Last Active</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="user-table-body"></tbody>
                        </table>
                    </div>
                </div>

                <!-- Error Management Page -->
                <div class="page" id="page-errors">
                    <div class="stat-grid">
                        <div class="stat-card red">
                            <div class="stat-label">Critical</div>
                            <div class="stat-value">3</div>
                        </div>
                        <div class="stat-card yellow">
                            <div class="stat-label">Warning</div>
                            <div class="stat-value">4</div>
                        </div>
                        <div class="stat-card teal">
                            <div class="stat-label">Resolved (7d)</div>
                            <div class="stat-value">29</div>
                        </div>
                        <div class="stat-card green">
                            <div class="stat-label">Success Rate</div>
                            <div class="stat-value">99.2%</div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <div class="card-title">Error Log</div>
                            </div>
                            <div class="filters-row">
                                <div class="search-bar"><input id="error-search" placeholder="Search error…" onkeyup="filterErrors()" /></div>
                                <button class="filter-chip active" onclick="setErrorFilter('all', this)">All</button>
                                <button class="filter-chip" onclick="setErrorFilter('critical', this)">Critical</button>
                                <button class="filter-chip" onclick="setErrorFilter('warning', this)">Warning</button>
                            </div>
                        </div>
                        <table>
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Message</th>
                                    <th>Severity</th>
                                    <th>Origin</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="error-table-body"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- User Modal -->
    <div class="modal-overlay" id="user-modal">
        <div class="modal">
            <div class="modal-title" id="user-modal-title">Add New User</div>
            <div class="form-group">
                <label class="form-label">Full Name</label>
                <input class="form-input" id="u-name" placeholder="e.g. Alice Martin" />
            </div>
            <div class="form-group">
                <label class="form-label">Email</label>
                <input class="form-input" id="u-email" type="email" placeholder="user@example.com" />
            </div>
            <div class="form-group">
                <label class="form-label">Role</label>
                <select class="form-input" id="u-role">
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Status</label>
                <select class="form-input" id="u-status">
                    <option value="active">Active</option>
                    <option value="suspended">Suspended</option>
                </select>
            </div>
            <div class="modal-footer">
                <button class="topbar-btn" onclick="closeModal('user-modal')">Cancel</button>
                <button class="topbar-btn btn-primary" onclick="saveUser()">Save User</button>
            </div>
        </div>
    </div>

    <script>
        let users = [];
        let userFilter = 'all';
        let errorFilter = 'all';
        let editingUserId = null;

        // Error data
        let errors = [{
                id: 1,
                code: 'ERR_DB_TIMEOUT',
                msg: 'Database connection timed out',
                severity: 'critical',
                origin: 'db-service',
                time: '5 min ago',
                status: 'open'
            },
            {
                id: 2,
                code: 'ERR_PAYMENT_FAILED',
                msg: 'Stripe API returned 402',
                severity: 'critical',
                origin: 'payment-svc',
                time: '1 hr ago',
                status: 'open'
            },
            {
                id: 3,
                code: 'ERR_AUTH_JWT',
                msg: 'JWT signature verification failed',
                severity: 'critical',
                origin: 'auth-service',
                time: '2 hr ago',
                status: 'open'
            },
            {
                id: 4,
                code: 'WARN_CACHE_MISS',
                msg: 'Redis cache miss rate exceeded',
                severity: 'warning',
                origin: 'cache-layer',
                time: '20 min ago',
                status: 'open'
            },
            {
                id: 5,
                code: 'WARN_DISK_SPACE',
                msg: 'Disk usage at 87%',
                severity: 'warning',
                origin: 'system-monitor',
                time: '45 min ago',
                status: 'open'
            }
        ];

        // Navigation
        function navigate(page, el) {
            document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
            document.getElementById('page-' + page).classList.add('active');
            if (el) el.classList.add('active');

            const titles = {
                home: 'Home',
                files: 'Files',
                customers: 'Customers',
                users: 'User Management',
                errors: 'Error Management'
            };
            document.getElementById('topbar-title').textContent = titles[page];
        }

        function handleRefresh() {
            fetchUsers();
            showNotification('Refreshed successfully', 'success');
        }

        function handleAdd() {
            const active = document.querySelector('.page.active').id;
            if (active === 'page-users') {
                editingUserId = null;
                document.getElementById('user-modal-title').textContent = 'Add New User';
                document.getElementById('u-name').value = '';
                document.getElementById('u-email').value = '';
                document.getElementById('u-role').value = 'user';
                document.getElementById('u-status').value = 'active';
                document.getElementById('user-modal').classList.add('open');
            } else {
                showNotification('Feature coming soon', 'error');
            }
        }

        // API Functions
        async function fetchUsers() {
            try {
                const response = await fetch('../../../api/users.php');
                if (!response.ok) throw new Error('Failed to fetch users');
                users = await response.json();
                renderUsers();
                updateStats();
            } catch (error) {
                console.error('Error:', error);
                showNotification('Failed to load users', 'error');
            }
        }

        async function saveUser() {
            const name = document.getElementById('u-name').value.trim();
            const email = document.getElementById('u-email').value.trim();
            const role = document.getElementById('u-role').value;
            const status = document.getElementById('u-status').value;

            if (!name || !email) {
                showNotification('Please fill all fields', 'error');
                return;
            }

            const userData = {
                name,
                email,
                role,
                status
            };

            try {
                let response;
                if (editingUserId) {
                    response = await fetch('../../../api/users.php', {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            id: editingUserId,
                            ...userData
                        })
                    });
                } else {
                    response = await fetch('../../../api/users.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(userData)
                    });
                }

                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.error || 'Operation failed');
                }

                showNotification(editingUserId ? 'User updated successfully' : 'User created successfully', 'success');
                closeModal('user-modal');
                await fetchUsers();
            } catch (error) {
                showNotification(error.message, 'error');
            }
        }

        async function deleteUser(id) {
            if (!confirm('Are you sure you want to delete this user?')) return;

            try {
                const response = await fetch(`../../../api/users.php?id=${id}`, {
                    method: 'DELETE'
                });

                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.error || 'Failed to delete user');
                }

                showNotification('User deleted successfully', 'success');
                await fetchUsers();
            } catch (error) {
                showNotification(error.message, 'error');
            }
        }

        function editUser(id) {
            const user = users.find(u => u.id === id);
            if (user) {
                editingUserId = id;
                document.getElementById('user-modal-title').textContent = 'Edit User';
                document.getElementById('u-name').value = user.name;
                document.getElementById('u-email').value = user.email;
                document.getElementById('u-role').value = user.role;
                document.getElementById('u-status').value = user.status;
                document.getElementById('user-modal').classList.add('open');
            }
        }

        function updateStats() {
            const total = users.length;
            const admins = users.filter(u => u.role === 'admin').length;
            const suspended = users.filter(u => u.status === 'suspended').length;

            document.getElementById('total-users-count').textContent = total;
            document.getElementById('admin-count').textContent = admins;
            document.getElementById('suspended-count').textContent = suspended;
        }

        // Render Functions
        function renderUsers() {
            const searchTerm = document.getElementById('user-search')?.value?.toLowerCase() || '';
            let filtered = users.filter(u => {
                if (userFilter === 'admin') return u.role === 'admin';
                if (userFilter === 'user') return u.role === 'user';
                if (userFilter === 'suspended') return u.status === 'suspended';
                return true;
            }).filter(u => u.name.toLowerCase().includes(searchTerm) || u.email.toLowerCase().includes(searchTerm));

            const tbody = document.getElementById('user-table-body');
            if (!tbody) return;

            tbody.innerHTML = filtered.map(u => `
                <tr>
                    <td>
                        <div class="user-row">
                            <div class="row-avatar">${u.avatar}</div>
                            <div>
                                <div class="row-name">${escapeHtml(u.name)}</div>
                                <div class="row-email">${escapeHtml(u.email)}</div>
                            </div>
                        </div>
                    </td>
                    <td><span class="badge badge-info">${u.role}</span></td>
                    <td>${u.status === 'active' ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Suspended</span>'}</td>
                    <td>${u.last || '-'}</td>
                    <td>${u.created || '-'}</td>
                    <td>
                        <button class="action-btn" onclick="editUser(${u.id})">✏️</button>
                        <button class="action-btn" onclick="deleteUser(${u.id})">🗑</button>
                    </td>
                </tr>
            `).join('');
        }

        function renderErrors() {
            const searchTerm = document.getElementById('error-search')?.value?.toLowerCase() || '';
            let filtered = errors.filter(e => {
                if (errorFilter === 'critical') return e.severity === 'critical';
                if (errorFilter === 'warning') return e.severity === 'warning';
                return true;
            }).filter(e => e.code.toLowerCase().includes(searchTerm) || e.msg.toLowerCase().includes(searchTerm));

            const tbody = document.getElementById('error-table-body');
            if (!tbody) return;

            tbody.innerHTML = filtered.map(e => `
                <tr>
                    <td><code>${e.code}</code></td>
                    <td>${e.msg}</td>
                    <td><span class="badge ${e.severity === 'critical' ? 'badge-danger' : 'badge-warning'}">${e.severity}</span></td>
                    <td>${e.origin}</td>
                    <td>${e.time}</td>
                    <td><span class="badge ${e.status === 'resolved' ? 'badge-success' : 'badge-danger'}">${e.status}</span></td>
                    <td>
                        <button class="action-btn" onclick="resolveError(${e.id})">✓</button>
                        <button class="action-btn" onclick="deleteError(${e.id})">🗑</button>
                    </td>
                </tr>
            `).join('');
        }

        function resolveError(id) {
            const error = errors.find(e => e.id === id);
            if (error) error.status = 'resolved';
            renderErrors();
            showNotification('Error marked as resolved', 'success');
        }

        function deleteError(id) {
            if (confirm('Remove this error from log?')) {
                errors = errors.filter(e => e.id !== id);
                renderErrors();
                showNotification('Error deleted', 'success');
            }
        }

        function setUserFilter(filter, el) {
            userFilter = filter;
            document.querySelectorAll('#page-users .filter-chip').forEach(c => c.classList.remove('active'));
            el.classList.add('active');
            renderUsers();
        }

        function setErrorFilter(filter, el) {
            errorFilter = filter;
            document.querySelectorAll('#page-errors .filter-chip').forEach(c => c.classList.remove('active'));
            el.classList.add('active');
            renderErrors();
        }

        function filterUsers() {
            renderUsers();
        }

        function filterErrors() {
            renderErrors();
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('open');
        }

        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            document.body.appendChild(notification);
            setTimeout(() => notification.remove(), 3000);
        }

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }

        // Close modal when clicking outside
        document.querySelectorAll('.modal-overlay').forEach(o => {
            o.addEventListener('click', e => {
                if (e.target === o) o.classList.remove('open');
            });
        });

        // Initialize
        fetchUsers();
        renderErrors();
        navigate('home', document.querySelector('.nav-link.active'));
    </script>
</body>

</html>
<?php
$users_query->close();
$conn->close();
?>