<?php
session_start();
require_once 'db.php';
require_once 'TicketManager.php';
require_once 'AdminManager.php';

// Route back to authorization matrix if session token does not exist
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$ticketManager = new TicketManager($pdo);
$adminManager = new AdminManager($pdo);
$message = '';
if (isset($_SESSION['operational_message'])) {
    $message = $_SESSION['operational_message'];
    unset($_SESSION['operational_message']); // Clear it so it only displays once!
}

$currentUserId   = $_SESSION['user_id'];
$currentUserName = $_SESSION['user_name'];
// Normalize casing string logic ('Admin', 'Agent', 'Requester') to prevent system mismatch
$currentRole     = ucfirst(strtolower($_SESSION['user_role'] ?? 'Requester')); 

// Capture UI runtime filtering parameters cleanly
$filterStatus   = $_GET['filter_status'] ?? '';
$filterPriority = $_GET['filter_priority'] ?? '';
$filterAgent    = $_GET['filter_agent'] ?? '';

// Catch report download actions before page layout compiles
if ($currentRole === 'Admin' && isset($_GET['download_action'])) {
    $downloadFormat = $_GET['download_action'];

    // Fetch master queue and filter locally based on chosen metrics
    $rawTickets = $ticketManager->getMasterQueue();
    $filteredTickets = array_filter($rawTickets, function($t) use ($filterStatus, $filterPriority, $filterAgent) {
        if (!empty($filterStatus) && $t['status'] !== $filterStatus) return false;
        if (!empty($filterPriority) && $t['priority'] !== $filterPriority) return false;
        if (!empty($filterAgent) && ($t['assigned_to'] ?? 0) != $filterAgent) return false;
        return true;
    });

    if (ob_get_level()) ob_end_clean();

    if ($downloadFormat === 'excel') {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename=Support_Report_' . date('Ymd_His') . '.xls');
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Title</th><th>Priority</th><th>Status</th><th>By</th><th>Assigned Agent</th></tr>";
        foreach ($filteredTickets as $t) {
            echo "<tr><td>#{$t['id']}</td><td>" . htmlspecialchars($t['title']) . "</td><td>{$t['priority']}</td><td>{$t['status']}</td><td>" . htmlspecialchars($t['creator_name'] ?? 'System') . "</td><td>" . htmlspecialchars($t['agent_name'] ?? 'Unassigned') . "</td></tr>";
        }
        echo "</table>";
        exit;
    } 
    elseif ($downloadFormat === 'pdf') {
        header('Content-Type: text/html; charset=utf-8');
        echo "<html><head><style>body{font-family:sans-serif;} table{width:100%; border-collapse:collapse;} th,td{border:1px solid #ddd; padding:8px; text-align:left;} th{background:#f2f2f2;}</style></head><body onload='window.print()'>";
        echo "<h2>IT Helpdesk Operations Report</h2><p>Generated on: " . date('Y-m-d H:i:s') . "</p>";
        echo "<table><thead><tr><th>ID</th><th>Title</th><th>Priority</th><th>Status</th><th>Agent</th></tr></thead><tbody>";
        foreach ($filteredTickets as $t) {
            echo "<tr><td>#{$t['id']}</td><td>" . htmlspecialchars($t['title']) . "</td><td>{$t['priority']}</td><td>{$t['status']}</td><td>" . htmlspecialchars($t['agent_name'] ?? 'Unassigned') . "</td></tr>";
        }
        echo "</tbody></table></body></html>";
        exit;
    }
}

// Form Routing Handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Standard creation handling channel 
    if ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priority = $_POST['priority'] ?? 'Low';
        $uploadedFilePath = null;

        if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['screenshot']['tmp_name'];
            $fileName = $_FILES['screenshot']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'pdf'])) {
                $uploadDir = __DIR__ . '/uploads/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                
                $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                $destPath = $uploadDir . $newFileName;

                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    $uploadedFilePath = 'uploads/' . $newFileName;
                }
            }
        }

        if (!empty($title) && !empty($description)) {
            $ticketManager->createTicket($title, $description, $priority, $currentUserId, $uploadedFilePath);
            $_SESSION['operational_message'] = "<div class='alert alert-success alert-dismissible fade show shadow-sm' role='alert'><i class='bi bi-check-circle-fill me-2'></i>Support ticket opened and queued inside operations grid.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
            header("Location: index.php");
            exit;
        }
    }

    // Requester Action: Cancel Own Ticket
    if ($action === 'requester_cancel') {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $ticketManager->updateStatus($ticketId, 'Closed', $currentUserId);
        $message = "<div class='alert alert-warning alert-dismissible fade show shadow-sm' role='alert'><i class='bi bi-info-circle-fill me-2'></i>Ticket #{$ticketId} has been canceled by requester.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    }

    // Admin-Specific Action: Create New User Account
    if ($action === 'admin_create_user' && $currentRole === 'Admin') {
        $uName = $_POST['new_name'] ?? '';
        $uEmail = $_POST['new_email'] ?? '';
        $uPass = $_POST['new_password'] ?? '';
        $uRole = $_POST['new_role'] ?? 'Requester';

        $res = $adminManager->createUser($uName, $uEmail, $uPass, $uRole);
        $statusClass = $res['success'] ? 'alert-success' : 'alert-danger';
        $icon = $res['success'] ? 'bi-person-check-fill' : 'bi-exclamation-triangle-fill';
        $message = "<div class='alert {$statusClass} alert-dismissible fade show shadow-sm' role='alert'><i class='bi {$icon} me-2'></i>" . $res['message'] . "<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    }

    // Admin-Specific Action: Update User Account
    if ($action === 'admin_update_user' && $currentRole === 'Admin') {
        $uId = (int)($_POST['user_id'] ?? 0);
        $uName = $_POST['new_name'] ?? '';
        $uEmail = $_POST['new_email'] ?? '';
        $uRole = $_POST['new_role'] ?? 'Requester';
        $uPass = $_POST['new_password'] ?? '';

        $res = $adminManager->updateUser($uId, $uName, $uEmail, $uRole, $uPass);
        $statusClass = $res['success'] ? 'alert-success' : 'alert-danger';
        $icon = $res['success'] ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill';
        $message = "<div class='alert {$statusClass} alert-dismissible fade show shadow-sm' role='alert'><i class='bi {$icon} me-2'></i>" . $res['message'] . "<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    }

    // Admin-Specific Action: Delete User Account
    if ($action === 'admin_delete_user' && $currentRole === 'Admin') {
        $uId = (int)($_POST['user_id'] ?? 0);
        if ($uId !== $currentUserId) {
            $res = $adminManager->deleteUser($uId);
            $statusClass = $res['success'] ? 'alert-success' : 'alert-danger';
            $message = "<div class='alert {$statusClass} alert-dismissible fade show shadow-sm' role='alert'><i class='bi bi-trash-fill me-2'></i>" . $res['message'] . "<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        } else {
            $message = "<div class='alert alert-danger alert-dismissible fade show shadow-sm' role='alert'><i class='bi bi-exclamation-octagon-fill me-2'></i>Self-deletion is blocked to preserve administrative access.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        }
    }

    // Standard operational ticket updates
    if ($action === 'claim' && $currentRole === 'Agent') {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $ticketManager->claimTicket($ticketId, $currentUserId);
    }

    if ($action === 'update_status' && ($currentRole === 'Admin' || $currentRole === 'Agent')) {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $newStatus = $_POST['status'] ?? '';
        $ticketManager->updateStatus($ticketId, $newStatus, $currentUserId);
    }

    if ($action === 'admin_assign' && $currentRole === 'Admin') {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $agentId = (int)($_POST['agent_id'] ?? 0);
        $ticketManager->forceAssignTicket($ticketId, $agentId, $currentUserId);
    }

    if ($action === 'admin_delete' && $currentRole === 'Admin') {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $ticketManager->deleteTicket($ticketId, $currentUserId);
    }
}

// Track inline user edit parameters state safely
$editUser = null;
if ($currentRole === 'Admin' && isset($_GET['edit_user'])) {
    $editUser = $adminManager->getUserById((int)$_GET['edit_user']);
}

// Prepare variable payloads
$tickets = $ticketManager->getMasterQueue();
$agentsList = $ticketManager->getAllAgents();
$userRoster = $adminManager->getAllUsers();

// Generate filtered dataset for preview window
$reportTickets = array_filter($tickets, function($t) use ($filterStatus, $filterPriority, $filterAgent) {
    if (!empty($filterStatus) && $t['status'] !== $filterStatus) return false;
    if (!empty($filterPriority) && $t['priority'] !== $filterPriority) return false;
    if (!empty($filterAgent) && ($t['assigned_to'] ?? 0) != $filterAgent) return false;
    return true;
});

// Aggregate Report Metrics Data Arrays
$metrics = $ticketManager->getSummaryMetrics();
$priorityStats = $ticketManager->getPriorityMetrics();
$agentLoads = $ticketManager->getAgentLoadMetrics();

$priorityBadges = [
    'Critical' => 'bg-danger text-white', 
    'High'     => 'bg-warning text-dark', 
    'Medium'   => 'bg-info text-white', 
    'Low'      => 'bg-secondary text-white'
];

// Determine explicitly which tab should be active stateful
$activeTab = 'queue'; // Default fallback
if ($currentRole === 'Admin') {
    if (isset($_GET['edit_user'])) {
        $activeTab = 'users';
    } elseif (!empty($filterStatus) || !empty($filterPriority) || !empty($filterAgent) || isset($_GET['download_action'])) {
        $activeTab = 'report-generate';
    } elseif (isset($_GET['tab']) && $_GET['tab'] === 'reports') {
        $activeTab = 'reports';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Corporate Operations Desk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-light-subtle py-4">

<div class="container">
    
    <header class="d-flex flex-wrap justify-content-between align-items-center p-3 mb-4 <?php echo $currentRole === 'Admin' ? 'bg-dark text-white' : 'bg-white text-dark'; ?> rounded shadow-sm">
        <div class="d-flex align-items-center gap-2">
            <i class="bi <?php echo $currentRole === 'Admin' ? 'bi-shield-lock-fill text-warning' : 'bi-shield-fill-check text-primary'; ?> fs-3"></i>
            <span class="fs-4 fw-bold"><?php echo $currentRole === 'Admin' ? 'Central Administration Panal' : 'IT Helpdesk Portal'; ?></span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="small opacity-75">Signed in as: <strong><?php echo htmlspecialchars($currentUserName); ?></strong></span>
            <span class="badge <?php echo $currentRole === 'Admin' ? 'bg-warning text-dark' : 'bg-secondary'; ?>"><?php echo $currentRole; ?></span>
            <a href="logout.php" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </header>

    <?php if (!empty($message)) echo $message; ?>

    <ul class="nav nav-pills mb-4 gap-2 bg-white p-2 rounded shadow-sm" id="dashboardTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold <?php echo $activeTab === 'queue' ? 'active' : ''; ?>" id="queue-tab" data-bs-toggle="tab" data-bs-target="#queue-pane" type="button" role="tab"><i class="bi bi-list-task me-2"></i>Active Operations Grid</button>
        </li>
        <?php if ($currentRole === 'Admin'): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold text-success <?php echo $activeTab === 'reports' ? 'active' : ''; ?>" id="reports-tab" data-bs-toggle="tab" data-bs-target="#reports-pane" type="button" role="tab"><i class="bi bi-graph-up-arrow me-2"></i>Reports & Metrics</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold text-info <?php echo $activeTab === 'users' ? 'active' : ''; ?>" id="users-tab" data-bs-toggle="tab" data-bs-target="#users-pane" type="button" role="tab"><i class="bi bi-people-fill me-2"></i>User Access Management</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold text-primary <?php echo $activeTab === 'report-generate' ? 'active' : ''; ?>" id="report-generate-tab" data-bs-toggle="tab" data-bs-target="#report-generate-pane" type="button" role="tab"><i class="bi bi-file-earmark-bar-graph-fill me-2"></i>Report Generate Area</button>
            </li>
        <?php endif; ?>
    </ul>

    <div class="tab-content" id="dashboardTabsContent">
        
        <div class="tab-pane fade <?php echo $activeTab === 'queue' ? 'show active' : ''; ?>" id="queue-pane" role="tabpanel" aria-labelledby="queue-tab">
            <div class="row g-4">
                <div class="col-12 col-lg-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white py-3 fw-bold text-secondary"><i class="bi bi-plus-circle me-2"></i>File a New Ticket</div>
                        <div class="card-body">
                            <form action="index.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="create">
                                <div class="mb-3"><label class="form-label small fw-bold">Issue Summary</label><input type="text" name="title" class="form-control" required placeholder="Server unreachable..."></div>
                                <div class="mb-3"><label class="form-label small fw-bold">Troubleshooting Details</label><textarea name="description" rows="3" class="form-control" required></textarea></div>
                                <div class="mb-3"><label class="form-label small fw-bold">Attachment</label><input type="file" name="screenshot" class="form-control form-control-sm"></div>
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Priority</label>
                                    <select name="priority" class="form-select form-select-sm">
                                        <option value="Low">Low</option>
                                        <option value="Medium" selected>Medium</option>
                                        <option value="High">High</option>
                                        <option value="Critical">Critical</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary w-100 fw-bold">Submit Ticket</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-8">
                    <div class="card border-0 shadow-sm">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead class="table-light small">
                                    <tr><th class="ps-3">ID</th><th>Issue Details</th><th>Priority</th><th>Status</th><th class="pe-3 text-end">Routing Actions</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tickets as $ticket): 
                                        $pClass = $priorityBadges[$ticket['priority']] ?? 'bg-secondary text-white';
                                    ?>
                                        <tr>
                                            <td class="ps-3 text-muted font-monospace">#<?php echo $ticket['id']; ?></td>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($ticket['title']); ?></div>
                                                <div class="text-muted small text-truncate" style="max-width: 300px;"><?php echo htmlspecialchars($ticket['description']); ?></div>
                                                <small class="text-muted" style="font-size:0.7rem;"><i class="bi bi-person"></i> By: <?php echo htmlspecialchars($ticket['creator_name'] ?? 'System'); ?> | Agent: <?php echo !empty($ticket['agent_name']) ? htmlspecialchars($ticket['agent_name']) : 'Unassigned'; ?></small>
                                            </td>
                                            <td><span class="badge w-100 <?php echo $pClass; ?>"><?php echo $ticket['priority']; ?></span></td>
                                            <td>
                                                <?php if ($currentRole === 'Admin'): ?>
                                                    <form action="index.php" method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="update_status"><input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                                        <select name="status" onchange="this.form.submit()" class="form-select form-select-sm text-primary fw-bold">
                                                            <option value="Open" <?php echo $ticket['status'] === 'Open' ? 'selected' : ''; ?>>Open</option>
                                                            <option value="In Progress" <?php echo $ticket['status'] === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                                            <option value="Resolved" <?php echo $ticket['status'] === 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
                                                            <option value="Closed" <?php echo $ticket['status'] === 'Closed' ? 'selected' : ''; ?>>Closed</option>
                                                        </select>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-dark border"><?php echo $ticket['status']; ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="pe-3 text-end">
                                                <?php if ($currentRole === 'Admin'): ?>
                                                    <div class="d-flex flex-column gap-1 align-items-end">
                                                        <form action="index.php" method="POST" class="w-100">
                                                            <input type="hidden" name="action" value="admin_assign"><input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                                            <select name="agent_id" onchange="this.form.submit()" class="form-select form-select-sm" style="font-size:0.75rem;">
                                                                <option value="" disabled selected>Reassign...</option>
                                                                <?php foreach($agentsList as $agent): ?><option value="<?php echo $agent['id']; ?>" <?php echo ($ticket['assigned_to'] ?? 0) == $agent['id'] ? 'selected' : ''; ?>>➔ <?php echo htmlspecialchars($agent['name']); ?></option><?php endforeach; ?>
                                                            </select>
                                                        </form>
                                                        <form action="index.php" method="POST" onsubmit="return confirm('Delete permanent?');">
                                                            <input type="hidden" name="action" value="admin_delete"><input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2 fw-bold" style="font-size:0.7rem;"><i class="bi bi-trash"></i> Delete</button>
                                                        </form>
                                                    </div>
                                                <?php elseif ($currentRole === 'Agent'): ?>
                                                    <div class="d-flex gap-1 justify-content-end">
                                                        <?php if (empty($ticket['assigned_to'])): ?>
                                                            <form action="index.php" method="POST">
                                                                <input type="hidden" name="action" value="claim">
                                                                <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-primary py-1 px-2 fw-bold" style="font-size:0.75rem;">
                                                                    <i class="bi bi-hand-index-thumb"></i> Claim
                                                                </button>
                                                            </form>
                                                        <?php elseif (($ticket['assigned_to'] ?? 0) == $currentUserId && $ticket['status'] !== 'Resolved'): ?>
                                                            <form action="index.php" method="POST">
                                                                <input type="hidden" name="action" value="update_status">
                                                                <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                                                <select name="status" onchange="this.form.submit()" class="form-select form-select-sm text-success fw-bold" style="font-size:0.75rem;">
                                                                    <option value="In Progress" <?php echo $ticket['status'] === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                                                    <option value="Resolved" <?php echo $ticket['status'] === 'Resolved' ? 'selected' : ''; ?>>Mark Resolved</option>
                                                                </select>
                                                            </form>
                                                        <?php else: ?>
                                                            <span class="text-muted small px-2">Managed</span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php elseif ($currentRole === 'Requester'): ?>
                                                    <div class="d-flex justify-content-end align-items-center">
                                                        <?php if (($ticket['created_by'] ?? 0) == $currentUserId && !in_array($ticket['status'], ['Resolved', 'Closed'])): ?>
                                                            <form action="index.php" method="POST" onsubmit="return confirm('Cancel this ticket?');">
                                                                <input type="hidden" name="action" value="requester_cancel">
                                                                <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-secondary py-1 px-2 fw-semibold" style="font-size:0.75rem;">
                                                                    <i class="bi bi-x-circle text-danger me-1"></i> Cancel Ticket
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <span class="badge bg-light text-muted border py-1 px-2 fw-normal" style="font-size:0.75rem;">ReadOnly Track</span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($currentRole === 'Admin'): ?>
        <div class="tab-pane fade <?php echo $activeTab === 'reports' ? 'show active' : ''; ?>" id="reports-pane" role="tabpanel" aria-labelledby="reports-tab">
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm bg-primary text-white p-3 rounded">
                        <div class="small fw-bold opacity-75">Total Queue Base</div>
                        <div class="fs-2 fw-black font-monospace"><?php echo $metrics['Total'] ?? 0; ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm bg-warning text-dark p-3 rounded">
                        <div class="small fw-bold opacity-75">Active Progress</div>
                        <div class="fs-2 fw-black font-monospace"><?php echo ($metrics['In Progress'] ?? 0) + ($metrics['Assigned'] ?? 0); ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm bg-success text-white p-3 rounded">
                        <div class="small fw-bold opacity-75">Resolved Tickets</div>
                        <div class="fs-2 fw-black font-monospace"><?php echo $metrics['Resolved'] ?? 0; ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm bg-secondary text-white p-3 rounded">
                        <div class="small fw-bold opacity-75">Closed Archive</div>
                        <div class="fs-2 fw-black font-monospace"><?php echo $metrics['Closed'] ?? 0; ?></div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-12 col-md-6">
                    <div class="card border-0 shadow-sm p-4 h-100">
                        <h6 class="fw-bold border-bottom pb-2 text-secondary"><i class="bi bi-pie-chart-fill me-2"></i>Severity Metric Load Analysis</h6>
                        <ul class="list-group list-group-flush mt-2">
                            <li class="list-group-item d-flex justify-content-between align-items-center">Critical System Failures <span class="badge bg-danger rounded-pill"><?php echo $priorityStats['Critical'] ?? 0; ?></span></li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">High Importance Blocks <span class="badge bg-warning text-dark rounded-pill"><?php echo $priorityStats['High'] ?? 0; ?></span></li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">Medium Level Disrupters <span class="badge bg-info rounded-pill"><?php echo $priorityStats['Medium'] ?? 0; ?></span></li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">Low Minor Demands <span class="badge bg-secondary rounded-pill"><?php echo $priorityStats['Low'] ?? 0; ?></span></li>
                        </ul>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="card border-0 shadow-sm p-4 h-100">
                        <h6 class="fw-bold border-bottom pb-2 text-secondary"><i class="bi bi-person-video2 me-2"></i>IT Staff Load Multiplier Matrix</h6>
                        <div class="table-responsive mt-2">
                            <table class="table table-sm align-middle table-borderless">
                                <thead class="table-light text-muted small">
                                    <tr><th>Agent Identifier Name</th><th class="text-end">Active Backlogs Mapping</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($agentLoads as $load): ?>
                                        <tr>
                                            <td class="fw-bold small"><?php echo htmlspecialchars($load['name']); ?></td>
                                            <td class="text-end">
                                                <span class="badge <?php echo $load['active_tickets'] > 3 ? 'bg-danger-subtle text-danger' : 'bg-light text-dark'; ?> px-3 border">
                                                    <?php echo $load['active_tickets']; ?> Tasks
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade <?php echo $activeTab === 'users' ? 'show active' : ''; ?>" id="users-pane" role="tabpanel" aria-labelledby="users-tab">
            <div class="row g-4">
                <div class="col-12 col-lg-4">
                    <div class="card border-0 shadow-sm p-4">
                        <h6 class="fw-bold border-bottom pb-2 text-secondary mb-3">
                            <i class="bi <?php echo $editUser ? 'bi-person-dash-fill text-warning' : 'bi-person-plus-fill text-primary'; ?> me-2"></i>
                            <?php echo $editUser ? 'Modify Corporate Account' : 'Provision New Corporate Account'; ?>
                        </h6>
                        <form action="index.php" method="POST">
                            <input type="hidden" name="action" value="<?php echo $editUser ? 'admin_update_user' : 'admin_create_user'; ?>">
                            <?php if ($editUser): ?>
                                <input type="hidden" name="user_id" value="<?php echo $editUser['id']; ?>">
                            <?php endif; ?>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Full Name</label>
                                <input type="text" name="new_name" class="form-control form-control-sm" required value="<?php echo $editUser ? htmlspecialchars($editUser['name']) : ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Email Address</label>
                                <input type="email" name="new_email" class="form-control form-control-sm" required value="<?php echo $editUser ? htmlspecialchars($editUser['email']) : ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">System Privilege Role</label>
                                <select name="new_role" class="form-select form-select-sm">
                                    <option value="Requester" <?php echo ($editUser && $editUser['role'] === 'Requester') ? 'selected' : ''; ?>>Requester (Employee)</option>
                                    <option value="Agent" <?php echo ($editUser && $editUser['role'] === 'Agent') ? 'selected' : ''; ?>>Operations Agent (IT Support)</option>
                                    <option value="Admin" <?php echo ($editUser && $editUser['role'] === 'Admin') ? 'selected' : ''; ?>>System Administrator</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Account Password <?php echo $editUser ? '(Leave blank to retain)' : ''; ?></label>
                                <input type="password" name="new_password" class="form-control form-control-sm" <?php echo $editUser ? '' : 'required'; ?>>
                            </div>
                            <button type="submit" class="btn btn-sm <?php echo $editUser ? 'btn-warning text-dark' : 'btn-success'; ?> w-100 fw-bold">
                                <?php echo $editUser ? 'Apply Matrix Updates' : 'Provision Secure Entry'; ?>
                            </button>
                            <?php if ($editUser): ?>
                                <a href="index.php" class="btn btn-sm btn-link text-secondary w-100 text-center mt-2 small text-decoration-none">Cancel Edit</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                <div class="col-12 col-lg-8">
                    <div class="card border-0 shadow-sm p-3">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr><th>UID</th><th>System User</th><th>Assigned Privileges</th><th class="text-end">Actions</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($userRoster as $usr): ?>
                                        <tr>
                                            <td class="font-monospace text-muted small">#<?php echo $usr['id']; ?></td>
                                            <td>
                                                <div class="fw-bold small"><?php echo htmlspecialchars($usr['name']); ?></div>
                                                <div class="text-muted" style="font-size:0.75rem;"><?php echo htmlspecialchars($usr['email']); ?></div>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $usr['role'] === 'Admin' ? 'bg-danger-subtle text-danger' : ($usr['role'] === 'Agent' ? 'bg-info-subtle text-info' : 'bg-light text-secondary'); ?> border px-2">
                                                    <?php echo $usr['role']; ?>
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <a href="index.php?edit_user=<?php echo $usr['id']; ?>" class="btn btn-sm btn-outline-primary py-0 px-2 fw-semibold" style="font-size:0.75rem;"><i class="bi bi-pencil-square"></i> Modify</a>
                                                <?php if ($usr['id'] !== $currentUserId): ?>
                                                    <form action="index.php" method="POST" class="d-inline" onsubmit="return confirm('Revoke account permissions forever?');">
                                                        <input type="hidden" name="action" value="admin_delete_user"><input type="hidden" name="user_id" value="<?php echo $usr['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2 fw-bold" style="font-size:0.7rem;"><i class="bi bi-trash"></i> Drop</button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade <?php echo $activeTab === 'report-generate' ? 'show active' : ''; ?>" id="report-generate-pane" role="tabpanel" aria-labelledby="report-generate-tab">
            <div class="card border-0 shadow-sm p-4 mb-4">
                <h5 class="fw-bold text-secondary mb-3"><i class="bi bi-funnel-fill text-primary me-2"></i>Data Filtering Engine</h5>
                <form method="GET" action="index.php" class="row g-3 align-items-end">
                    <?php if (isset($_GET['edit_user'])): ?>
                        <input type="hidden" name="edit_user" value="<?php echo (int)$_GET['edit_user']; ?>">
                    <?php endif; ?>
                    <div class="col-12 col-sm-3">
                        <label class="form-label small fw-bold text-muted">Status State</label>
                        <select name="filter_status" class="form-select form-select-sm">
                            <option value="">-- View All Statuses --</option>
                            <option value="Open" <?php echo $filterStatus === 'Open' ? 'selected' : ''; ?>>Open</option>
                            <option value="In Progress" <?php echo $filterStatus === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="Resolved" <?php echo $filterStatus === 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
                            <option value="Closed" <?php echo $filterStatus === 'Closed' ? 'selected' : ''; ?>>Closed</option>
                        </select>
                    </div>
                    <div class="col-12 col-sm-3">
                        <label class="form-label small fw-bold text-muted">Ticket Severity</label>
                        <select name="filter_priority" class="form-select form-select-sm">
                            <option value="">-- View All Severities --</option>
                            <option value="Low" <?php echo $filterPriority === 'Low' ? 'selected' : ''; ?>>Low</option>
                            <option value="Medium" <?php echo $filterPriority === 'Medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="High" <?php echo $filterPriority === 'High' ? 'selected' : ''; ?>>High</option>
                            <option value="Critical" <?php echo $filterPriority === 'Critical' ? 'selected' : ''; ?>>Critical</option>
                        </select>
                    </div>
                    <div class="col-12 col-sm-3">
                        <label class="form-label small fw-bold text-muted">Assigned Specialist</label>
                        <select name="filter_agent" class="form-select form-select-sm">
                            <option value="">-- View All Agents --</option>
                            <?php foreach ($agentsList as $ag): ?>
                                <option value="<?php echo $ag['id']; ?>" <?php echo $filterAgent == $ag['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($ag['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-sm-3 d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-primary flex-grow-1 fw-bold"><i class="bi bi-search me-1"></i> Filter View</button>
                        <a href="index.php" class="btn btn-sm btn-outline-secondary fw-semibold"><i class="bi bi-arrow-counterclockwise"></i></a>
                    </div>
                </form>
            </div>

            <div class="card border-0 shadow-sm p-3">
                <div class="d-flex justify-content-between align-items-center mb-3 px-2">
                    <span class="small text-muted">Filtered Yield Counter: <strong><?php echo count($reportTickets); ?></strong> matching records entries found.</span>
                    <div class="btn-group">
                        <a href="index.php?download_action=excel&filter_status=<?php echo urlencode($filterStatus); ?>&filter_priority=<?php echo urlencode($filterPriority); ?>&filter_agent=<?php echo urlencode($filterAgent); ?>" class="btn btn-sm btn-outline-success fw-bold"><i class="bi bi-file-earmark-excel me-1"></i> Export Excel</a>
                        <a href="index.php?download_action=pdf&filter_status=<?php echo urlencode($filterStatus); ?>&filter_priority=<?php echo urlencode($filterPriority); ?>&filter_agent=<?php echo urlencode($filterAgent); ?>" target="_blank" class="btn btn-sm btn-outline-danger fw-bold"><i class="bi bi-file-earmark-pdf me-1"></i> Print PDF</a>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 text-start font-monospace small">
                        <thead class="table-light">
                            <tr><th>ID</th><th>Issue Title Summary</th><th>Priority</th><th>Current Status</th><th>Handler Agent</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($reportTickets)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">No matching active records match current dashboard metric filter settings.</td></tr>
                            <?php else: ?>
                                <?php foreach ($reportTickets as $rt): ?>
                                    <tr>
                                        <td>#<?php echo $rt['id']; ?></td>
                                        <td class="text-dark fw-bold"><?php echo htmlspecialchars($rt['title']); ?></td>
                                        <td><?php echo $rt['priority']; ?></td>
                                        <td><span class="badge border bg-white text-dark"><?php echo $rt['status']; ?></span></td>
                                        <td><?php echo htmlspecialchars($rt['agent_name'] ?? 'Unassigned'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>