<?php

use Slim\App;
use Auth0\SDK\JWTVerifier;

return function (App $app) {
    $container = $app->getContainer();

    // view renderer
    $container['renderer'] = function ($c) {
        $settings = $c->get('settings')['renderer'];
        return new \Slim\Views\PhpRenderer($settings['template_path']);
    };

    // monolog
    $container['logger'] = function ($c) {
        $settings = $c->get('settings')['logger'];
        $logger = new \Monolog\Logger($settings['name']);
        $logger->pushProcessor(new \Monolog\Processor\UidProcessor());
        $logger->pushHandler(new \Monolog\Handler\StreamHandler($settings['path'], $settings['level']));
        return $logger;
    };

    // PDO database library 
    $container['db'] = function ($c) {
        $settings = $c->get('settings')['db'];
        $pdo = new PDO("mysql:host=" . $settings['host'] . ";dbname=" . $settings['dbname'],
            $settings['user'], $settings['pass']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    };

    class Auth0 {

        protected $token;
        protected $tokenInfo;
        protected $userInfo;
        private $valid_audiences;
        private $authorized_iss;
        private $supported_algs;
        private $namespace;

        public function __construct($valid_audiences, $authorized_iss, $supported_algs, $namespace) 
        {
            $this->valid_audiences = $valid_audiences;
            $this->authorized_iss = $authorized_iss;
            $this->supported_algs = $supported_algs;
            $this->namespace = $namespace;
        }

        public function setCurretToken($token) {
            try {
                $verifier = new JWTVerifier([
                    'valid_audiences' => $this->valid_audiences,
                    'authorized_iss' => $this->authorized_iss,
                    'supported_algs' => $this->supported_algs
                ]);
                $this->token = $token;
                $this->tokenInfo = $verifier->verifyAndDecode($token);
            }
            catch(\Auth0\SDK\Exception\CoreException $e) {
                throw $e;
            }
        }

        public function getCurrentToken() {
            return $this->tokenInfo;
        }

        public function getEmail() {
            return $this->tokenInfo->{$this->namespace . "email"};
        }

        function checkJWT($headers) {
            if (!isset($headers['HTTP_AUTHORIZATION'])) {
                header('HTTP/1.0 401 Unauthorized');
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(array("message" => "No token provided."));
                exit();
            }

            if ($headers['HTTP_AUTHORIZATION'] == null) {
                header('HTTP/1.0 401 Unauthorized');
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(array("message" => "No authorization header sent."));
                exit();
            }

            $token = str_replace('bearer ', '', $headers['HTTP_AUTHORIZATION'][0]);
            $token = str_replace('Bearer ', '', $token);
        
            try {
                $this->setCurretToken($token);
            }
            catch(\Auth0\SDK\Exception\CoreException $e) {
                header('HTTP/1.0 401 Unauthorized');
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(array("message" => $e->getMessage()));
                exit();
            }
        }
    }


    $container['auth0'] = function($c) {
        $settings = $c->get('settings')['auth0'];
        $auth0 = new Auth0(
            $settings['valid_audiences'],
            $settings['authorized_iss'],
            $settings['supported_algs'],
            $settings['namespace']
        );
        return $auth0;
    };
};
