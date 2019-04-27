<?php
function fnGetVoluntario($db, $logger, $param) {
    $param = filter_var($param, FILTER_SANITIZE_STRING);
    $voluntario = null;
    try {
        $sql = "SELECT vasb_id, uuid, nickname, avatar_url FROM voluntariojogo WHERE ";
        if (is_numeric($param)) {
            $sql .= "cpf";
        } else {
            $sql .= "uuid";
        }
        $sql .= " = ?";
        $dado = array($param);
        $stmt = $db->prepare($sql);   
        $stmt->execute($dado);    
        $arrVoluntarios = $stmt->fetchAll(PDO::FETCH_ASSOC);   
        if (count($arrVoluntarios)>0) {
            $voluntario = $arrVoluntarios[0]; 
        }
    } catch (PDOException $e) {
        $logger->addError('PDO Error', ['error' => $e, 'query'=> $sql, 'params'=>print_r($dado, true)]);
    }    
    return $voluntario;
}
?>