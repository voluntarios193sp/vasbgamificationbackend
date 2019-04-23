<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Firebase\JWT\JWT;
use Tuupola\Base62;

//to generate users: htpasswd -nbBC 10 vasbmobile cobom

function oauthGeraToken(Request $request, Response $response, array $args) {
    //$db = getDB();
    $logger = getLogger();
    $now = new DateTime();
    $future = new DateTime("now +2 hours");
    $server = $request->getServerParams();
    $jti = (new Base62)->encode(random_bytes(16));
    $scopes = "all";
    $payload = [
        "iat" => $now->getTimeStamp(),
        "exp" => $future->getTimeStamp(),
        "jti" => $jti,
        "sub" => $server["PHP_AUTH_USER"],
        "scope" => $scopes
    ];
    $secret = getOAuthSecret();
    $token = JWT::encode($payload, $secret, "HS256");
    $data["token"] = $token;
    $data["expires"] = $future->getTimeStamp();
    return $response->withStatus(201)
        ->withHeader("Content-Type", "application/json")
        ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
}
?>