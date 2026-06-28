<?php
// CRITICAL FALLBACK: Ensure session state is active before reading user roles
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class TicketManager {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Centralized Dual-Logging Engine (Tracks All User Classes)
     */
    private function writeToLog($userId, $subject, $status, $details) {
        date_default_timezone_set('Asia/Colombo');
        $timestamp = date('Y-m-d H:i:s');
        
        // DEFAULT FALLBACK EMAIL
        $recipientEmail = 'admin@gmail.com';

        // DYNAMIC LOOKUP: Fetch the actual user's email from the database if a User ID exists
        if ($userId) {
            try {
                $userStmt = $this->pdo->prepare("SELECT email FROM users WHERE id = :id LIMIT 1");
                $userStmt->execute(['id' => $userId]);
                $userRow = $userStmt->fetch(PDO::FETCH_ASSOC);
                if ($userRow && !empty($userRow['email'])) {
                    $recipientEmail = $userRow['email'];
                }
            } catch (PDOException $e) {
                error_log("Failed to fetch user email for logging: " . $e->getMessage());
            }
        }

        // Normalize User ID description for the text log file
        $logUser = $userId ? "User ID: #{$userId}" : "System Automated Engine";

        // ------------------------------------------
        // 1. WRITE TO TEXT FILE (email_logs.txt)
        // ------------------------------------------
        try {
            $logFile = __DIR__ . '/email_logs.txt';
            $logLine = "[{$timestamp}] [{$logUser}] TO: {$recipientEmail} | SUBJECT: {$subject} | DETAILS: {$details}\n";
            file_put_contents($logFile, $logLine, FILE_APPEND);
        } catch (Exception $e) {
            error_log("Flat File Logging Failure: " . $e->getMessage());
        }

        // ------------------------------------------
        // 2. WRITE TO DATABASE TABLE (email_logs)
        // ------------------------------------------
        try {
            // Check if your schema matches these exact columns.
            // If your table lacks a 'status' column, you should remove it from this query.
            $sql = "INSERT INTO email_logs (user_id, recipient_email, subject, status, error_message, sent_at) 
                    VALUES (:user_id, :recipient_email, :subject, :status, :error_message, :sent_at)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'user_id'         => $userId ? (int)$userId : null,
                'recipient_email' => $recipientEmail,
                'subject'         => $subject,
                'status'          => $status, // This passes 'Success' or custom string statuses
                'error_message'   => $details,
                'sent_at'         => $timestamp
            ]);
        } catch (PDOException $e) {
            // This line prints the exact database issue directly to your server logs
            error_log("Database Table Logging Failure: " . $e->getMessage());
        }
    }

    public function createTicket($title, $description, $priority, $createdBy = null) {
        $createdBy = $createdBy ?? ($_SESSION['user_id'] ?? null);

        $sql = "INSERT INTO tickets (title, description, priority, created_by) 
                VALUES (:title, :description, :priority, :created_by)";
        
        $stmt = $this->pdo->prepare($sql);
        $success = $stmt->execute([
            'title' => htmlspecialchars($title),
            'description' => htmlspecialchars($description),
            'priority' => $priority,
            'created_by' => $createdBy
        ]);

        if ($success) {
            $cleanDetails = str_replace(["\r", "\n"], " ", $description);
            $subject = "System Update - New Ticket Created";
            $details = "Severity [{$priority}]: " . htmlspecialchars($title) . " - " . htmlspecialchars($cleanDetails);
            
            $this->writeToLog($createdBy, $subject, 'Success', $details);
        }

        return $success;
    }

    public function claimTicket($ticketId, $agentId = null) {
        $agentId = $agentId ?? ($_SESSION['user_id'] ?? null);

        $sql = "UPDATE tickets SET assigned_to = :agent_id, status = 'Assigned' WHERE id = :ticket_id AND assigned_to IS NULL";
        $stmt = $this->pdo->prepare($sql);
        $success = $stmt->execute(['agent_id' => $agentId, 'ticket_id' => $ticketId]);

        if ($success && $stmt->rowCount() > 0) {
            $this->writeToLog($agentId, "System Update - Ticket Claimed", 'Success', "Ticket #{$ticketId} successfully claimed by Agent ID: {$agentId}");
        }

        return $success;
    }

    public function updateStatus($ticketId, $status, $modifiedBy = null) {
        $modifiedBy = $modifiedBy ?? ($_SESSION['user_id'] ?? null);

        $sql = "UPDATE tickets SET status = :status WHERE id = :ticket_id";
        $stmt = $this->pdo->prepare($sql);
        $success = $stmt->execute(['status' => $status, 'ticket_id' => $ticketId]);

        if ($success) {
            $this->writeToLog($modifiedBy, "System Update - Status Changed", 'Success', "Ticket #{$ticketId} status mapping changed to [{$status}]");
        }

        return $success;
    }

    public function forceAssignTicket($ticketId, $agentId, $adminId = null) {
        $adminId = $adminId ?? ($_SESSION['user_id'] ?? null);

        $sql = "UPDATE tickets SET assigned_to = :agent_id, status = 'Assigned' WHERE id = :ticket_id";
        $stmt = $this->pdo->prepare($sql);
        $success = $stmt->execute(['agent_id' => $agentId, 'ticket_id' => $ticketId]);

        if ($success) {
            $this->writeToLog($adminId, "System Update - Administrative Reassignment", 'Success', "Ticket #{$ticketId} forcefully routed to Agent ID: {$agentId}");
        }

        return $success;
    }

    public function deleteTicket($ticketId, $deletedBy = null) {
        $deletedBy = $deletedBy ?? ($_SESSION['user_id'] ?? null);

        $sql = "DELETE FROM tickets WHERE id = :ticket_id";
        $stmt = $this->pdo->prepare($sql);
        $success = $stmt->execute(['ticket_id' => $ticketId]);

        if ($success) {
            $this->writeToLog($deletedBy, "System Update - Ticket Purged", 'Success', "Ticket Data Record #{$ticketId} permanently scrubbed from system operations storage grids.");
        }

        return $success;
    }

    public function getMasterQueue() {
        $sql = "SELECT t.*, u1.name as creator_name, u2.name as agent_name 
                FROM tickets t 
                JOIN users u1 ON t.created_by = u1.id 
                LEFT JOIN users u2 ON t.assigned_to = u2.id
                ORDER BY FIELD(t.priority, 'Critical', 'High', 'Medium', 'Low'), t.created_at DESC";
        return $this->pdo->query($sql)->fetchAll();
    }

    public function getAllAgents() {
        $sql = "SELECT id, name, role FROM users WHERE role IN ('Agent', 'Admin') ORDER BY name ASC";
        return $this->pdo->query($sql)->fetchAll();
    }

    public function getSummaryMetrics() {
        $metrics = [
            'Total' => 0, 
            'Open' => 0, 
            'Assigned' => 0, 
            'In Progress' => 0, 
            'Resolved' => 0, 
            'Closed' => 0
        ];
        
        $sql = "SELECT status, COUNT(*) as count FROM tickets GROUP BY status";
        $rows = $this->pdo->query($sql)->fetchAll();
        
        foreach ($rows as $row) {
            if (array_key_exists($row['status'], $metrics)) {
                $metrics[$row['status']] = (int)$row['count'];
            }
            $metrics['Total'] += (int)$row['count'];
        }
        return $metrics;
    }

    public function getPriorityMetrics() {
        $sql = "SELECT priority, COUNT(*) as count FROM tickets GROUP BY priority";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function getAgentLoadMetrics() {
        $sql = "SELECT u.name, COUNT(t.id) as active_tickets 
                FROM users u 
                LEFT JOIN tickets t ON u.id = t.assigned_to AND t.status != 'Closed'
                WHERE u.role IN ('Agent', 'Admin')
                GROUP BY u.id ORDER BY active_tickets DESC";
        return $this->pdo->query($sql)->fetchAll();
    }
}
?>