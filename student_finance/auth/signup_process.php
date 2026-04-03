<?php
session_start();
include 'config.php';

if (isset($_POST['signup'])) {
    $fullname         = trim($_POST['fullname']);
    $email            = trim($_POST['email']);
    $password         = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($fullname) || empty($email) || empty($password)) {
        $_SESSION['auth_error'] = "All fields are required.";
        header("Location: signup.php");
        exit();
    }

    if (strlen($password) < 6) {
        $_SESSION['auth_error'] = "Password must be at least 6 characters.";
        header("Location: signup.php");
        exit();
    }

    if ($password !== $confirm_password) {
        $_SESSION['auth_error'] = "Passwords do not match.";
        header("Location: signup.php");
        exit();
    }

    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $_SESSION['auth_error'] = "An account with that email already exists.";
        header("Location: signup.php");
        exit();
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt   = $conn->prepare("INSERT INTO users (fullname, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $fullname, $email, $hashed);

    if ($stmt->execute()) {
        $_SESSION['auth_success'] = "Account created! Please log in.";
        header("Location: login.php");
        exit();
    } else {
        $_SESSION['auth_error'] = "Something went wrong. Please try again.";
        header("Location: signup.php");
        exit();
    }
}

header("Location: signup.php");
exit();
?>