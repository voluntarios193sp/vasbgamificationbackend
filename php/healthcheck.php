<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

function(Request $request, Response $response, array $args) {
    $response = $response->withStatus(200);
    $response->getBody()->write("{\"status\":\"available\"}");
    return $response;
}
?>