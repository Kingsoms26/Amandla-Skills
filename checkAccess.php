<?php
// checkAccess.php
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT account_status FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $status = $stmt->get_result()->fetch_assoc()['account_status'] ?? 'active';
    $stmt->close();

    if ($status === 'suspended' || $status === 'banned') {
        session_destroy();
        header("Location: login.php?msg=account_restricted");
        exit();
    }
}
?>