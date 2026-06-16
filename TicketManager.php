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

    
}
?>