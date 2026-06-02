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

$currentUserId   = $_SESSION['user_id'];
$currentUserName = $_SESSION['user_name'];
// Normalize casing string logic ('Admin', 'Agent', 'Requester') to prevent system mismatch
$currentRole     = ucfirst(strtolower($_SESSION['user_role'] ?? 'Requester')); 

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
            $message = "<div class='alert alert-success alert-dismissible fade show shadow-sm' role='alert'><i class='bi bi-check-circle-fill me-2'></i>Support ticket opened and queued inside operations grid.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        }
    }

    // Requester Action: Cancel Own Ticket
    if ($action === 'requester_cancel') {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $ticketManager->updateStatus($ticketId, 'Closed');
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
        $ticketManager->updateStatus($ticketId, $newStatus);
    }

    if ($action === 'admin_assign' && $currentRole === 'Admin') {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $agentId = (int)($_POST['agent_id'] ?? 0);
        $ticketManager->forceAssignTicket($ticketId, $agentId);
    }

    if ($action === 'admin_delete' && $currentRole === 'Admin') {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $ticketManager->deleteTicket($ticketId);
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
            <span class="fs-4 fw-bold"><?php echo $currentRole === 'Admin' ? 'Central Administration Area' : 'IT Helpdesk Portal'; ?></span>
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
            <button class="nav-link active fw-bold" id="queue-tab" data-bs-toggle="tab" data-bs-target="#queue-pane" type="button" role="tab"><i class="bi bi-list-task me-2"></i>Active Operations Grid</button>
        </li>
        <?php if ($currentRole === 'Admin'): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold text-success" id="reports-tab" data-bs-toggle="tab" data-bs-target="#reports-pane" type="button" role="tab"><i class="bi bi-graph-up-arrow me-2"></i>Reports & Metrics</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold text-info <?php echo isset($_GET['edit_user']) ? 'active' : ''; ?>" id="users-tab" data-bs-toggle="tab" data-bs-target="#users-pane" type="button" role="tab"><i class="bi bi-people-fill me-2"></i>User Access Management</button>
            </li>
        <?php endif; ?>
    </ul>

    <div class="tab-content" id="dashboardTabsContent">
        
        <div class="tab-pane fade <?php echo !isset($_GET['edit_user']) ? 'show active' : ''; ?>" id="queue-pane" role="tabpanel" aria-labelledby="queue-tab">
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
                                    <label class="form-label small fw-bold">Severity</label>
                                    <select name="priority" class="form-select form-select-sm">
                                        <option value="Low">Low</option><option value="Medium" selected>Medium</option><option value="High">High</option><option value="Critical">Critical</option>
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
                                                        <?php if (($ticket['user_id'] ?? 0) == $currentUserId && !in_array($ticket['status'], ['Resolved', 'Closed'])): ?>
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
                                                <?php else: ?>
                                                    <span class="text-muted small opacity-50">-</span>
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
        <div class="tab-pane fade" id="reports-pane" role="tabpanel" aria-labelledby="reports-tab">
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm bg-primary text-white p-3 rounded">
                        <div class="small fw-bold opacity-75">Total Queue Base</div>
                        <div class="fs-2 fw-black font-monospace"><?php echo $metrics['Total']; ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm bg-warning text-dark p-3 rounded">
                        <div class="small fw-bold opacity-75">Active Progress</div>
                        <div class="fs-2 fw-black font-monospace"><?php echo $metrics['In Progress'] + $metrics['Assigned']; ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm bg-success text-white p-3 rounded">
                        <div class="small fw-bold opacity-75">Resolved Tickets</div>
                        <div class="fs-2 fw-black font-monospace"><?php echo $metrics['Resolved']; ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm bg-secondary text-white p-3 rounded">
                        <div class="small fw-bold opacity-75">Closed Archive</div>
                        <div class="fs-2 fw-black font-monospace"><?php echo $metrics['Closed']; ?></div>
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

        <div class="tab-pane fade <?php echo isset($_GET['edit_user']) ? 'show active' : ''; ?>" id="users-pane" role="tabpanel" aria-labelledby="users-tab">
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
                                <input type="text" name="new_name" class="form-control form-control-sm text-capitalize" required placeholder="e.g. Ruwan Perera" value="<?php echo $editUser ? htmlspecialchars($editUser['name']) : ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Corporate Email Address</label>
                                <input type="email" name="new_email" class="form-control form-control-sm" required placeholder="name@baengineering.com" value="<?php echo $editUser ? htmlspecialchars($editUser['email']) : ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold"><?php echo $editUser ? 'Security Password Key (Leave blank to keep current)' : 'Temporary Password Base'; ?></label>
                                <input type="password" name="new_password" class="form-control form-control-sm" <?php echo $editUser ? '' : 'required'; ?> minlength="6">
                            </div>
                            <div class="mb-4">
                                <label class="form-label small fw-bold">System Clearances Access Assignment</label>
                                <select name="new_role" class="form-select form-select-sm fw-semibold">
                                    <option value="Requester" <?php echo ($editUser && $editUser['role'] === 'Requester') ? 'selected' : ''; ?>>Requester (Standard Employee / Client)</option>
                                    <option value="Agent" <?php echo ($editUser && $editUser['role'] === 'Agent') ? 'selected' : ''; ?>>Agent (IT Technical Support Staff)</option>
                                    <option value="Admin" <?php echo ($editUser && $editUser['role'] === 'Admin') ? 'selected' : ''; ?>>Admin (Full Operations Root Executive)</option>
                                </select>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn <?php echo $editUser ? 'btn-warning text-dark' : 'btn-success'; ?> btn-sm flex-grow-1 fw-bold shadow-sm py-2">
                                    <i class="bi <?php echo $editUser ? 'bi-floppy-fill' : 'bi-shield-plus'; ?> me-1"></i>
                                    <?php echo $editUser ? 'Save Updates' : 'Commit & Provision'; ?>
                                </button>
                                <?php if ($editUser): ?>
                                    <a href="index.php" class="btn btn-sm btn-outline-secondary py-2 px-3 fw-bold">Cancel</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-12 col-lg-8">
                    <div class="card border-0 shadow-sm p-4">
                        <h6 class="fw-bold border-bottom pb-2 text-secondary mb-3"><i class="bi bi-shield-shaded me-2"></i>Current Domain Directory Roster Logs</h6>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle table-hover mb-0">
                                <thead class="table-light text-secondary small">
                                    <tr><th>Domain Name</th><th>Email Context</th><th>Role Tier</th><th>Provision Date</th><th class="text-end pe-3">Actions</th></tr>
                                </thead>
                                <tbody class="small">
                                    <?php foreach ($userRoster as $usr): ?>
                                        <tr>
                                            <td class="fw-bold text-dark"><?php echo htmlspecialchars($usr['name']); ?></td>
                                            <td class="text-muted"><code><?php echo htmlspecialchars($usr['email']); ?></code></td>
                                            <td>
                                                <span class="badge py-1 <?php echo $usr['role'] === 'Admin' ? 'bg-danger-subtle text-danger' : ($usr['role'] === 'Agent' ? 'bg-success-subtle text-success' : 'bg-light text-muted border'); ?>">
                                                    <?php echo $usr['role']; ?>
                                                </span>
                                            </td>
                                            <td class="text-muted font-monospace" style="font-size:0.75rem;">
                                                <?php echo (!empty($usr['created_at'])) ? date('Y-m-d', strtotime($usr['created_at'])) : 'Not Recorded'; ?>
                                            </td>
                                            <td class="text-end pe-3">
                                                <div class="btn-group shadow-sm rounded">
                                                    <a href="index.php?edit_user=<?php echo $usr['id']; ?>&amp;tab=users-pane" class="btn btn-sm btn-light border" title="Modify Configuration">
                                                        <i class="bi bi-pencil-square text-primary"></i>
                                                    </a>
                                                    <?php if ($usr['id'] !== $currentUserId): ?>
                                                        <form action="index.php" method="POST" class="d-inline" onsubmit="return confirm('Purge this access profile identity permanently from storage?');">
                                                            <input type="hidden" name="action" value="admin_delete_user">
                                                            <input type="hidden" name="user_id" value="<?php echo $usr['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-light border" title="Purge Record">
                                                                <i class="bi bi-trash3 text-danger"></i>
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-light border" disabled title="Current Session User"><i class="bi bi-lock text-muted"></i></button>
                                                    <?php endif; ?>
                                                </div>
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
        <?php endif; ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('edit_user')) {
            const userTab = document.querySelector('#users-tab');
            if (userTab) {
                bootstrap.Tab.getOrCreateInstance(userTab).show();
            }
        }
    });
</script>
</body>
</html>