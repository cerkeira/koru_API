<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use App\Models\DB;

// LOGIN
$app->post('/user/login/{id}', function (Request $request, Response $response) {

// LOGIN PODE SER TANTO COM ID COMO USERNAME

  $id = $request->getAttribute('id');
  $data = $request->getParsedBody();
  $password = $data["password"];

  $sql = "SELECT id_user, password FROM user WHERE username = :id";

  try {
      $db = new Db();
      $conn = $db->connect();
      $stmt = $conn->prepare($sql);
      $stmt->bindParam(':id', $id, PDO::PARAM_INT);
      $stmt->execute();

      $result = $stmt->fetch(PDO::FETCH_ASSOC);
      $hashedPassword = $result['password'];

      if (!$hashedPassword || !password_verify($password, $hashedPassword)) {
          $response->getBody()->write('Invalid username or password');
          return $response->withStatus(401);
      } else {
        $generateToken = function ($length = 32) {
                $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                $charLength = strlen($characters);
                $token = '';
          
                for ($i = 0; $i < $length; $i++) {
                  $randomIndex = mt_rand(0, $charLength - 1);
                  $token .= $characters[$randomIndex];
                }
          
                return $token;
              };

          $token = $generateToken();
          $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

          $updateSql = "UPDATE user SET token = :token, token_expire = :expiry WHERE username = :id";
          $updateStmt = $conn->prepare($updateSql);
          $updateStmt->bindParam(':token', $token);
          $updateStmt->bindParam(':expiry', $expiry);
          $updateStmt->bindParam(':id', $id, PDO::PARAM_INT);
          $updateStmt->execute();

          $output = array(
            "id_user" => $result['id_user'],
            "token" => $token,
        );
          $response->getBody()->write(json_encode($output));
          return $response;
      }
  } catch (PDOException $e) {
      $error = array("message" => $e->getMessage());
      $response->getBody()->write(json_encode($error));
      return $response->withHeader('content-type', 'application/json')->withStatus(500);
  }
});


// REGISTO
$app->post('/user/register', function (Request $request, Response $response) {
  $data = $request->getParsedBody();
  $pass = $data['password'];
  $username = $data['username'];
  $email = $data['email'];
  $password = password_hash($pass, PASSWORD_DEFAULT);

  $sql = "INSERT INTO user (id_user, username, email, password, token, token_expire) VALUES (NULL, :username, :email, :password, NULL, NULL)";

  try {
      $db = new Db();
      $conn = $db->connect();
      $stmt = $conn->prepare($sql);
      $stmt->bindParam(':username', $username);
      $stmt->bindParam(':email', $email);
      $stmt->bindParam(':password', $password);
      $stmt->execute();

      $db = null;
      $response->getBody()->write(json_encode(true));
      return $response->withHeader('content-type', 'application/json')->withStatus(200);
  } catch (PDOException $e) {
      $error = array("message" => $e->getMessage());
      $response->getBody()->write(json_encode($error));
      return $response->withHeader('content-type', 'application/json')->withStatus(500);
  }
});

