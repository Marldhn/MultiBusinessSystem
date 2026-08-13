<?php
// Ensure the User model is required so PHP knows where the class is located
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../config/db.php'; // Adjust path if your db.php is located elsewhere

class AuthController {
    
    public function __construct() {
        // Your existing initialization logic or routing for auth goes here
    }

    public function login() {
        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = trim($_POST['email'] ?? '');
            $password = trim($_POST['password'] ?? '');

            if (!empty($email) && !empty($password)) {
                $pdo = Database::getConnection();
                
                // Using the User model method safely now that the class is loaded
                $user = User::findByEmail($pdo, $email);

                if ($user && password_verify($password, $user['password'])) {
                    if (session_status() === PHP_SESSION_NONE) {
                        session_start();
                    }
                    
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['name'] = $user['name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];

                    header('Location: index.php?page=select_business');
                    exit;
                } else {
                    $error = 'Invalid email address or password.';
                }
            } else {
                $error = 'Please fill in all fields.';
            }
        }
    }
}