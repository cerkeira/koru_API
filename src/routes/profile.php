<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\DB;

// PROFILE
$app->get('/profile/user', function (Request $request, Response $response) {

    $id = $request->getHeaderLine('id');
  
    $eventsSql = "SELECT COUNT(event_id_event) AS numberOfEvents FROM user_has_event WHERE user_id_user = :id";
    $coinsSql = "SELECT SUM(amount) AS coinsInvested FROM transaction WHERE user_has_event_user_id_user = :id AND type = 2";
    $emailSql = "SELECT username, email FROM user WHERE id_user = :id";
    $recentSql = "SELECT id_transaction, user_has_event_event_id_event, type, coin_id_coin, amount, project.name_project  FROM transaction INNER JOIN project ON transaction.project_id_project = project.id_project WHERE user_has_event_user_id_user LIKE :id AND type = 2 ORDER BY id_transaction DESC LIMIT 3";


    try {
        $db = new Db();
        $conn = $db->connect();
  
        $stmt = $conn->prepare($eventsSql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $numberOfEvents = $stmt->fetchColumn();
  
        $stmt = $conn->prepare($coinsSql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $coinsInvested = $stmt->fetchColumn();

        $stmt = $conn->prepare($emailSql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $email = $stmt->fetchAll();

        $stmt = $conn->prepare($recentSql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $recentTransactions =  $stmt->fetchAll(PDO::FETCH_ASSOC);
  
        $profile = array(
            "username" => $email[0]['username'],
            "numberOfEvents" => $numberOfEvents,
            "coinsInvested" => $coinsInvested,
            "email" => $email[0]['email'],
            "recentTransactions" => $recentTransactions
        );
  
        $response->getBody()->write(json_encode($profile));
        return $response
            ->withHeader('content-type', 'application/json')
            ->withStatus(200);
    } catch (PDOException $e) {
        $error = array("message" => $e->getMessage());
        $response->getBody()->write(json_encode($error));
        return $response
            ->withHeader('content-type', 'application/json')
            ->withStatus(500);
    }
});

// EVENT LIST
$app->get('/profile/events', function (Request $request, Response $response) {

    $id = $request->getHeaderLine('id');


    $sql = "SELECT event.id_event, event.logo_event, event.name_event, event.start_date, event.end_date FROM event INNER JOIN user_has_event ON user_has_event.event_id_event = event.id_event WHERE user_has_event.user_id_user = :id";
    
    try {
        $db = new Db();
        $conn = $db->connect();
    
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($result as &$item) {

            if($item['logo_event'] == "" || $item['logo_event'] == null){
                $imagePath = 'test.png';
            }else{
                $imagePath = $item['logo_event'];
            }

                $imageFullPath = __DIR__ . '/../images/event/' . $imagePath;

                if (file_exists($imageFullPath)) {
                    $imageContent = file_get_contents($imageFullPath);
                    $item['logo_event'] = base64_encode($imageContent);
                }
            
        }
       
    
        $db = null;
    
        $response->getBody()->write(json_encode($result));
          return $response
            ->withHeader('content-type', 'application/json')
            ->withStatus(200);
    } catch (PDOException $e) {
        $error = array("message" => $e->getMessage());
        $response->getBody()->write(json_encode($error));
        return $response
            ->withHeader('content-type', 'application/json')
            ->withStatus(500);
    }
});


// MUDAR PASS
$app->post('/profile/changepass', function (Request $request, Response $response) {
    
      $id = $request->getHeaderLine('id');
      $data = $request->getParsedBody();
      $newpass = $data["newpass"];
      $newhash = password_hash($newpass, PASSWORD_DEFAULT);
      $oldpass = $data["oldpass"];

    
      $sql = "SELECT password FROM user WHERE id_user = :id";
    
      try {
          $db = new Db();
          $conn = $db->connect();
          $stmt = $conn->prepare($sql);
          $stmt->bindParam(':id', $id, PDO::PARAM_INT);
          $stmt->execute();
    
          $result = $stmt->fetch(PDO::FETCH_ASSOC);
          $hashedPassword = $result['password'];
    
          if (!$hashedPassword || !password_verify($oldpass, $hashedPassword)) {
              $response->getBody()->write('Invalid password');
              return $response->withStatus(401);
          } else {    
              $updateSql = "UPDATE user SET password = :newhash WHERE id_user = :id";
              $updateStmt = $conn->prepare($updateSql);
              $updateStmt->bindParam(':newhash', $newhash);
              $updateStmt->bindParam(':id', $id, PDO::PARAM_INT);
              $updateStmt->execute();
    
              $response->getBody()->write('Password changed');
              return $response;
          }
      } catch (PDOException $e) {
          $error = array("message" => $e->getMessage());
          $response->getBody()->write(json_encode($error));
          return $response->withHeader('content-type', 'application/json')->withStatus(500);
      }
    });
  