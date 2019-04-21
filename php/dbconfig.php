<?php
function getDB() {
    $config['db']['host']   = 'localhost';
    $config['db']['user']   = 'root';
    $config['db']['pass']   = "MeuLindoDb<3";
    $config['db']['dbname'] = 'siopm_bombeiro';
    $db = $config['db'];
    $pdo = new PDO('mysql:host=' . $db['host'] . ';dbname=' . $db['dbname'],
        $db['user'], $db['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}
?>