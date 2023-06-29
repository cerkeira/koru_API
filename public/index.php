<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Selective\BasePath\BasePathMiddleware;
use Slim\Factory\AppFactory;
use App\Models\DB;

require '../src/middleware/TokenAuth.php';

require_once __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();
$app->setBasePath('/proj/koru');

$app->addBodyParsingMiddleware();

$app->addRoutingMiddleware();
$app->add(new BasePathMiddleware($app));
$app->addErrorMiddleware(true, true, true);

$app->get('/welcome/test', function (Request $request, Response $response) {
   $response->getBody()->write('Hello World! This is the KoruDB API.');
   return $response;
});

function convertImageToBase64($path, $imagePath) {
  if ($imagePath == '') {
      $imagePath = 'test.png';
  }
  $imageFullPath = __DIR__ . '/../src/images/'.$path.'/' . $imagePath;

  if (file_exists($imageFullPath)) {
      $imageContent = file_get_contents($imageFullPath);
      return base64_encode($imageContent);
  }else{
      $imagePath = 'test.png';
      $imageFullPath = __DIR__ . '/../src/images/'.$path.'/' . $imagePath;
      $imageContent = file_get_contents($imageFullPath);
      return base64_encode($imageContent);
  }
  return null;
}

$whitelist = [
  // '/proj/koru/user'
  '/user',
  '/welcome/test'
];
$pdo = new PDO('mysql:host=localhost;dbname=korudb', 'root', '');
$app->add(new AuthenticationMiddleware($pdo, $whitelist));
require '../src/routes/event.php';
require '../src/routes/user.php';
require '../src/routes/profile.php';
require '../src/routes/qr.php';



// $app->get('/transaction', function (Request $request, Response $response) {
//   $data = $request->getQueryParams();

//   $perPage = $data['count'];
//   $page = $data['page'];
//   $offset = ($page - 1) * $perPage;


//   $sql = "SELECT * FROM transaction LIMIT :limit OFFSET :offset";

//   try {
//       $db = new Db();
//       $conn = $db->connect();

//       $stmt = $conn->prepare($sql);
//       $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
//       $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
//       $stmt->execute();
//       $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
//       $db = null;

//       $responseData = [
//           'perPage' => $perPage,
//           'currentPage' => $page,
//           'data' => $transactions,
//       ];

//       $response->getBody()->write(json_encode($responseData));
//       return $response
//           ->withHeader('content-type', 'application/json')
//           ->withStatus(200);
//   } catch (PDOException $e) {
//       $error = array("message" => $e->getMessage());
//       $response->getBody()->write(json_encode($error));
//       return $response
//           ->withHeader('content-type', 'application/json')
//           ->withStatus(500);
//   }
// });



// $app->delete('/transaction/delete/{id}', function (Request $request, Response $response, array $args) {
//   $id = $args["id"];

//   $sql = "DELETE FROM transaction WHERE id_transaction = :id";

//   try {
//       $db = new Db();
//       $conn = $db->connect();

//       $stmt = $conn->prepare($sql);
//       $stmt->bindValue(':id', $id, PDO::PARAM_INT);
//       $result = $stmt->execute();

//       $db = null;
//       $response->getBody()->write(json_encode($result));
//       return $response
//           ->withHeader('content-type', 'application/json')
//           ->withStatus(200);
//   } catch (PDOException $e) {
//       $error = array(
//           "message" => $e->getMessage()
//       );

//       $response->getBody()->write(json_encode($error));
//       return $response
//           ->withHeader('content-type', 'application/json')
//           ->withStatus(500);
//   }
// });

$app->run();