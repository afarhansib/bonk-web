<?php
namespace App\Controllers;

use App\Models\User;

class AuthController
{
    private $user;

    public function __construct()
    {
        $this->user = new User();
        if (!isset($_SESSION)) {
            session_start();
        }
    }

    public function register($username, $email, $password, $confirmPassword)
    {
        // Validate input
        if (empty($username) || empty($email) || empty($password)) {
            return ['error' => 'All fields are required'];
        }

        if ($password !== $confirmPassword) {
            return ['error' => 'Passwords do not match'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['error' => 'Invalid email format'];
        }

        // Log the registration attempt
        error_log("Attempting to register user: $username with email: $email");

        // Register user
        $registrationResult = $this->user->register($username, $email, $password);

        if ($registrationResult) {
            return ['success' => 'Registration successful'];
        }

        // Log the failure reason
        error_log("Registration failed for user: $username. Username or email already exists.");
        return ['error' => 'Username or email already exists'];
    }

    public function login($username, $password)
    {
        if (empty($username) || empty($password)) {
            return ['error' => 'Username and password are required'];
        }

        $result = $this->user->login($username, $password);
        if ($result) {
            $_SESSION['user_id'] = $result['id'];
            return ['success' => 'Login successful'];
        }

        return ['error' => 'Invalid username or password'];
    }

    public function logout()
    {
        session_destroy();
        header('Location: /login');
        exit;
    }

    public function isLoggedIn()
    {
        return isset($_SESSION['user_id']);
    }

    public function getCurrentUserId()
    {
        return $_SESSION['user_id'] ?? null;
    }
}
