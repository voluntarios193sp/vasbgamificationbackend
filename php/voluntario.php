<?php

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

function teste(Request $request, Response $response, array $args) {
    print_r(getDB());
}

function voluntarioNovo(Request $request, Response $response, array $args) {
    $db = getDB();
    $logger = getLogger();

    $data = $request->getParsedBody();

    $cpf = filter_var($data['cpf'], FILTER_SANITIZE_STRING);
    $cpf = str_replace(".","", $cpf);
    $cpf = str_replace("-","", $cpf);
    if (strlen($cpf) != 11) {
        $response = $response->withStatus(400);
        $response->getBody()->write("Invalid CPF");
        return $response;
    }

    $nick = filter_var($data['nickname'], FILTER_SANITIZE_STRING);
    if (strlen($nick) > 45) {
        $response = $response->withStatus(400);
        $response->getBody()->write("Invalid Nickname");
        return $response;
    }

    $avatarURL = filter_var($data['avatar_url'], FILTER_SANITIZE_STRING);

    $novoID = 0;

    try {
        $sql = "INSERT INTO voluntariojogo (cpf, nickname, avatar_url) ";
        $sql .= "VALUES (?,?,?)";
        $stmt= $db->prepare($sql);

        $dado = array($cpf);
        array_push($dado, $nick);
        array_push($dado, $avatarURL);   

        $logger->info('SQL ' . $sql);
        $logger->info('dados ', ['dado' => $dado]);

        $stmt->execute($dado);
        $novoID = $db->lastInsertId();
        $logger->info("Novo voluntario " . $novoID);
    } catch (PDOException $e) {
        $logger->addError('PDO Error', ['error' => $e]);
        $response = $response->withStatus(500);
        $response->getBody()->write("Internal Error 1");
        return $response;
    }
    
    
    $arrResp = array('id' => $novoID, 'cpf' => $cpf, 'avatar_url'=> $avatarURL, 'nickname' => $nick);

    $response = $response->withHeader('Content-Type','application/json')->withStatus(201);
    //$response->setStatus(201);
    $response->getBody()->write(json_encode($arrResp));
    return $response;
};
//echo $voluntarioNovoFn;
?>