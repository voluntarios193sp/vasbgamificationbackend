<?php

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Firebase\JWT\JWT;


function pontoNovo(Request $request, Response $response, array $args) {
    $db = getDB();
    $logger = getLogger();

    $data = $request->getParsedBody();

    $pctID = filter_var($data['pontuacao_id'], FILTER_SANITIZE_STRING);
    $playerID = filter_var($args['id'], FILTER_SANITIZE_STRING);
    $jwt = $request->getHeaders();
    $jwt = str_replace('Bearer ', '', $jwt['HTTP_AUTHORIZATION'][0]);
    $jwtDecoded = JWT::decode($jwt, getOAuthSecret(), ['HS256']);
    //print_r($jwtDecoded);    exit;
    
    try {
        $sql = "SELECT vasb_id FROM voluntariojogo where uuid = ?";
        $stmt= $db->prepare($sql);
        $stmt->execute([$playerID]);
        $arr = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if(!$arr) {
            $data = array('error' => 'nao encontrado voluntario com esse ID');
            $response = $response->withJson($data, 404);
            return $response;
        }
        // var_dump($arr);
        $vasbID = $arr[0]["vasb_id"];
        
        $sql = "SELECT pontuacao FROM pontocorrencia where pct_id = ?";
        $stmt= $db->prepare($sql);
        $stmt->execute([$pctID]);
        $arr = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if(!$arr) {
            $data = array('error' => 'nao encontrado ponto com esse ID');
            $response = $response->withJson($data, 404);
            return $response;
        }
        // var_dump($arr);
        $pontuacao = $arr[0]["pontuacao"];

        $sql = "INSERT INTO voluntarioponto (vasb_id, pct_id, inserido_por, quando) VALUES (?,?,?,NOW())";
        $stmt= $db->prepare($sql);
        $dado = array($vasbID);
        array_push($dado, $pctID);   
        array_push($dado, $jwtDecoded->sub);
        $stmt->execute($dado);

        $sql = "INSERT INTO voluntariosaldo (vasb_id, pct_id, valor, entrada_saida) VALUES (?,?,?,1)";
        $stmt= $db->prepare($sql);
        $dado = array($vasbID);
        array_push($dado, $pctID);   
        array_push($dado, $pontuacao);
        // var_dump($dado);
        $stmt->execute($dado);

    } catch (PDOException $e) {
        $logger->addError('PDO Error', ['error' => $e, 'sql' => $sql, 'dado' => print_r($dado, true)]);
        $arrResp = array('status' => 'Error dealing with database records. See logs for futher details');
        $response = $response->withJson($arrResp, 500);
        return $response;
    }
        
    $arrResp = array('status' => 'pontuacao registrada com sucesso');
    $response = $response->withJson($arrResp, 201);
    return $response;
};
?>