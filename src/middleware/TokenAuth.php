<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class AuthenticationMiddleware
{

    private $pdo;
    private $whitelist;

    public function __construct(PDO $pdo, array $whitelist = [])
    {
        $this->whitelist = $whitelist;
        $this->pdo = $pdo;
    }


    private function isValidSessionToken(string $sessionToken, $id): bool
    {
        
        $stmt = $this->pdo->prepare("SELECT token, token_expire FROM user WHERE id_user = $id");
        $stmt->execute();
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($session['token_expire'] < date('Y-m-d H:i:s') || $session['token'] != $sessionToken) {
            return false; 
        }

        $stmt = $this->pdo->prepare("UPDATE user SET token_expire = :tokenExpire WHERE id_user = :id");
        $stmt->bindValue(':tokenExpire', date('Y-m-d H:i:s', strtotime('+1 month')));
        $stmt->bindValue(':id', $id);
        $stmt->execute();

        return true;
    }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $path = $request->getUri()->getPath();
        $id = $request->getHeaderLine('id');

        if ($this->isWhitelisted($path)) {
            return $handler->handle($request);
        }

        $sessionToken = $request->getHeaderLine('Authorization');

        if (!$this->isValidSessionToken($sessionToken, $id)) {
            $response = new Response();
            $response->getBody()->write('Unauthorized');
            return $response->withStatus(401);
        }

        return $handler->handle($request);
    }

    private function isWhitelisted(string $path): bool
    {
        foreach ($this->whitelist as $whitelistedPath) {
            if (strpos($path, $whitelistedPath) === 0) {
                return true;
            }
        }

        return false;
    }

}