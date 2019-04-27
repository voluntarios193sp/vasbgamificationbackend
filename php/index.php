<?php
//phpinfo();
//exit;

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require './vendor/autoload.php';

$config['displayErrorDetails'] = true;
$config['addContentLengthHeader'] = true;

require './dbconfig.php';
require './oauthconfig.php';
require './logger.php';

$app = new \Slim\App(['settings' => $config]);
$container = $app->getContainer();
$container['logger'] = getLogger();    
$container['db'] = getDB();
$container['secretkey'] = getOAuthSecret();

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

$app->add(new Tuupola\Middleware\HttpBasicAuthentication([
    "path" => ["/gamevasb/api/v1/oauth"],
    "realm" => "Protected",
    "secure" => true,
    "relaxed" => ["localhost"],    
    "authenticator" => new \Tuupola\Middleware\HttpBasicAuthentication\PdoAuthenticator([
        "pdo" => getDB(),
        "table" => "usuariooauth"
    ]),
    "error" => function ($response, $arguments) {
        $data = [];
        $data["status"] = "error";
        $data["message"] = $arguments["message"];
        return $response->write(json_encode($data, JSON_UNESCAPED_SLASHES));
    }
]));

$app->add(new \Tuupola\Middleware\JwtAuthentication([
    "logger" => $container['logger'],
    "secure" => true,
    "relaxed" => ["localhost"],   
    "secret" => $container['secretkey'],
    "rules" => [
		new \Tuupola\Middleware\JwtAuthentication\RequestPathRule([
			"path" => "/gamevasb/api/v1",
			"ignore" => [
				"/gamevasb/api/v1/healthcheck",
				"/gamevasb/api/v1/oauth"
			]
		])
    ],
    "error" => function ($response, $arguments) {
        $data["status"] = "error";
        $data["message"] = $arguments["message"];
        return $response
            ->withHeader("Content-Type", "application/json")
            ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }
]));

$app->get('/hello/{name}', function (Request $request, Response $response, array $args) {
    $name = $args['name'];
    $response->getBody()->write("Hello, $name");
    $this->logger->info('Nome: ' . $name);
    return $response;
});

$app->get('/ocorrencia', function(Request $request, Response $response, array $args) {
    $this->logger->info("Busca ocorrencia");
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

    $this->logger->info($sql);
    $this->logger->info('dados ', ['dado' => $dado]);

    $stmt->execute($dado);
    $idChamada = $this->db->lastInsertId();
    $this->logger->info("Nova chamada " . $idChamada);
    $response = $response->withHeader('Content-Type','application/json');
    $response = $response->withStatus(201);
    $response->getBody()->write("{\"ocorrencia\":" . $idChamada . "}");
    return $response;
});

require './healthcheck.php';
require './voluntario.php';
require './ponto.php';
require './equipamento.php';
require './oauth.php';

///gamevasb/api/v1/
$app->group('/gamevasb', function() use ($app) {
    $app->group('/api', function() use ($app){
        $app->group('/v1', function() use ($app) {
            $app->get('/oauth', 'oauthGeraToken');

            $app->post('/voluntario', 'voluntarioNovo');
            $app->get('/voluntario', 'voluntarioListarTodos');
            $app->get('/voluntario/{id}', 'voluntarioListarPorIDOuCpf');
            $app->get('/voluntario/{id}/saldo', 'voluntarioSaldo');
            $app->get('/voluntario/{id}/extrato', 'voluntarioExtrato');
            $app->get('/voluntario/{id}/equipamento', 'voluntarioEquipamentos');
            $app->post('/voluntario/{id}/pontuacao', 'pontoNovo');
            $app->post('/voluntario/{id}/equipamento', 'equipamentoNovo');

            $app->get('/equipamento', 'equipamentoListaTodos');

            $app->get('/healthcheck','healthCheckFn');
        });
    });
});

$app->run();
?>
