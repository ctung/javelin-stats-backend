<?php

use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;

return function (App $app) {
    $container = $app->getContainer();

    $app->get('/api/build_link/{id}', function ($request, $response, $args) {
        $sth = $this->db->prepare('SELECT build FROM build_links WHERE id=:id');
        $sth->bindParam('id', $args['id']);
        $sth->execute();
        $url = $sth->fetchObject();
        return $this->response
            ->withJson($url);
    });

    $app->post('/api/build_link', function ($request, $response) {
        $input = $request->getParsedBody();
        $sql = "INSERT INTO build_links (date, build) VALUES (NOW(), :build)";
        $sth = $this->db->prepare($sql);
        $sth->bindParam("build", $input['build']);
        $sth->execute();
        $input['id'] = $this->db->lastInsertId();
        return $this->response->withJson($input);
    });

    $app->get('/api/test', function($request, $response) use ($app) {
        $headers = $request->getHeaders();
        $this->auth0->checkJWT($headers);
        echo json_encode($this->auth0->getCurrentToken());
    });

    $app->get('/api/items', function($request, $response) {
        $this->auth0->checkJWT($request->getHeaders());
        $email = $this->auth0->getEmail();
        $sth = $this->db->prepare("SELECT * FROM saved_items WHERE email=:email");
        $sth->bindParam('email',$email);
        $result = $sth->execute();
        
        $retval = array();
        if ($result && $sth->rowCount() > 0) {
            $items = $sth->fetchAll(PDO::FETCH_ASSOC);
            foreach ($items as $item) {
                $retval[] = array(
                    'idx' => (int)$item['idx'],
                    'type'=> $item['type'],
                    'id' => (int)$item['id'],
                    'i' => json_decode($item['i'])
                );
            }
        }
        echo json_encode(array('items' => $retval));
    });

    $app->delete('/api/items/{idx}', function($request, $response, $args) {
        $this->auth0->checkJWT($request->getHeaders());
        $email = $this->auth0->getEmail();
        $sth = $this->db->prepare("DELETE FROM saved_items WHERE idx=:idx AND email=:email");
        $sth->bindParam("idx", $args['idx']);
        $sth->bindParam("email", $email);
        $result = $sth->execute();
        echo json_encode($result);
    });

    $app->post('/api/items', function($request, $response) {
        $this->auth0->checkJWT($request->getHeaders());
        $email = $this->auth0->getEmail();
        $body = $request->getParsedBody();
        $item = $body['item'];
        $type = $body['type'];
        $i = json_encode($item['i']);

        $sth = $this->db->prepare("SELECT idx FROM saved_items WHERE idx=:idx AND email=:email AND type=:type");
        $sth->bindParam("idx", $item['idx']);
        $sth->bindParam("email", $email);
        $sth->bindParam("type", $type);
        $result = $sth->execute();
        if ($result && $sth->rowCount() == 1) {
            $sth = $this->db->prepare("UPDATE saved_items SET id=:id, i=:i WHERE idx=:idx ");
            $sth->bindParam('idx', $item['idx']);
            $sth->bindParam('id', $item['id']);
            $sth->bindParam('i', $i);
            $result = $sth->execute();
        } else {
            $sth = $this->db->prepare("INSERT INTO saved_items (email, type, id, i) VALUES (:email, :type, :id, :i)");
            $sth->bindParam('email', $email);
            $sth->bindParam('type', $type);
            $sth->bindParam('id', $item['id']);
            $sth->bindParam('i', $i);
            $result = $sth->execute();
            $item['idx'] = $this->db->lastInsertId();
        }
        echo json_encode($item);      
    });

    $app->get('/api/builds', function($request, $response) {
        $this->auth0->checkJWT($request->getHeaders());
        $email = $this->auth0->getEmail();
        $sth = $this->db->prepare("SELECT * FROM builds WHERE email=:email");
        $sth->bindParam('email',$email);
        $result = $sth->execute();
        
        $retval = array();
        if ($result && $sth->rowCount() > 0) {
            # initialize empty builds
            foreach (array('colossus','interceptor','ranger','storm') as $class) {
                $retval[$class] = array();
                foreach (array(0,1,2) as $slot) {
                    $retval[$class][$slot] = array(
                        'class' => $class,
                        'slot' => $slot,
                        'name' => sprintf('loadout %d', $slot),
                        'weap' => array(array('id' => 0, 'i'=> array())),
                        'gear' => array(),
                        'comp' => array(),
                        'supp' => array(),
                        'sigils' => array(),
                        'debuffs' => array('acid' => false, 'beacon' => false)
                    );
                }
            }

            $builds = $sth->fetchAll(PDO::FETCH_ASSOC);
            foreach ($builds as $build) {
                $retval[$build['class']][$build['slot']] = json_decode($build['build']);
            }
        }
        echo json_encode($retval);
    });

    $app->post('/api/builds', function($request, $respons) {
        $this->auth0->checkJWT($request->getHeaders());
        $email = $this->auth0->getEmail();
        $body = $request->getParsedBody();
        $class = $body['class'];
        $slot = $body['slot'];
        $build = json_encode($body['build']);

        $sth = $this->db->prepare("INSERT INTO builds (email, class, slot, build) VALUES (:email, :class, :slot, :build) ON DUPLICATE KEY UPDATE build=:build");
        $sth->bindParam("email", $email);
        $sth->bindParam("class", $class);
        $sth->bindParam("slot", $slot);
        $sth->bindParam("build", $build);
        $result = $sth->execute();
        echo json_encode($result);
    });
};
