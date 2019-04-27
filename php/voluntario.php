<?php
include_once "./lib/voluntario.php";

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

function teste(Request $request, Response $response, array $args) {
    print_r(getDB());
}

function voluntarioListarTodos(Request $request, Response $response, array $args) {
    $db = getDB();
    $logger = getLogger();
    try {
        $sql = "SELECT uuid, nickname, avatar_url FROM voluntariojogo ORDER BY vasb_id";
        $results = $db->query($sql);       
        $arrVoluntarios = $results->fetchAll(PDO::FETCH_ASSOC);
        if (count($arrVoluntarios)>0) {
            $response = $response->withHeader('Content-Type','application/json')->withJson($arrVoluntarios, 200);    
        } else {
            $response = $response->withStatus(404);
        }
        return $response;

    } catch (PDOException $e) {
        $logger->addError('PDO Error', ['error' => $e]);
        $response = $response->withStatus(500);
        $response->getBody()->write("Internal Error 1");
        return $response;
    }            
}

function voluntarioListarPorIDOuCpf(Request $request, Response $response, array $args) {
    $db = getDB();
    $logger = getLogger();
    $voluntario = fnGetVoluntario($db, $logger, $args['id']);    
    if (is_null($voluntario)) {
        $response = $response->withStatus(404);
    } else {
        unset($voluntario["vasb_id"]);
        $response = $response->withHeader('Content-Type','application/json')->withJson($voluntario, 200);            
    }
    return $response;
}

function voluntarioExtrato(Request $request, Response $response, array $args) {
    $db = getDB();
    $logger = getLogger();
    $voluntario = fnGetVoluntario($db, $logger, $args['id']);    
    if (is_null($voluntario)) {
        $data = array('error' => 'voluntario nao encontrado');
        $response = $response->withJson($data, 404);
        return $response;
    } else {
        try {
            $sql = 'SELECT id, valor, entrada_saida, item FROM 
                    (SELECT a.vasb_id, a.vsd_id as id, a.valor, a.entrada_saida, e.nome as item
                    FROM voluntariosaldo a , equipamento e
                    WHERE a.eqt_id = e.eqt_id
                    UNION
                    SELECT a.vasb_id, a.vsd_id as id, a.valor, a.entrada_saida, p.sigla as item
                    FROM voluntariosaldo a, pontocorrencia p
                    WHERE a.pct_id = p.pct_id) TABA
                    WHERE vasb_id = ?
                    order by id';
            $dado = array($voluntario["vasb_id"]);
            $stmt = $db->prepare($sql);   
            $stmt->execute($dado);    
            $arrExtrato = $stmt->fetchAll(PDO::FETCH_ASSOC);   
            if (count($arrExtrato)<1) {
                $logger->addError('[voluntarioExtrato] Itens nao encontrados', ['query'=> $sql, 'params'=>print_r($voluntario, true)]);
                $data = array('error' => 'itens do extrato do voluntario informado nao encontrado');
                $response = $response->withJson($data, 404);
                return $response;
            }
        } catch (PDOException $e) {
            $logger->addError('PDO Error', ['error' => $e, 'query'=> $sql, 'params'=>print_r($dado, true)]);
            $data = array('error' => 'erro envolvendo banco de dados. favor verificar logs.');
            $response = $response->withJson($data, 500);
            return $response;
        }    
        $response = $response->withHeader('Content-Type','application/json')->withJson($arrExtrato, 200);            
    }
    return $response;
}


function voluntarioEquipamentos(Request $request, Response $response, array $args) {
    $db = getDB();
    $logger = getLogger();
    $voluntario = fnGetVoluntario($db, $logger, $args['id']);    
    if (is_null($voluntario)) {
        $data = array('error' => 'voluntario nao encontrado');
        $response = $response->withJson($data, 404);
        return $response;
    } else {
        try {
            $sql = 'SELECT 
                    a.veq_id as id,
                    a.eqt_id as equipamento_id,
                    a.comprado_quando,
                    b.nome,
                    b.descricao,
                    b.imagem_url
                    FROM voluntatarioequipamento a, equipamento b
                    WHERE a.eqt_id = b.eqt_id
                    AND a.vasb_id = ?';
            $dado = array($voluntario["vasb_id"]);
            $stmt = $db->prepare($sql);   
            $stmt->execute($dado);    
            $arrExtrato = $stmt->fetchAll(PDO::FETCH_ASSOC);   
            if (count($arrExtrato)<1) {
                $logger->addError('[voluntarioEquipamentos] Itens nao encontrados', ['query'=> $sql, 'params'=>print_r($voluntario, true)]);
                $data = array('error' => 'equipamentos do voluntario informado nao encontrados');
                $response = $response->withJson($data, 404);
                return $response;
            }
        } catch (PDOException $e) {
            $logger->addError('PDO Error', ['error' => $e, 'query'=> $sql, 'params'=>print_r($dado, true)]);
            $data = array('error' => 'erro envolvendo banco de dados. favor verificar logs.');
            $response = $response->withJson($data, 500);
            return $response;
        }    
        $response = $response->withHeader('Content-Type','application/json')->withJson($arrExtrato, 200);            
    }
    return $response;
}


function voluntarioSaldo(Request $request, Response $response, array $args) {
    $db = getDB();
    $logger = getLogger();
    $voluntario = fnGetVoluntario($db, $logger, $args['id']);    
    if (is_null($voluntario)) {
        $data = array('error' => 'voluntario nao encontrado');
        $response = $response->withJson($data, 404);
        return $response;
    } else {
        try {
            $sql = "SELECT saldo FROM vw_saldovoluntario WHERE vasb_id = ?";
            $dado = array($voluntario["vasb_id"]);
            $stmt = $db->prepare($sql);   
            $stmt->execute($dado);    
            $arrSaldo = $stmt->fetchAll(PDO::FETCH_ASSOC);   
            if (count($arrSaldo)>0) {
                $saldo = $arrSaldo[0]; 
            } else {
                $logger->addError('[voluntarioSaldo] Saldo nao encontrado', ['query'=> $sql, 'params'=>print_r($voluntario, true)]);
                $data = array('error' => 'saldo do voluntario informado nao encontrado');
                $response = $response->withJson($data, 404);
                return $response;
            }
        } catch (PDOException $e) {
            $logger->addError('PDO Error', ['error' => $e, 'query'=> $sql, 'params'=>print_r($dado, true)]);
            $data = array('error' => 'erro envolvendo banco de dados. favor verificar logs.');
            $response = $response->withJson($data, 500);
            return $response;
        }    
        $response = $response->withHeader('Content-Type','application/json')->withJson($saldo, 200);            
    }
    return $response;
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
    $uuid = bin2hex(random_bytes(8));

    $novoID = 0;

    try {
        $sql = "INSERT INTO voluntariojogo (cpf, nickname, avatar_url, uuid) ";
        $sql .= "VALUES (?,?,?,?)";
        $stmt= $db->prepare($sql);

        $dado = array($cpf);
        array_push($dado, $nick);
        array_push($dado, $avatarURL);   
        array_push($dado, $uuid);
        $stmt->execute($dado);
        $novoID = $db->lastInsertId();
        $logger->info("Novo voluntario " . $novoID);

        $sql = "INSERT INTO voluntariosaldo (vasb_id, eqt_id, valor, entrada_saida) VALUES (?,?,?,0)";
        $stmt= $db->prepare($sql);
        $dado = array($novoID);
        array_push($dado, 0);   
        array_push($dado, 0);
        array_push($dado, 0);
        $stmt->execute($dado);

        $sql = "INSERT INTO voluntariosaldo (vasb_id, pct_id, valor, entrada_saida) VALUES (?,?,?,0)";
        $stmt= $db->prepare($sql);
        $dado = array($novoID);
        array_push($dado, 0);   
        array_push($dado, 0);
        array_push($dado, 1);
        $stmt->execute($dado);

    } catch (PDOException $e) {
        $logger->addError('PDO Error', ['error' => $e]);
        $response = $response->withStatus(500);
        $response->getBody()->write("Internal Error 1");
        return $response;
    }
        
    $arrResp = array('id' => $uuid, 'cpf' => $cpf, 'avatar_url'=> $avatarURL, 'nickname' => $nick);

    $response = $response->withHeader('Content-Type','application/json')->withStatus(201);
    //$response->setStatus(201);
    $response->getBody()->write(json_encode($arrResp));
    return $response;
};
//echo $voluntarioNovoFn;
?>