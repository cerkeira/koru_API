<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\DB;

// BALANÇO
$app->get('/event/balance/{event}', function (Request $request, Response $response) {

    $event = $request->getAttribute('event');
    $user = $request->getHeaderLine('id');


    $sql = "SELECT type, transaction.amount, transaction.coin_id_coin, coin.name_coin FROM transaction INNER JOIN coin ON transaction.coin_id_coin = coin.id_coin WHERE user_has_event_event_id_event = :event AND user_has_event_user_id_user = :user";

    try {
        $db = new Db();
        $conn = $db->connect();

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':event', $event, PDO::PARAM_INT);
        $stmt->bindValue(':user', $user, PDO::PARAM_INT);
        $stmt->execute();
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $db = null;

        $responseData = [];

        foreach ($transactions as $transaction) {
            if ($transaction['type'] == 1) {
                if (!isset($responseData[$transaction['name_coin']])) {
                    $responseData[$transaction['name_coin']] = [
                        'id' => $transaction['coin_id_coin'],
                        'balance' => max(0, $transaction['amount'])
                    ];
                } else {
                    $responseData[$transaction['name_coin']]['balance'] = max(0, $responseData[$transaction['name_coin']]['balance'] + $transaction['amount']);
                }
            }
            if ($transaction['type'] == 2) {
                if (!isset($responseData[$transaction['name_coin']])) {
                    $responseData[$transaction['name_coin']] = [
                        'id' => $transaction['coin_id_coin'],
                        'balance' => max(0, $transaction['amount'])
                    ];
                } else {
                    $responseData[$transaction['name_coin']]['balance'] = max(0, $responseData[$transaction['name_coin']]['balance'] - $transaction['amount']);
                }
            }
        }

        $response->getBody()->write(json_encode($responseData));
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

//   VOTAR
$app->post('/event/vote/{event}', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $type = 2;
    $amount = $data["amount"];
    $project = $data["project"];
    $coin = $data["coin"];
    $event = $request->getAttribute('event');
    $user = $request->getHeaderLine('id');

    $sql = "INSERT INTO transaction (type, amount, project_id_project, coin_id_coin, user_has_event_event_id_event, user_has_event_user_id_user) VALUES (:type, :amount, :project, :coin, :event, :user)";

    $balanceSql = "SELECT type, amount, coin_id_coin FROM transaction WHERE user_has_event_event_id_event = :event AND user_has_event_user_id_user = :user AND coin_id_coin = :coin";

    $voteSql = "SELECT vote_start, vote_end FROM event WHERE id_event = :event";

    try {

        $db = new Db();
        $conn = $db->connect();

        $stmt = $conn->prepare($balanceSql);
        $stmt->bindValue(':event', $event, PDO::PARAM_INT);
        $stmt->bindValue(':user', $user, PDO::PARAM_INT);
        $stmt->bindParam(':coin', $coin, PDO::PARAM_INT);
        $stmt->execute();
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare($voteSql);
        $stmt->bindValue(':event', $event, PDO::PARAM_INT);
        $stmt->execute();
        $date = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $db = null;


        foreach ($transactions as $transaction) {
            if ($transaction['type'] == 1) {
                if (!isset($responseData)) {
                    $responseData =  max(0, $transaction['amount']);
                } else {
                    $responseData = max(0, $responseData + $transaction['amount']);
                }
            }
            if ($transaction['type'] == 2) {
                if (!isset($responseData)) {
                    $responseData =  max(0, $transaction['amount']);
                }
                $responseData = max(0, $responseData - $transaction['amount']);
            }
        }

        if ($responseData >= $amount){
            if(date('Y-m-d H:i:s') < $date[0]['vote_end'] && date('Y-m-d H:i:s') > $date[0]['vote_start']){
            $db = new Db();
            $conn = $db->connect();

            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':type', $type, PDO::PARAM_INT);
            $stmt->bindParam(':amount', $amount, PDO::PARAM_INT);
            $stmt->bindParam(':project', $project, PDO::PARAM_INT);
            $stmt->bindParam(':coin', $coin, PDO::PARAM_INT);
            $stmt->bindParam(':event', $event, PDO::PARAM_INT);
            $stmt->bindParam(':user', $user, PDO::PARAM_INT);

            $result = $stmt->execute();
            }else{
                $result = "Voting is not enabled at the moment.";
            }
        }else{
                $result = "You don't have enough coins.";
            }

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


// EDITAR EVENTO
$app->post('/event/edit/{id}', function (Request $request, Response $response) {
    $id = $request->getAttribute('id');
    $data = $request->getParsedBody();

    $selectSql = "SELECT name_event, des_event, end_date, start_date, vote_start, vote_end FROM event WHERE id_event = :id";
    try {
        $db = new Db();
        $conn = $db->connect();

        $stmt = $conn->prepare($selectSql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $select = $stmt->fetch(PDO::FETCH_ASSOC);

        if (isset($data['start_date']) && $data['start_date'] > $select['end_date']) {

            $db = null;

            $response->getBody()->write(json_encode('The event needs to end before it starts.'));
            return $response
                ->withHeader('content-type', 'application/json')
                ->withStatus(500);
        }

        if (isset($data['end_date']) && $data['end_date'] < $select['start_date']) {

            $db = null;

            $response->getBody()->write(json_encode('The event needs to start before it ends.'));
            return $response
                ->withHeader('content-type', 'application/json')
                ->withStatus(500);
        }

        $name = isset($data['name']) ? $data['name'] : $select['name_event'];
        $des = isset($data['des']) ? $data['des'] : $select['des_event'];
        $end_date = isset($data['end_date']) ? $data['end_date'] : $select['end_date'];
        $start_date = isset($data['start_date']) ? $data['start_date'] : $select['start_date'];
        $vote_start = isset($data['vote_start']) ? $data['vote_start'] : $select['vote_start'];
        $vote_end = isset($data['vote_end']) ? $data['vote_end'] : $select['vote_end'];



        $updateSql = "UPDATE event SET name_event = :name, des_event = :des, end_date = :end_date, start_date = :start_date, vote_start = :vote_start, vote_end = :vote_end WHERE id_event = :id";

        $stmt = $conn->prepare($updateSql);
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $stmt->bindParam(':des', $des, PDO::PARAM_STR);
        $stmt->bindParam(':end_date', $end_date, PDO::PARAM_STR);
        $stmt->bindParam(':start_date', $start_date, PDO::PARAM_STR);
        $stmt->bindParam(':vote_start', $vote_start, PDO::PARAM_STR);
        $stmt->bindParam(':vote_end', $vote_end, PDO::PARAM_STR);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        $result = $stmt->execute();

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

// INFORMAÇÕES DO EVENTO
$app->get('/event/info/{id}', function (Request $request, Response $response) {

    $id = $request->getAttribute('id');

    $firstSql = "SELECT
    name_event,
    des_event,
    logo_event,
    start_date,
    end_date,
    vote_start,
    vote_end,
    org.name_org,
    COUNT(DISTINCT user_id_user) AS total_people,
    COUNT(DISTINCT project.id_project) AS total_projetos
  FROM
    event
    INNER JOIN org ON event.org_id_org = org.id_org
    INNER JOIN user_has_event ON event.id_event = user_has_event.event_id_event
    INNER JOIN project ON event.id_event = project.event_id_event
  WHERE
    event.id_event = :id";

    $secondSql = " SELECT id_coin, name_coin FROM coin WHERE event_id_event = :id";

    try {
        $db = new Db();
        $conn = $db->connect();

        $stmt = $conn->prepare($firstSql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $first = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare($secondSql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $second = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = array(
            "info" => $first,
            "coins" => $second,
        );

        $db = null;
        
        if($result['info'][0]['logo_event'] != ''){
            $imagePath = $result['info'][0]['logo_event'];
        }else{
            $imagePath = 'test.png';
        }
        $imageFullPath = __DIR__ . '/../images/event/' . $imagePath;


        if (file_exists($imageFullPath)) {
            $imageContent = file_get_contents($imageFullPath);

            $result['info'][0]['logo_event'] = base64_encode($imageContent);
        }

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


// LIVE RANKING
$app->get('/event/rank/{id}', function (Request $request, Response $response) {
    $id = $request->getAttribute('id');

    $sql = "SELECT project_id_project, transaction.coin_id_coin, transaction.amount, project.name_project, project.logo_project, coin.name_coin FROM transaction INNER JOIN project ON transaction.project_id_project = project.id_project INNER JOIN coin ON transaction.coin_id_coin = coin.id_coin WHERE user_has_event_event_id_event = :id AND type = 2";

    $projectSql = "SELECT id_project, name_project, logo_project FROM project WHERE event_id_event = :id";

    $coinsSql = "SELECT id_coin, name_coin FROM coin WHERE event_id_event = :id";


    try {
        $db = new Db();
        $conn = $db->connect();

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare($projectSql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

        function convertImageToBase64($imagePath)
        {
            if ($imagePath == '') {
                $imagePath = 'test.png';
            }
            $imageFullPath = __DIR__ . '/../images/project/' . $imagePath;

            if (file_exists($imageFullPath)) {
                $imageContent = file_get_contents($imageFullPath);
                return base64_encode($imageContent);
            }
            return null;
        }

        $final = array();

        foreach ($result as $item) {
            $projectId = $item['project_id_project'];
            $coinId = $item['coin_id_coin'];
            $coinName = $item['name_coin'];
            $amount = $item['amount'];
            $name = $item['name_project'];
            $logo = convertImageToBase64($item['logo_project']);

            

            if (!isset($final[$coinId])) {
                $final[$coinId] = array(
                    "coin_id_coin" => $coinId,
                    "name_coin" => $coinName,
                    "projects" => array()
                );
            }

            if (!isset($final[$coinId]['projects'][$projectId])) {
                $final[$coinId]['projects'][$projectId] = array(
                    "id_project" => $projectId,
                    "name_project" => $name,
                    "logo_project" => $logo,
                    "amount_sum" => 0
                );
            }

            $final[$coinId]['projects'][$projectId]['amount_sum'] += $amount;
        }


        foreach ($final as &$coinData) {
            $coinData['projects'] = array_values($coinData['projects']);
            usort($coinData['projects'], function ($a, $b) {
                $sumA = $a['amount_sum'];
                $sumB = $b['amount_sum'];
                return $sumB <=> $sumA;
            });
        }

        if ($final != []){

            foreach ($projects as $project) {
                $projectId = $project['id_project'];
                $projectName = $project['name_project'];
                $projectLogo = convertImageToBase64($project['logo_project']);
            
                $found = false;
            
                foreach ($final as &$coinData) {
                    foreach ($coinData['projects'] as &$existingProject) {
                        if ($existingProject['id_project'] === $projectId) {
                            $found = true;
                            break 2;
                        }
                    }
                }
            
                if (!$found) {
                    foreach ($final as &$coinData) {
                        $coinData['projects'][] = array(
                            "id_project" => $projectId,
                            "name_project" => $projectName,
                            "logo_project" => $projectLogo,
                            "amount_sum" => 0,
                        );
                    }
                }
            }
        }else{
        $stmt = $conn->prepare($coinsSql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $coins = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($coins as $item) {
            $coinId = $item['id_coin'];
            $coinName = $item['name_coin'];
            

            if (!isset($final[$coinId])) {
                $final[$coinId] = array(
                    "coin_id_coin" => $coinId,
                    "name_coin" => $coinName,
                    "projects" => array()
                );
            }
        }

            foreach ($projects as $project) {
                $projectId = $project['id_project'];
                $projectName = $project['name_project'];
                $projectLogo = convertImageToBase64($project['logo_project']);
                foreach ($final as &$coinData) {
                    $coinData['projects'][] = array(
                        "id_project" => $projectId,
                        "name_project" => $projectName,
                        "logo_project" => $projectLogo,
                        "amount_sum" => 0,
                    );
                }
        }
    }


        foreach ($projects as $project) {
            $projectId = $project['id_project'];
            $projectName = $project['name_project'];
            $projectLogo = convertImageToBase64($project['logo_project']);
        
            $found = false;
        
            foreach ($final as &$coinData) {
                foreach ($coinData['projects'] as &$existingProject) {
                    if ($existingProject['id_project'] === $projectId) {
                        $found = true;
                        break 2;
                    }
                }
            }
        
            if (!$found) {
                foreach ($final as &$coinData) {
                    $coinData['projects'][] = array(
                        "id_project" => $projectId,
                        "name_project" => $projectName,
                        "logo_project" => $projectLogo,
                        "amount_sum" => 0,
                    );
                }
            }
        }

        $db = null;
        
        $response->getBody()->write(json_encode(array_values($final)));
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





// PROJETOS
$app->get('/event/projects/{event}', function (Request $request, Response $response) {

    $event = $request->getAttribute('event');


    $sql = "SELECT id_project, name_project, logo_project, desc_project, url FROM project WHERE event_id_event = :event";

    try {
        $db = new Db();
        $conn = $db->connect();

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':event', $event, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $db = null;

        
        foreach ($result as &$item) {

            if($item['logo_project'] == ''){
                $imagePath = 'test.png';
            }else{
                $imagePath = $item['logo_project'];
            }

            if ($imagePath != null) {
                $imageFullPath = __DIR__ . '/../images/project/' . $imagePath;

                if (file_exists($imageFullPath)) {
                    $imageContent = file_get_contents($imageFullPath);
                    $item['logo_project'] = base64_encode($imageContent);
                }
            }
        }


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

// SCHEDULE DO EVENTO
$app->get('/event/programa/{event}', function (Request $request, Response $response) {

    $event = $request->getAttribute('event');


    $sql = "SELECT name_schedule, date_schedule FROM schedule WHERE event_id_event = :event";

    $voteSql = "SELECT vote_start, vote_end FROM event WHERE id_event = :event";

    try {
        $db = new Db();
        $conn = $db->connect();

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':event', $event, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare($voteSql);
        $stmt->bindValue(':event', $event, PDO::PARAM_INT);
        $stmt->execute();
        $vote = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $db = null;

        $final = [];


        foreach ($result as $item) {
            $arrayproject = [
                "name_schedule" => $item['name_schedule'],
                "date_schedule" => $item['date_schedule']
            ];
            $final[] = $arrayproject;
        }

        $votesOpen = [
            "name_schedule" => "Votes open",
            "date_schedule" => $vote[0]['vote_start']
        ];
        $final[] = $votesOpen;

        $votesClose = [
            "name_schedule" => "Votes close",
            "date_schedule" => $vote[0]['vote_end']
        ];
        $final[] = $votesClose;

        function compareDates($a, $b)
        {
            $dateA = strtotime($a['date_schedule']);
            $dateB = strtotime($b['date_schedule']);
            return $dateA - $dateB;
        }

        usort($final, 'compareDates');

        $response->getBody()->write(json_encode($final));
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
// Versão antiga da info
// $app->get('/event/info/{id}', function (Request $request, Response $response) {

    //     $id = $request->getAttribute('id');
      
    //     $sql = "SELECT name_event, des_event, logo_event, start_date, end_date, vote_start, vote_end, org.name_org FROM event INNER JOIN org ON event.org_id_org = org.id_org WHERE event.id_event LIKE :id";
    
    //     try {
    //         $db = new Db();
    //         $conn = $db->connect();
      
    //         $stmt = $conn->prepare($sql);
    //         $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    //         $stmt->execute();
    //         $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    //         $db = null;
    
    //         $response->getBody()->write(json_encode($result));
    //         return $response
    //             ->withHeader('content-type', 'application/json')
    //             ->withStatus(200);
    //     } catch (PDOException $e) {
    //         $error = array("message" => $e->getMessage());
    //         $response->getBody()->write(json_encode($error));
    //         return $response
    //             ->withHeader('content-type', 'application/json')
    //             ->withStatus(500);
    //     }
    // });