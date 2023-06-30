# koru_API

API desenvolvida em exclusivo para o projeto KORU.


Requisitos:
-PHP 7.4.30

-Composer


Instalação:


composer install

php -S localhost:80 -t public



ALERTA:


1- Pode ser necessário configurar vhosts para o enderenço localhost:80.

2- As linhas:

  index.php -> $pdo = new PDO('mysql:host=localhost;dbname=korudb', 'root', '');
  
  Db.php -> private $host = 'localhost'; private $user = 'root'; private $pass = ''; private $dbname = 'korudb';
  
Podem ser adaptadas com outras credenciais.
