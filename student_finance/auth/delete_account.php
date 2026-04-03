<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $user_id = $_SESSION['user_id'];
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // STEP 1: Verify user email & password
    $stmt = $conn->prepare("SELECT email, password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && $user['email'] === $email && password_verify($password, $user['password'])) {

        // STEP 2: Delete all related tables
        $tables = [
            'budget',
            'debts',
            'expenses',
            'income',
            'notifications',
            'recurring_transactions',
            'savings',
            'savings_goals',
            'achievements'
        ];

        foreach ($tables as $table) {
            $delete_related = $conn->prepare("DELETE FROM $table WHERE user_id = ?");
            $delete_related->bind_param("i", $user_id);
            $delete_related->execute();
        }

        // STEP 3: Delete user account
        $stmt_delete = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt_delete->bind_param("i", $user_id);
        $stmt_delete->execute();

        // STEP 4: Destroy session
        session_destroy();

        // STEP 5: Set flash message and redirect
        session_start(); // start session again to store message
        $_SESSION['flash_message'] = "Account deleted successfully!";
        $_SESSION['flash_type'] = "success";
        header("Location: /student_finance/index.php");
        exit();

    } else {
        // STEP 6: Set error message and redirect
        $_SESSION['flash_message'] = "Incorrect Email or Password.";
        $_SESSION['flash_type'] = "error";
        header("Location: ../index.php");
        exit();
    }
}