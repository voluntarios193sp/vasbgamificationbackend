<?php

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;


function equipamentoListaTodos(Request $request, Response $response, array $args) {
    $db = getDB();
    $logger = getLogger();
    try {
        $sql = "SELECT b.eqt_id as equipamento_id, b.nome, b.descricao, b.imagem_url, b.preco FROM equipamento b ORDER BY b.nome";
        $result = $db->query($sql)->fetchAll();
        if (count($result)<1) {
            $arrResp = array('status' => 'Equipamentos não cadastrados');
            $response = $response->withJson($arrResp, 404);
        }
    } catch (PDOException $e) {
        $logger->addError('PDO Error', ['error' => $e, 'sql' => $sql]);
        $arrResp = array('status' => 'Error dealing with database records. See logs for futher details');
        $response = $response->withJson($arrResp, 500);
        return $response;
    }
    return $response->withJSON($result, 200);
}

function equipamentoNovo(Request $request, Response $response, array $args) {
    $db = getDB();
    $logger = getLogger();

    $data = $request->getParsedBody();

    $eqtID = filter_var($data['equipamento_id'], FILTER_SANITIZE_STRING);
    $playerID = filter_var($args['id'], FILTER_SANITIZE_STRING);
    
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

        $sql = "SELECT saldo FROM vw_saldovoluntario where vasb_id = ?";
        $stmt= $db->prepare($sql);
        $stmt->execute([$vasbID]);
        $arr = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if(!$arr) {
            $data = array('error' => 'nao encontrado saldo para esse ID');
            $response = $response->withJson($data, 404);
            return $response;
        }
        // var_dump($arr);
        $saldo = $arr[0]["saldo"];

        $dado = array($eqtID);
        array_push($dado, $vasbID);
        $sql = 'SELECT 
                    b.preco 
                FROM
                    patente as a,
                    equipamento as b,
                    vw_ganhosvoluntario as c
                WHERE b.eqt_id = ?
                    AND c.vasb_id = ?
                    AND a.ptt_id = b.ptt_id
                    AND c.entrada >= a.pontuacao_minima';
        $stmt= $db->prepare($sql);
        $stmt->execute($dado);
        $arr = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if(!$arr) {
            $data = array('error' => 'voluntario nao atingiu a classe necessaria');
            $response = $response->withJson($data, 400);
            return $response;
        }
        // var_dump($arr);
        $preco = $arr[0]["preco"];

        if ($saldo >= $preco) {
            $sql = "INSERT INTO voluntatarioequipamento (vasb_id, eqt_id, comprado_quando) VALUES (?,?,NOW())";
            $stmt= $db->prepare($sql);
            $dado = array($vasbID);
            array_push($dado, $eqtID);   
            $stmt->execute($dado);

            $sql = "INSERT INTO voluntariosaldo (vasb_id, eqt_id, valor, entrada_saida) VALUES (?,?,?,0)";
            $stmt= $db->prepare($sql);
            $dado = array($vasbID);
            array_push($dado, $eqtID);   
            array_push($dado, $preco);
            // var_dump($dado);
            $stmt->execute($dado);
        } else {
            $data = array('error' => 'voluntario nao tem saldo suficiente');
            $response = $response->withJson($data, 400);
            return $response;
        }

    } catch (PDOException $e) {
        $logger->addError('PDO Error', ['error' => $e, 'sql' => $sql, 'dado' => print_r($dado, true)]);
        $arrResp = array('status' => 'Error dealing with database records. See logs for futher details');
        $response = $response->withJson($arrResp, 500);
        return $response;
    }
        
    $arrResp = array('status' => 'compra de equipamento registrada com sucesso');
    $response = $response->withJson($arrResp, 201);
    return $response;
};
?>