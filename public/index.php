<?php

require '../vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$config = require '../config/config.php';
$db = new PDO('mysql:host=' . $config['db']['host'] . ';dbname=' . $config['db']['dbname'], $config['db']['user'], $config['db']['pass']);
$jwtSecret = $config['jwt']['secret'];

function generateJWT($userId, $jwtSecret)
{
    $payload = [
        'iss' => 'http://your-domain.com',
        'aud' => 'http://your-domain.com',
        'iat' => time(),
        'exp' => time() + 3600,
        'sub' => $userId
    ];

    return JWT::encode($payload, $jwtSecret, 'HS256');
}

function getUserFromJWT($jwt, $jwtSecret)
{
    try {
        $decoded = JWT::decode($jwt, new Key($jwtSecret, 'HS256'));
        return $decoded->sub;
    } catch (Exception $e) {
        return null;
    }
}

$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];
$input = json_decode(file_get_contents('php://input'), true);

header('Content-Type: application/json');

switch ($requestUri) {
    case '/api/register':
        if ($requestMethod == 'POST') {
            $email = $input['email'];
            $password = password_hash($input['password'], PASSWORD_BCRYPT);

            $stmt = $db->prepare("INSERT INTO users (email, password) VALUES (:email, :password)");
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $password);
            if ($stmt->execute()) {
                echo json_encode(['message' => 'User registered successfully']);
            } else {
                http_response_code(400);
                echo json_encode(['message' => 'User registration failed']);
            }
        }
        break;

    case '/api/login':
        if ($requestMethod == 'POST') {
            $email = $input['email'];
            $password = $input['password'];

            $stmt = $db->prepare("SELECT * FROM users WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                $token = generateJWT($user['id'], $jwtSecret);
                echo json_encode(['token' => $token]);
            } else {
                http_response_code(401);
                echo json_encode(['message' => 'Invalid credentials']);
            }
        }
        break;

    case '/api/face-model':
        if ($requestMethod == 'GET') {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            $token = str_replace('Bearer ', '', $authHeader);
            $userId = getUserFromJWT($token, $jwtSecret);

            if ($userId) {
                $stmt = $db->prepare("SELECT * FROM face_models WHERE user_id = :user_id");
                $stmt->bindParam(':user_id', $userId);
                $stmt->execute();
                $faceModel = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($faceModel) {
                    echo json_encode($faceModel);
                } else {
                    http_response_code(404);
                    echo json_encode(['message' => 'Face model not found']);
                }
            } else {
                http_response_code(401);
                echo json_encode(['message' => 'Unauthorized']);
            }
        } elseif ($requestMethod == 'POST') {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            $token = str_replace('Bearer ', '', $authHeader);
            $userId = getUserFromJWT($token, $jwtSecret);

            if ($userId) {
                $features = json_encode($input['features']);

                $stmt = $db->prepare("INSERT INTO face_models (user_id, features) VALUES (:user_id, :features) ON DUPLICATE KEY UPDATE features = :features");
                $stmt->bindParam(':user_id', $userId);
                $stmt->bindParam(':features', $features);
                if ($stmt->execute()) {
                    echo json_encode(['message' => 'Face model stored successfully']);
                } else {
                    http_response_code(400);
                    echo json_encode(['message' => 'Failed to store face model']);
                }
            } else {
                http_response_code(401);
                echo json_encode(['message' => 'Unauthorized']);
            }
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['message' => 'Endpoint not found']);
        break;
}