<?php
namespace App\Utils;

class ErrorHandler {
    private static $errors = [];

    public static function addError($error) {
        self::$errors[] = $error;
    }

    public static function getErrors() {
        return self::$errors;
    }

    public static function hasErrors() {
        return !empty(self::$errors);
    }

    public static function clearErrors() {
        self::$errors = [];
    }

    public static function handleException($e) {
        error_log($e->getMessage());
        self::addError('An unexpected error occurred. Please try again later.');
    }

    public static function jsonResponse($data = null, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        
        if (self::hasErrors()) {
            echo json_encode(['errors' => self::getErrors()]);
        } else {
            echo json_encode(['data' => $data]);
        }
        exit;
    }
}
