<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require './vendor/autoload.php';

$config['displayErrorDetails'] = true;
$config['addContentLengthHeader'] = false;

include './dbconfig.php';

$app = new \Slim\App(['settings' => $config]);
$container = $app->getContainer();

$container['logger'] = function($c) {
    $logger = new \Monolog\Logger('my_logger');
    $file_handler = new \Monolog\Handler\StreamHandler('./logs/app.log');
    $logger->pushHandler($file_handler);
    return $logger;
};

$container['db'] = function ($c) {
    $db = $c['settings']['db'];
    $pdo = new PDO('mysql:host=' . $db['host'] . ';dbname=' . $db['dbname'],
        $db['user'], $db['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
};

$app->options('/{routes:.+}', function ($request, $response, $args) {
    return $response;
});

$app->add(function ($req, $res, $next) {
    $response = $next($req, $res);
    return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});

$app->get('/hello/{name}', function (Request $request, Response $response, array $args) {
    $name = $args['name'];
    $response->getBody()->write("Hello, $name");
    $this->logger->addInfo('Nome: ' . $name);
    return $response;
});

$app->get('/ocorrencia', function(Request $request, Response $response, array $args) {
    $sql = "SELECT ";
    $sql .= "nroOcorrencia as numero_ocorrencia, IFNULL(situacao_ocorrencia, 'ABERTA') situacao_ocorrencia, ";
    $sql .= "data_ocorrencia, latitude, longitude, telefone_solicitante, ";
    $sql .= "nome_solicitante, cpf_solicitante, natureza, descricao ";
    $sql .= "FROM chamados ";
    $sql .= "ORDER BY situacao_ocorrencia, natureza, data_ocorrencia";

    $stmt = $this->db->prepare($sql);
    $stmt->execute();
    $arr = $stmt->fetchAll(PDO::FETCH_CLASS);
    if(!$arr) {
        $response = $response->withStatus(404);
        return $response;
    }
    $response = $response->withStatus(200);
    $response = $response->withHeader('Content-Type','application/json');
    $response->getBody()->write(json_encode($arr));
    return $response;
});

$app->get('/ocorrencia/{id}', function(Request $request, Response $response, array $args) {
    $sql = "SELECT ";
    $sql .= "nroOcorrencia as numero_ocorrencia, IFNULL(situacao_ocorrencia, 'ABERTA') situacao_ocorrencia, ";
    $sql .= "data_ocorrencia, latitude, longitude, telefone_solicitante, ";
    $sql .= "nome_solicitante, cpf_solicitante, natureza, descricao ";
    $sql .= "FROM chamados ";
    $sql .= "WHERE nroOcorrencia = ?";
    $sql .= "ORDER BY situacao_ocorrencia, natureza, data_ocorrencia";

    $ocorrenciaId = (int)$args['id'];

    $stmt = $this->db->prepare($sql);
    $stmt->execute([$ocorrenciaId]);
    $arr = $stmt->fetchAll(PDO::FETCH_CLASS);
    if(!$arr) {
        $response = $response->withStatus(404);
        return $response;
    }
    $response = $response->withStatus(200);
    $response = $response->withHeader('Content-Type','application/json');
    $response->getBody()->write(json_encode($arr));
    return $response;
});

$app->post('/ocorrencia', function(Request $request, Response $response, array $args) {
    $data = $request->getParsedBody();
    $sql = "INSERT INTO chamados (situacao_ocorrencia, data_ocorrencia, latitude, longitude, telefone_solicitante, ";
    $sql .= "data_geracao, data_chamada, ";
    $sql .= "nome_solicitante, cpf_solicitante, natureza, descricao) ";
    $sql .= "VALUES ('ABERTA',?,?,?,?,now(),now(),?,?,?,?)";

    $stmt= $this->db->prepare($sql);
    $dado = array(filter_var($data['data_ocorrencia'], FILTER_SANITIZE_STRING));
    array_push($dado, filter_var($data['latitude'], FILTER_SANITIZE_STRING));
    array_push($dado, filter_var($data['longitude'], FILTER_SANITIZE_STRING));
    array_push($dado, filter_var($data['telefone_solicitante'], FILTER_SANITIZE_STRING));
    array_push($dado, filter_var($data['nome_solicitante'], FILTER_SANITIZE_STRING));
    array_push($dado, filter_var($data['cpf_solicitante'], FILTER_SANITIZE_STRING));
    array_push($dado, filter_var($data['natureza'], FILTER_SANITIZE_STRING));
    array_push($dado, filter_var($data['descricao'], FILTER_SANITIZE_STRING));

    $this->logger->addInfo($sql);
    $this->logger->addInfo($dado);

    $stmt->execute($dado);
    $idChamada = $this->db->lastInsertId();
    $this->logger->addInfo("Nova chamada " . $idChamada);
    $response = $response->withHeader('Content-Type','application/json');
    $response->getBody()->write("{\"ocorrencia\":" . $idChamada . "}");
    return $response;
});

///gamevasb/api/v1/
$app->group('/gamevasb', function() use ($app) {
    $app->group('/api', function() use ($app){
        $app->group('/v1', function() use ($app) {
            
        });
    });
});

$app->run();
?>
