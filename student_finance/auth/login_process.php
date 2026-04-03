<?php
session_start();
include 'config.php';

if (isset($_POST['login'])) {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, fullname, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $_SESSION['auth_error'] = "No account found with that email.";
        header("Location: login.php");
        exit();
    }

    $user = $result->fetch_assoc();

    if (!password_verify($password, $user['password'])) {
        $_SESSION['auth_error'] = "Incorrect password. Please try again.";
        header("Location: login.php");
        exit();
    }

    $_SESSION['user_id']  = $user['id'];
    $_SESSION['fullname'] = $user['fullname'];
    header("Location: dashboard.php");
    exit();
}

// If someone visits this file directly without POST, send them to login
header("Location: login.php");
exit();
?>