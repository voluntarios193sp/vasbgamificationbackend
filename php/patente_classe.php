<?php

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;


function patenteBuscaPorId(Request $request, Response $response, array $args) {
    $db = getDB();
    $logger = getLogger();
    $pttID = filter_var($args['id'], FILTER_SANITIZE_STRING);
    try {
        $sql = "SELECT ptt_id as id, classe, pontuacao_minima FROM patente WHERE ptt_id = ? ORDER BY pontuacao_minima";
        $dado = array($pttID);
        $stmt = $db->prepare($sql);
        $stmt->execute($dado);
        $arrReg = $stmt->fetchAll();
        if (count($arrReg)<1) {
            $arrResp = array('status' => 'Classe/Patente/Nivel não cadastrado/encontrado');
            $response = $response->withJson($arrResp, 404);
            return $response;
        }
        $classe = $arrReg[0];
    } catch (PDOException $e) {
        $logger->addError('PDO Error', ['error' => $e, 'sql' => $sql]);
        $arrResp = array('status' => 'Error dealing with database records. See logs for futher details');
        $response = $response->withJson($arrResp, 500);
        return $response;
    }
    return $response->withJSON($classe, 200);
}


function patenteListaTodos(Request $request, Response $response, array $args) {
    $db = getDB();
    $logger = getLogger();
    try {
        $sql = "SELECT ptt_id as id, classe, pontuacao_minima FROM patente order by pontuacao_minima";
        $result = $db->query($sql)->fetchAll();
        if (count($result)<1) {
            $arrResp = array('status' => 'Classes/Patentes/Niveis não cadastrados');
            return $response->withJson($arrResp, 404);
        }
    } catch (PDOException $e) {
        $logger->addError('PDO Error', ['error' => $e, 'sql' => $sql]);
        $arrResp = array('status' => 'Error dealing with database records. See logs for futher details');
        return $response->withJson($arrResp, 500);
    }
    return $response->withJSON($result, 200);
}

function patenteNovo(Request $request, Response $response, array $args) {
    $db = getDB();
    $logger = getLogger();

    $data = $request->getParsedBody();

    $classe = filter_var($data['classe'], FILTER_SANITIZE_STRING);
    $ptMinima = filter_var($data['pontuacao_minima'], FILTER_SANITIZE_STRING);
    $dado = array($classe);
    array_push($dado, $ptMinima);
    if (strlen($classe)<2 || ($ptMinima<0)) {
        $logger->addError('Invalid data to insert', ['dado' => print_r($dado, true)]);
        $arrResp = array('status' => 'Dados invalidos. Verifique se o nome da classe esta correto ou se a pontuacao minima é maior ou igual a zero');
        $response = $response->withJson($arrResp, 400);
        return $response;
    }
    try {
        $sql = "INSERT INTO patente (classe, pontuacao_minima) VALUES (?,?)";
        $stmt= $db->prepare($sql);
        $stmt->execute($dado);
        $novoID = $db->lastInsertId();
    } catch (PDOException $e) {
        $logger->addError('PDO Error', ['error' => $e, 'sql' => $sql, 'dado' => print_r($dado, true)]);
        $arrResp = array('status' => 'Error dealing with database records. See logs for futher details');
        $response = $response->withJson($arrResp, 500);
        return $response;
    }
        
    $arrResp = array('status' => 'nova classe/patente/nivel registrada com sucesso');
    $arrResp['classe_id'] = $novoID;
    $response = $response->withJson($arrResp, 201);
    return $response;
};
?>