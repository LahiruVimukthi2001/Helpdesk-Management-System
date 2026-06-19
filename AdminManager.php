<?php
class AdminManager {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function createUser($name, $email, $password, $role) {
        if (empty($name) || empty($email) || empty($password)) {
            return ["success" => false, "message" => "All form fields are required."];
        }

        $email = filter_var(trim($email), FILTER_VALIDATE_EMAIL);
        if (!$email) {
            return ["success" => false, "message" => "Invalid email address format."];
        }

        $checkSql = "SELECT id FROM users WHERE email = :email";
        $stmt = $this->pdo->prepare($checkSql);
        $stmt->execute(['email' => $email]);
        if ($stmt->fetch()) {
            return ["success" => false, "message" => "This email is already registered."];
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        $sql = "INSERT INTO users (name, email, password_hash, role) VALUES (:name, :email, :password, :role)";
        $stmt = $this->pdo->prepare($sql);
        $executed = $stmt->execute([
            'name'     => htmlspecialchars(trim($name)),
            'email'    => $email,
            'password' => $hashedPassword,
            'role'     => $role
        ]);

        if ($executed) {
            return ["success" => true, "message" => "Account successfully created."];
        }
        return ["success" => false, "message" => "System error writing records."];
    }

    public function updateUser($id, $name, $email, $role, $password = null) {
        if (empty($name) || empty($email)) {
            return ["success" => false, "message" => "Name and Email are required."];
        }

        if (!empty($password)) {
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $sql = "UPDATE users SET name = :name, email = :email, role = :role, password_hash = :password WHERE id = :id";
            $params = ['name' => $name, 'email' => $email, 'role' => $role, 'password' => $hashedPassword, 'id' => $id];
        } else {
            $sql = "UPDATE users SET name = :name, email = :email, role = :role WHERE id = :id";
            $params = ['name' => $name, 'email' => $email, 'role' => $role, 'id' => $id];
        }

        $stmt = $this->pdo->prepare($sql);
        if ($stmt->execute($params)) {
            return ["success" => true, "message" => "User profile updated successfully."];
        }
        return ["success" => false, "message" => "Failed to update user context."];
    }

    public function deleteUser($id) {
        $sql = "DELETE FROM users WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        if ($stmt->execute(['id' => $id])) {
            return ["success" => true, "message" => "User dropped from the system context."];
        }
        return ["success" => false, "message" => "Failed to delete user record."];
    }

    public function getUserById($id) {
        $sql = "SELECT id, name, email, role FROM users WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getAllUsers() {
        $sql = "SELECT id, name, email, role, created_at FROM users ORDER BY id DESC";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function logEmail($recipient_email, $subject, $status, $error_message = null, $user_id = null) {
        $sql = "INSERT INTO email_logs (user_id, recipient_email, subject, status, error_message) 
                VALUES (:user_id, :recipient_email, :subject, :status, :error_message)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'user_id'         => $user_id,
            'recipient_email' => $recipient_email,
            'subject'         => htmlspecialchars($subject),
            'status'          => $status, 
            'error_message'   => $error_message
        ]);
    }

    public function getEmailLogs($filters = [], $limit = 50) {
        $sql = "SELECT el.*, u.name as user_name 
                FROM email_logs el 
                LEFT JOIN users u ON el.user_id = u.id 
                WHERE 1=1";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND el.status = :status";
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['start_date'])) {
            $sql .= " AND el.sent_at >= :start_date";
            $params['start_date'] = $filters['start_date'] . " 00:00:00";
        }
        if (!empty($filters['end_date'])) {
            $sql .= " AND el.sent_at <= :end_date";
            $params['end_date'] = $filters['end_date'] . " 23:59:59";
        }

        $sql .= " ORDER BY el.sent_at DESC LIMIT :limit";
        
        $stmt = $this->pdo->prepare($sql);
        
        foreach ($params as $key => $val) {
            $stmt->bindValue(':' . $key, $val);
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAdvancedMetrics() {
        $metrics = [];
        $metrics['total_users'] = (int)$this->pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        
        $roleStmt = $this->pdo->query("SELECT LOWER(TRIM(role)) as clean_role, COUNT(*) as count FROM users WHERE role IS NOT NULL GROUP BY LOWER(TRIM(role))");
        $rawRoles = $roleStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $metrics['roles'] = [
            'admin' => (int)($rawRoles['admin'] ?? 0),
            'user'  => (int)($rawRoles['user'] ?? 0)
        ];

        $emailStats = $this->pdo->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM email_logs
        ")->fetch(PDO::FETCH_ASSOC);

        $totalEmails = (int)($emailStats['total'] ?? 0);
        $sentEmails = (int)($emailStats['sent'] ?? 0);
        
        $metrics['emails'] = [
            'total' => $totalEmails,
            'sent' => $sentEmails,
            'failed' => (int)($emailStats['failed'] ?? 0),
            'success_rate' => $totalEmails > 0 ? round(($sentEmails / $totalEmails) * 100, 1) : 100
        ];

        $sparkSql = "SELECT DATE(created_at) as log_date, COUNT(*) as count 
                     FROM users 
                     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                     GROUP BY DATE(created_at) ORDER BY log_date ASC";
        $metrics['user_growth_trend'] = $this->pdo->query($sparkSql)->fetchAll(PDO::FETCH_ASSOC);

        return $metrics;
    }

    public function getFilteredUsersReport($role = null, $startDate = null, $endDate = null) {
        $sql = "SELECT id, name, email, role, created_at FROM users WHERE 1=1";
        $params = [];

        if (!empty($role)) {
            $sql .= " AND LOWER(TRIM(role)) = LOWER(TRIM(:role))";
            $params['role'] = $role;
        }
        if (!empty($startDate)) {
            $sql .= " AND created_at >= :start_date";
            $params['start_date'] = $startDate . " 00:00:00";
        }
        if (!empty($endDate)) {
            $sql .= " AND created_at <= :end_date";
            $params['end_date'] = $endDate . " 23:59:59";
        }

        $sql .= " ORDER BY created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function generateExcelReport($type, $filters = []) {
        if (ob_get_level()) ob_end_clean();

        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $type . '_report_' . date('Ymd_His') . '.xls');

        echo "<table>";
        if ($type === 'users') {
            echo "<tr><th>User ID</th><th>Full Identity Name</th><th>Secure Email Location</th><th>Role Scope</th><th>Registration Date</th></tr>";
            $data = $this->getFilteredUsersReport($filters['role'] ?? null, $filters['start_date'] ?? null, $filters['end_date'] ?? null);
            foreach ($data as $row) {
                echo "<tr><td>" . $row['id'] . "</td><td>" . htmlspecialchars($row['name']) . "</td><td>" . htmlspecialchars($row['email']) . "</td><td>" . strtoupper($row['role']) . "</td><td>" . $row['created_at'] . "</td></tr>";
            }
        } elseif ($type === 'emails') {
            echo "<tr><th>Sent Time</th><th>Recipient Email Address</th><th>Subject Context</th><th>Execution Status State</th><th>System Error Messages Logs</th></tr>";
            $data = $this->getEmailLogs($filters, 500);
            foreach ($data as $row) {
                echo "<tr><td>" . $row['sent_at'] . "</td><td>" . htmlspecialchars($row['recipient_email']) . "</td><td>" . htmlspecialchars($row['subject']) . "</td><td>" . strtoupper($row['status']) . "</td><td>" . htmlspecialchars($row['error_message'] ?? 'None') . "</td></tr>";
            }
        }
        echo "</table>";
        exit;
    }
}
?>