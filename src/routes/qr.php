<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\DB;

$app->post('/qr/create', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $amount = $data["amount"];
    $coin = $data["coin"];
    $role = $data["role"];

    $sql = "INSERT INTO transaction_token (token, expire_date, amount, coin_id_coin, role) VALUES (:token, :date, :amount, :coin, :role)";

    $generateToken = function ($length = 14) {
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

    try {
        $db = new Db();
        $conn = $db->connect();

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':date', date('Y-m-d H:i:s', strtotime('+3 month')), PDO::PARAM_INT);
        $stmt->bindValue(':amount', $amount, PDO::PARAM_INT);
        $stmt->bindValue(':token', $token, PDO::PARAM_INT);
        $stmt->bindValue(':coin', $coin, PDO::PARAM_INT);
        $stmt->bindValue(':role', $role, PDO::PARAM_INT);
        $stmt->execute();
        $result = $token;

      
        $db = null;
        $response->getBody()->write(json_encode($result));
        return $response
            ->withHeader('content-type', 'application/json')
            ->withStatus(200);
    } catch (PDOException $e) {
        $error = array(
            "message" => $e->getMessage()
        );

        $response->getBody()->write(json_encode($error));
        return $response
            ->withHeader('content-type', 'application/json')
            ->withStatus(500);
    }
});

$app->post('/qr/firstread', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $token = $data["token"];
    $user = $request->getHeaderLine('id');


    $sql = "SELECT coin.event_id_event, event.name_event, event.logo_event, coin.name_coin FROM coin INNER JOIN event ON coin.event_id_event = event.id_event WHERE id_coin = :coin";

    $tokenSql = "SELECT expire_date, amount, coin_id_coin, role FROM transaction_token WHERE token = :token";

    $norepeatSql = "SELECT COUNT(id_transaction) FROM transaction WHERE coin_id_coin = :coin AND user_has_event_user_id_user = :user";

    try {
        $db = new Db();
        $conn = $db->connect();

        $stmt = $conn->prepare($tokenSql);
        $stmt->bindValue(':token', $token, PDO::PARAM_INT);
        $stmt->execute();
        $transaction = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':coin', $transaction[0]['coin_id_coin'], PDO::PARAM_INT);
        $stmt->execute();
        $event = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare($norepeatSql);
        $stmt->bindValue(':coin', $transaction[0]['coin_id_coin'], PDO::PARAM_INT);
        $stmt->bindValue(':user', $user, PDO::PARAM_INT);
        $stmt->execute();
        $norepeat = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if($norepeat[0]['COUNT(id_transaction)'] == 0 && $transaction[0]['expire_date'] >= date('Y-m-d H:i:s')){

                $result = array(
                    "id_event" => $event[0]['event_id_event'],
                    "name_event" => $event[0]['name_event'],
                    "name_coin" => $event[0]['name_coin'],
                    "coin" => $transaction[0]['coin_id_coin'],
                    "amount" => $transaction[0]['amount'],
                    "logo_event" =>  convertImageToBase64('event',$event[0]['logo_event'])
                );
                $status = 200;
        }else{
            $result="You can't get more of these coins.";
            $status=500;
        }
    

        $db = null;
        $response->getBody()->write(json_encode($result));
        return $response
            ->withHeader('content-type', 'application/json')
            ->withStatus($status);
    } catch (PDOException $e) {
        $error = array(
            "message" => $e->getMessage()
        );

        $response->getBody()->write(json_encode($error));
        return $response
            ->withHeader('content-type', 'application/json')
            ->withStatus(500);
    }

});

$app->post('/qr/secondread', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $token = $data["token"];
    $user = $request->getHeaderLine('id');


    $sql = "SELECT event_id_event FROM coin WHERE id_coin = :coin";

    $tokenSql = "SELECT expire_date, amount, coin_id_coin, role FROM transaction_token WHERE token = :token";

    $eventSql = "INSERT INTO user_has_event (user_id_user, event_id_event, role_ev_id_role_ev) VALUES (:user, :event, :role)";

    $transactionSql = "INSERT INTO transaction (type, amount, coin_id_coin, user_has_event_event_id_event, user_has_event_user_id_user) VALUES (:type, :amount, :coin, :event, :user)";

    $confirmSql = "SELECT event_id_event FROM user_has_event WHERE user_id_user = :user";

    try {
        $db = new Db();
        $conn = $db->connect();

        $stmt = $conn->prepare($tokenSql);
        $stmt->bindValue(':token', $token, PDO::PARAM_INT);
        $stmt->execute();
        $transaction = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':coin', $transaction[0]['coin_id_coin'], PDO::PARAM_INT);
        $stmt->execute();
        $event = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare($confirmSql);
        $stmt->bindValue(':user', $user, PDO::PARAM_INT);
        $stmt->execute();
        $confirm = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $eventIds = array_column($confirm, 'event_id_event');
            if (!in_array($event[0]['event_id_event'], $eventIds)) {
                    $stmt = $conn->prepare($eventSql);
                    $stmt->bindValue(':user', $user, PDO::PARAM_INT);
                    $stmt->bindValue(':event', $event[0]['event_id_event'], PDO::PARAM_INT);
                    $stmt->bindValue(':role', $transaction[0]['role'], PDO::PARAM_INT);
                    $stmt->execute();
            }

            $stmt = $conn->prepare($transactionSql);
            $stmt->bindValue(':type', 1, PDO::PARAM_INT);
            $stmt->bindValue(':amount', $transaction[0]['amount'], PDO::PARAM_INT);
            $stmt->bindValue(':coin', $transaction[0]['coin_id_coin'], PDO::PARAM_INT);
            $stmt->bindValue(':event', $event[0]['event_id_event'], PDO::PARAM_INT);
            $stmt->bindValue(':user', $user, PDO::PARAM_INT);
            $result=$stmt->execute();


        $db = null;
        $response->getBody()->write(json_encode($result));
        return $response
            ->withHeader('content-type', 'application/json')
            ->withStatus(200);
    } catch (PDOException $e) {
        $error = array(
            "message" => $e->getMessage()
        );

        $response->getBody()->write(json_encode($error));
        return $response
            ->withHeader('content-type', 'application/json')
            ->withStatus(500);
    }

});
?>