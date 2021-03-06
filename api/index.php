<?php

require_once ('../include/dbhandler.php');
require_once ('../include/config.php');
require_once '../include/passhash.php';
require '../libs/Slim/Slim.php';

\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim();

$app->get('/hello/:name', function ($name) {
    echo "Hello, " . $name;
});

$app->get('/', function () {
  $app = \Slim\Slim::getInstance();
    echo "Hallo, ich bin eine api";
});

// User id from db - Global Variable
$userid = NULL;

/**
 * Verifying required params posted or not
 */
function verifyRequiredParams($required_fields) {
    $error = false;
    $error_fields = "";
    $request_params = array();
    $request_params = $_REQUEST;
    // Handling PUT request params
    if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
        $app = \Slim\Slim::getInstance();
        parse_str($app->request()->getBody(), $request_params);
    }
    foreach ($required_fields as $field) {
        if (!isset($request_params[$field]) || strlen(trim($request_params[$field])) <= 0) {
            $error = true;
            $error_fields .= $field . ', ';
        }
    }

    if ($error) {
        // Required field(s) are missing or empty
        // echo error json and stop the app
        $response = array();
        $app = \Slim\Slim::getInstance();
        $response["error"] = true;
        $response["message"] = 'Required field(s) ' . substr($error_fields, 0, -2) . ' is missing or empty';
        echoRespnse(400, $response);
        $app->stop();
    }
}

/**
 * Gültigkeit der Email Adresse prüfen
 */
function validateEmail($email) {
    $app = \Slim\Slim::getInstance();
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response["error"] = true;
        $response["message"] = 'Email-Adresse ist Ungültig!';
        echoRespnse(400, $response);
        $app->stop();
    }
}

/**
 * Echoing json response to client
 * @param String $status_code Http response code
 * @param Int $response Json response
 */
function echoRespnse($status_code, $response) {
    $app = \Slim\Slim::getInstance();
    // Http response code
    $app->status($status_code);

    // setting response content type to json
    $app->contentType('application/json');

    echo json_encode($response);
}

/**
 * Adding Middle Layer to authenticate every request
 * Checking if the request has valid api key in the 'Authorization' header
 */
function authenticate(\Slim\Route $route) {
    // Getting request headers
    $headers = apache_request_headers();
    $response = array();
    $app = \Slim\Slim::getInstance();

    // Verifying Authorization Header
    if (isset($headers['Authorization'])) {
      $db = new DBHandler();

      // get the api key
      $apikey = $headers['Authorization'];

      // validating api key
      if (!$db->isValidApiKey($apikey)) {
        // api key is not present in users table
        $response["error"] = true;
        $response["message"] = "Zugriff verweigert! Falscher API-Key!";
        echoRespnse(401, $response);
        $app->stop();
      } else {
        global $userid;
        // get user primary key id
        $user = $db->getUserId($apikey);
        if ($user != NULL)
          $userid = $user;
      }
    } else {
      // api key is missing in header
      $response["error"] = true;
      $response["message"] = "Zugriff verweigert! API-Key fehlt!";
      echoRespnse(400, $response);
      $app->stop();
    }
}

/* --------------------  'user' Tabelle Methoden ----------------------- */
/**
 * Benutzer Registrierung
 * method - POST
 * @param - name, email, password
 *
 * @api {post} /register Benutzer registrieren
 * @apiDescription
 * DBHandler Funktion:
 * ```createUser($name, $email, $password)```
 * @apiName register
 * @apiGroup User
 *
 * @apiParam {String} name Benutzername
 * @apiParam {String} email Email Adresse
 * @apiParam {String} password Passwort
 *
 * @apiSuccessExample Erfolg (Beispiel):
 * {
 *   error:  false
 *   message: 'Du hast dich erfolgreich registriert!'
 * }
 * @apiSuccessExample Error (Beispiel):
  *     {
  *       error: true
  *       message: 'Ein Fehler ist aufgetreten! Bitte versuche es zu einem späteren Zeitpunkt erneut!'
  *     }
  *
  * @apiSuccessExample CURL Beispiel:
   *       curl -X POST -d "username=max" -d "password=pass" -d "email=mein@mail.de" http://karta.dima23.de/api/index.php/register
 */
$app->post('/register', function() use ($app) {
    // check for required params
    verifyRequiredParams(array('username', 'email', 'password'));
    $response = array();

    // reading post params
    $username = $app->request->post('username');
    $email = $app->request->post('email');
    $password = $app->request->post('password');

    // Email Gültigkeit prüfen
    validateEmail($email);

    $db = new DBHandler();
    $res = $db->createUser($username, $email, $password);

    if ($res == USER_CREATED_SUCCESSFULLY) {
      $response["error"] = false;
      $response["message"] = "Du hast dich erfolgreich registriert!";
      echoRespnse(201, $response);
    } else if ($res == USER_CREATE_FAILED) {
      $response["error"] = true;
      $response["message"] = "Ein Fehler ist aufgetreten! Bitte versuche es zu einem späteren Zeitpunkt erneut!";
      echoRespnse(200, $response);
    } else if ($res == USER_ALREADY_EXISTED) {
      $response["error"] = true;
      $response["message"] = "Benutzer mit diesen Daten existiert bereits!";
      echoRespnse(200, $response);
    }
});

/**
* @api {post} /login Benutzer Login prüfen
* @apiName login
* @apiGroup User
* @apiDescription
* DBHandler Funktion:
* ```checkLogin($username, $password)``` - ```getUser($username)```
*
* @apiParam {String} username Benutzername
* @apiParam {String} password Passwort
*
* @apiSuccessExample Erfolg (Beispiel):
* {
*   error:  false
*   username: 'Max'
*   email:  'max@gmx.de'
*   apikey: 'kjhasdlkjh98zaoszdpa98sadölkj'
* }
* @apiSuccessExample Error (Beispiel):
 *     {
 *       error: true
 *       message: 'Login fehlgeschlagen. Zugangsdaten sind nicht korrekt!'
 *     }
 *
 * @apiSuccessExample CURL Beispiel:
  *       curl -X POST -d "username=max" -d "password=pass" http://karta.dima23.de/api/index.php/login
*/
$app->post('/login', function() use ($app) {
    // check for required params
    verifyRequiredParams(array('username', 'password'));
    // reading post params
    $username = $app->request()->post('username');
    $password = $app->request()->post('password');
    $response = array();

    $db = new DBHandler();
    // check for correct email and password
    if ($db->checkLogin($username, $password)) {
      // get the user by email
      $user = $db->getUser($username);

      if ($user != NULL) {
        $response["error"] = false;
        $response['userid'] = $user['userid'];
        $response['username'] = $user['username'];
        $response['email'] = $user['email'];
        $response['apikey'] = $user['apikey'];
        echoRespnse(200, $response);
      } else {
        // unknown error occurred
        $response['error'] = true;
        $response['message'] = "Ein Fehler ist aufgetreten. Versuche es später noch mal.";
        echoRespnse(201, $response);
      }
    } else {
      // user credentials are wrong
      $response['error'] = true;
      $response['message'] = 'Login fehlgeschlagen. Zugangsdaten sind nicht korrekt!';
      echoRespnse(201, $response);
    }
});

/**
* @api {post} /resetpw Benutzerpasswort zurücksetzen
* @apiName resetpw
* @apiGroup User
* @apiDescription
* DBHandler Funktion:
* ```resetPassword($userid, $password)``` - ```getUserByEmail($email)```
*
* @apiParam {String} email Email-Adresse
*
* @apiSuccessExample Erfolg (Beispiel):
* {
*   error:  false
*   message: 'Passwort wurde erfolgreich geändert! Sie erhalten eine E-Mail!'
* }
* @apiSuccessExample Error (Beispiel):
 *     {
 *       error: true
 *       message: 'E-Mail Adresse wurde nicht gefunden!'
 *     }
 *
 * @apiSuccessExample CURL Beispiel:
  *       curl -X POST -d "username=max" -d "password=pass" http://karta.dima23.de/api/index.php/login
*/
$app->post('/resetpw', function() use ($app) {
    // check for required params
    verifyRequiredParams(array('email'));
    // reading post params
    $email = $app->request()->post('email');
    $response = array();

    $db = new DBHandler();
    // check for correct email and password
    $user = $db->getUserByEmail($email);
    $password = $db->randomPassword();
    $result = $db->resetPassword($user["userid"], $password);

      if ($result != NULL) {
        $response["error"] = false;
        $response['message'] = "Passwort wurde erfolgreich geändert! Sie erhalten eine E-Mail!";
        echoRespnse(200, $response);
      } else {
        $response['error'] = true;
        $response['message'] = "Passwort wurde erfolgreich geändert! Sie erhalten eine E-Mail!";
        echoRespnse(201, $response);
      }
});

/**
* Gebe Benutzerdaten zurück
* method GET
* params - userid
* @api {get} /user Benutzerdaten ausgeben
* @apiName getUser
* @apiGroup User
* @apiDescription
* DBHandler Funktion:
* ```getUserById($userid)```
*
* @apiHeader {String} Authorization API-Key des Benutzers
*
* @apiSuccessExample Erfolg (Beispiel):
* {
*   error:  false
*   username: 'Max'
*   email:  'max@gmx.de'
*   apikey: 'kjhasdlkjh98zaoszdpa98sadölkj'
* }
* @apiSuccessExample Error (Beispiel):
 *     {
 *       error: true
 *       message: 'Benutzer wurde nicht gefunden!'
 *     }
 *
 * @apiSuccessExample CURL Beispiel:
  *       curl -H 'Authorization: kjhasdkj23hkj2h3kj2h3' -X GET http://karta.dima23.de/api/index.php/user
*/
$app->get('/user', 'authenticate', function () {

  global $userid;

  $db = new DBHandler();

  $user = $db->getUserById($userid);
  $response = array();

  if ($user != NULL) {
    $response['error'] = false;
    $response['username'] = $user['username'];
    $response['email'] = $user['email'];
    $response['apikey'] = $user['apikey'];
    echoRespnse(200, $response);
  } else {
    // user credentials are wrong
    $response['error'] = true;
    $response['message'] = 'Benutzer wurde nicht gefunden!';
    echoRespnse(201, $response);
  }

});

/**
* @todo Benutzerdaten ändern
* @api {put} /user Benutzerdaten ändern
* @apiName updateUser
* @apiGroup User
* @apiDescription
* DBHandler Funktion:
* ```updateUser($email, $password)```
*
* @apiHeader {String} Authorization API-Key des Benutzers
*
* @apiParam {String} mail Email Adresse
* @apiParam {String} password Passwort
*
* @apiSuccessExample Erfolg (Beispiel):
* {
*   error:  false
*   message: 'Benutzerdaten wurden geändert.'
* }
* @apiSuccessExample Error (Beispiel):
 *     {
 *       error: true
 *       message: 'Fehler! Benutzerdaten konnten nicht geändert werden.'
 *     }
 *
 * @apiSuccessExample CURL Beispiel:
  *       curl -H 'Authorization: kjhasdkj23hkj2h3kj2h3' -X PUT -d "email=neue@mail.de" -d "password=ganzgeheim" http://karta.dima23.de/api/index.php/user
*/
$app->put('/user', 'authenticate', function () use ($app) {

  global $userid;

  $db = new DBHandler();

  $response = array();

  // check for required params
  verifyRequiredParams(array('email', 'password'));
  // reading post params
  $email = $app->request->put('email');
  $password = $app->request->put('password');

  // Email Gültigkeit prüfen
  validateEmail($email);

  $result = $db->updateUser($userid, $email, $password);


  if ($result){
    $user = $db->getUserById($userid);

    if ($user != NULL) {
      $response['error'] = false;
      $response['username'] = $user['username'];
      $response['email'] = $user['email'];
      $response['apikey'] = $user['apikey'];
      echoRespnse(200, $response);
    } else {
      // user credentials are wrong
      $response['error'] = true;
      $response['message'] = 'Benutzer wurde nicht gefunden!';
      echoRespnse(201, $response);
    }
  }else{
    // User update failed
    $response['error'] = true;
    $response['message'] = 'Benutzer konnte nicht geändert werden!';
    echoRespnse(201, $response);
  }

});



/* --------------------  'cardset' Tabelle Methoden ----------------------- */
/**
* @api {get} /cardset/:cardsetid Kartensatz ausgeben
* @apiName getCardset
* @apiGroup Cardset
* @apiDescription
* DBHandler Funktion:
* ```getCardset($cardsetid)```
*
* @apiHeader {String} Authorization API-Key des Benutzers
*
* @apiSuccessExample Erfolg (Beispiel):
* {
*   error:  false
*   cardsetname: 'Englisch 1'
* }
* @apiSuccessExample Error (Beispiel):
 *     {
 *       error: true
 *       message: 'Fehler! Kartensatz wurde nicht gefunden!'
 *     }
 *
 * @apiSuccessExample CURL Beispiel:
  *       curl  -H "Authorization: ce0783ccae32b2eddf9d49a6c8592dfb" -X GET http://karta.dima23.de/api/index.php/cardset/12
*/
$app->get('/cardset/:cardsetid', 'authenticate', function ($cardsetid) {

  global $userid;

  $db = new DBHandler();
  $response = array();

  $cardsetname = $db->getCardset($cardsetid);

  if ($cardsetname != NULL) {
    $response['error'] = false;
    $response['cardsetname'] = $cardsetname;
    echoRespnse(200, $response);
  } else {
    // user credentials are wrong
    $response['error'] = true;
    $response['message'] = 'Kartensatz wurde nicht gefunden!';
    echoRespnse(201, $response);
  }

});

/**
* @api {get} /cardset Alle Kartensätze ausgeben
* @apiName getAllCardset
* @apiGroup Cardset
* @apiDescription
* DBHandler Funktion:
* ```getAllCardsets($userid)```
*
* @apiHeader {String} Authorization API-Key des Benutzers
*
* @apiSuccessExample Erfolg (Beispiel):
* {
*   error:  false
*   cardsets: [{"setid":1,"name":"OOP"},{"setid":2,"name":"KLR"},{"setid":33,"name":"DB"}]
* }
* @apiSuccessExample Error (Beispiel):
 *     {
 *       error: true
 *       message: 'Benutzer wurde nicht gefunden!'
 *     }
 *
 * @apiSuccessExample CURL Beispiel:
  *       curl  -H "Authorization: ce0783ccae32b2eddf9d49a6c8592dfb" -X GET http://karta.dima23.de/api/index.php/cardset
*/
$app->get('/cardset', 'authenticate', function () {

  global $userid;

  $db = new DBHandler();
  $response = array();

  $cardsets = $db->getAllUserCardsetsWithCards($userid);

  if ($cardsets != NULL) {
    $response['error'] = false;
    $response['cardsets'] = $cardsets;
  } else {
    // user credentials are wrong
    $response['error'] = true;
    $response['message'] = 'Benutzer wurde nicht gefunden!';
  }

  echoRespnse(201, $response);
});

/* --------------------  'cards' Tabelle Methoden ----------------------- */
/**
* @todo CHECK: Ob benötigt wird
* @api {delete} /card/:cardid Karte löschen
* @apiName deleteCard
* @apiGroup Card
* @apiDescription
* DBHandler Funktion:
* ```deleteCard($cardid)```
*
* @apiHeader {String} Authorization API-Key des Benutzers
* @apiSuccessExample Erfolg (Beispiel):
* {
*   error:  x
*   message: 'x'
* }
* @apiSuccessExample Error (Beispiel):
 *     {
 *       error: x
 *       message: 'x'
 *     }
 *
 * @apiSuccessExample CURL Beispiel:
  *       curl  -H "Authorization: ce0783ccae32b2eddf9d49a6c8592dfb" -X DELETE " http://karta.dima23.de/api/index.php/card/132
*/
$app->delete('/card/:cardid', 'authenticate', function($cardid) use ($app) {
    // check for required params
    // verifyRequiredParams(array('cardsetname'));
    //
    // $cardsetname = $app->request->post('cardsetname');
    // $response = array();
    //
    // global $userid;
    // $db = new DBHandler();
    // // echo "USERID: ".$userid;
    // // creating new task
    // $cardsetid = $db->createCardset($userid, $cardsetname);
    //
    // if ($cardsetid != NULL) {
    //   $response["error"] = false;
    //   $response["message"] = "Cardset created successfully";
    //   $response["cardsetid"] = $cardsetid;
    // } else {
    //   $response["error"] = true;
    //   $response["message"] = "Fehler! Kartensatz konnte nicht erstellt werden.";
    // }
    // echoRespnse(201, $response);
});

/**
* @todo Prüfen ob Karte dem User gehört bzw. er Zugriff darauf hat
* @api {get} /card/:cardid Karte ausgeben
* @apiName getCard
* @apiGroup Card
* @apiDescription
* DBHandler Funktion:
* ```getCard($cardid)```
*
* @apiHeader {String} Authorization API-Key des Benutzers
* @apiSuccessExample Erfolg (Beispiel):
* {
*   error:  false
*   "type":0,"question":"Frage2","answer":"Antwort2","cardsetid":33
* }
* @apiSuccessExample Error (Beispiel):
 *     {
 *       error: true
 *       message: 'Karte wurde nicht gefunden!'
 *     }
 *
 * @apiSuccessExample CURL Beispiel:
  *       curl  -H "Authorization: ce0783ccae32b2eddf9d49a6c8592dfb" -X GET http://karta.dima23.de/api/index.php/card/132
*/
$app->get('/card/:cardid', 'authenticate', function($cardid) {
  global $userid;

  $db = new DBHandler();
  $response = array();

  $card = $db->getCard($cardid);

  if ($card != NULL) {
    $response['error'] = false;
    $response['type'] = $card['type'];
    $response['question'] = $card['question'];
    $response['answer'] = $card['answer'];
    $response['cardsetid'] = $card['cardsets_setid'];
    echoRespnse(200, $response);
  } else {
    // user credentials are wrong
    $response['error'] = true;
    $response['message'] = 'Karte wurde nicht gefunden!';
    echoRespnse(201, $response);
  }

});

/**
* @api {get} /card Alle Karten ausgeben
* @apiName getAllCards
* @apiGroup Card
* @apiDescription
* DBHandler Funktion:
* ```getAllCards($cardsetid)```
*
* @apiHeader {String} Authorization API-Key des Benutzers
* @apiParam {Integer} cardsetid KartensatzID
* @apiSuccessExample Erfolg (Beispiel):
* {
*   error:  false
*   cards: [{"cardid":18,"type":0,"question":"Frage1","answer":"Antwort1"},{"cardid":19,"type":0,"question":"Frage2","answer":"Ant2"}]
* }
* @apiSuccessExample Error (Beispiel):
 *     {
 *       error: true
 *       message: 'Fehler! Kartensatz konnte nicht gefunden werden.'
 *     }
 *
 * @apiSuccessExample CURL Beispiel:
  *       curl  -H "Authorization: ce0783ccae32b2eddf9d49a6c8592dfb" -X GET -d "cardsetid=12" http://karta.dima23.de/api/index.php/card
*/
$app->get('/card', 'authenticate', function() use ($app) {
    //check for required params
    //verifyRequiredParams(array('cardsetid'));

    $cardsetid = $app->request->get('cardsetid');
    $response = array();

    global $userid;
    $db = new DBHandler();

    $cards = $db->getAllCardsWithBox($userid, $cardsetid);

    if ($cards != NULL) {
      $response["error"] = false;
      $response['cards'] = $cards;
      echoRespnse(200, $response);
    } else {
      $response["error"] = true;
      $response["message"] = "Fehler! Kartensatz konnte nicht gefunden werden.";
      echoRespnse(201, $response);
    }
});

/* --------------------  'stats' Tabelle Methoden ----------------------- */
/**
* @api {post} /stats/:cardid Statistik für Karte erstellen
* @apiName setStats
* @apiGroup Stats
* @apiDescription
* DBHandler Funktion:
* ```setStats($userid, $cardid, $known)```
*
* @apiHeader {String} Authorization API-Key des Benutzers
* @apiParam {Boolean} known gewusst Ja/Nein
* @apiSuccessExample Erfolg (Beispiel):
* {
*   error:  false
*   message: 'Statistik gesetzt.'
* }
* @apiSuccessExample Error (Beispiel):
 *     {
 *       error: true
 *       message: 'Fehler! Statistik konnte nicht gesetzt werden.'
 *     }
 *
 * @apiSuccessExample CURL Beispiel:
  *       curl  -H "Authorization: ce0783ccae32b2eddf9d49a6c8592dfb" -X POST -d "known=1" http://karta.dima23.de/api/index.php/stats/12
*/
$app->post('/stats/:cardid', 'authenticate', function($cardid) use ($app) {
    //check for required params
    verifyRequiredParams(array('known'));

    $known = $app->request->post('known');
    $response = array();

    global $userid;
    $db = new DBHandler();
    // echo "USERID: ".$userid;
    // creating new task
    $result = $db->setStats($userid, $cardid, $known);

    if ($result) {
      $response["error"] = false;
      $response["message"] = "Statistik gesetzt.";
      echoRespnse(200, $response);
    } else {
      $response["error"] = true;
      $response["message"] = "Fehler! Statistik konnte nicht gesetzt werden.";
      echoRespnse(201, $response);
    }
});

/**
* @api {get} /stats/:cardsetid Statistik für Kartensatz zurückgeben
* @apiName getStats
* @apiGroup Stats
* @apiDescription
* DBHandler Funktion:
* ```getStats($userid, $cardsetid)```
*
* @apiHeader {String} Authorization API-Key des Benutzers
* @apiSuccessExample Erfolg (Beispiel):
* {
*   error:  false
*   stats: 0.75 (Noch zu Lernender Prozentsatz)
* }
* @apiSuccessExample Error (Beispiel):
 *     {
 *       error: true
 *       message: 'Fehler! Kartensatz konnte nicht gefunden werden.'
 *     }
 *
 * @apiSuccessExample CURL Beispiel:
  *       curl  -H "Authorization: ce0783ccae32b2eddf9d49a6c8592dfb" -X GET http://karta.dima23.de/api/index.php/stats/12
*/
$app->get('/stats/:cardsetid', 'authenticate', function($cardsetid) use ($app) {

  $response = array();

  global $userid;
  $db = new DBHandler();

  $stats = $db->getStats($userid, $cardsetid);

  if ($stats != NULL) {
    $response["error"] = false;
    $response['stats'] = $stats;
    echoRespnse(200, $response);
  } else {
    $response["error"] = true;
    $response["message"] = "Fehler! Kartensatz konnte nicht gefunden werden.";
    echoRespnse(201, $response);
  }
});


/* --------------------  'friends' Tabelle Methoden ----------------------- */
/**
* @api {get} /friend Freunde zurückgeben
* @apiName getFriends
* @apiGroup Friends
* @apiDescription
* DBHandler Funktion:
* ```getFriends($userid)```
*
* @apiHeader {String} Authorization API-Key des Benutzers
* @apiParam {Boolean} known gewusst Ja/Nein
* @apiSuccessExample Erfolg (Beispiel):
* {
*   error:  false
*   friends: [{"userid":1,"username":"freund1"},{"userid":2,"username":"freund2"}]
* }
* @apiSuccessExample Error (Beispiel):
 *     {
 *       error: true
 *       message: 'Fehler! Benutzer konnte nicht gefunden werden.'
 *     }
 *
 * @apiSuccessExample CURL Beispiel:
  *       curl  -H "Authorization: ce0783ccae32b2eddf9d49a6c8592dfb" -X GET http://karta.dima23.de/api/index.php/friend
*/
$app->get('/friend', 'authenticate', function() {
  $response = array();

  global $userid;
  $db = new DBHandler();

  $friends = $db->getFriends($userid);

  if ($friends != NULL) {
    $response["error"] = false;
    $response['friends'] = $friends;
    echoRespnse(200, $response);
  } else {
    $response["error"] = true;
    $response["message"] = "Fehler! Benutzer konnte nicht gefunden werden.";
    echoRespnse(201, $response);
  }
});

/* --------------------  'user_has_cardsets' Tabelle Methoden ----------------------- */
/**
* @api {get} /permission Berechtigungen zurückgeben
* @apiName getPermission
* @apiGroup Share
* @apiDescription
* DBHandler Funktion:
* ```assignedUser($cardsetid)```
*
* @apiHeader {String} Authorization API-Key des Benutzers
* @apiParam {Integer} cardsetid Kartensatz ID
* @apiSuccessExample Erfolg (Beispiel):
* {
*   error:  false
*   permission: [{"userid":1,"username":"freund1", "persmission":"1"},{"userid":2,"username":"freund2","persmission":"0"}]
* }
* @apiSuccessExample Error (Beispiel):
 *     {
 *       error: true
 *       message: 'Fehler! Kartensatz konnte nicht gefunden werden.'
 *     }
 *
 * @apiSuccessExample CURL Beispiel:
  *       curl  -H "Authorization: ce0783ccae32b2eddf9d49a6c8592dfb" -X GET -d "cardsetid=1" http://karta.dima23.de/api/index.php/permission
*/
$app->get('/permission', 'authenticate', function() use ($app){
  $response = array();

  $cardsetid = $app->request->get('cardsetid');

  global $userid;
  $db = new DBHandler();

  $permissions = $db->assignedUser($cardsetid);

  if ($permissions != NULL) {
    $response["error"] = false;
    $response['permissions'] = $permissions;
    echoRespnse(200, $response);
  } else {
    $response["error"] = true;
    $response["message"] = "Fehler! Kartensatz konnte nicht gefunden werden.";
    echoRespnse(201, $response);
  }
});

/*
* @api {post} /permission:cardsetid Berechtigungen hinzufügen
* @apiName addPermission
* @apiGroup Share
* @apiDescription
* DBHandler Funktion:
* ```assignUserCardset($userid, $cardsetid, $permission)```
*
* @apiHeader {String} Authorization API-Key des Benutzers
* @apiParam {String} username Benutzername
* @apiParam {Integer} persmission Berechtigung 1=Besitzer; 0=Teilnehmer
* @apiSuccessExample Erfolg (Beispiel):
* {
*   error:  false
*   message: "Benutzer wurde erfolgreich hinzugefügt"
* }
* @apiSuccessExample Error (Beispiel):
 *     {
 *       error: true
 *       message: 'Fehler! Benutzer/Kartensatz konnte nicht gefunden werden.'
 *     }
 *
 * @apiSuccessExample CURL Beispiel:
  *       curl  -H "Authorization: ce0783ccae32b2eddf9d49a6c8592dfb" -X POST -d "username=vogt" -d "persmission=1" http://karta.dima23.de/api/index.php/permission/12
*/
$app->post('/permission/:cardsetid', 'authenticate', function($cardsetid) use ($app){
  $response = array();
  verifyRequiredParams(array('username'));
  verifyRequiredParams(array('permission'));

  $username = $app->request->post('username');
  $permission = $app->request->post('permission');

  $db = new DBHandler();

  $userid = $db->getUser($username);
  $result = $db->assignUserCardset($userid['userid'], $cardsetid, $permission);

  if ($result != NULL) {
    $response["error"] = false;
    $response['message'] = "Benutzer wurde erfolgreich hinzugefügt";
    echoRespnse(200, $response);
  } else {
    $response["error"] = true;
    $response["message"] = "Fehler! Benutzer/Kartensatz konnte nicht gefunden werden.".$result;
    echoRespnse(201, $response);
  }
});

/*
* @api {delete} /permission:cardsetid Berechtigungen löschen
* @apiName deletePermission
* @apiGroup Share
* @apiDescription
* DBHandler Funktion:
* ```deleteAssignedUser($userid, $cardsetid)```
*
* @apiHeader {String} Authorization API-Key des Benutzers
* @apiParam {String} username Benutzername
* @apiSuccessExample Erfolg (Beispiel):
* {
*   error:  false
*   message: "Benutzer wurde erfolgreich entfernt"
* }
* @apiSuccessExample Error (Beispiel):
 *     {
 *       error: true
 *       message: 'Fehler! Benutzer/Kartensatz konnte nicht gefunden werden.'
 *     }
 *
 * @apiSuccessExample CURL Beispiel:
  *       curl  -H "Authorization: ce0783ccae32b2eddf9d49a6c8592dfb" -X DELETE -d "username=vogt" http://karta.dima23.de/api/index.php/permission/12
*/
$app->delete('/permission/:cardsetid', 'authenticate', function($cardsetid) use ($app){
  $response = array();

  $username = $app->request->get('username');

  $db = new DBHandler();

  $userid = $db->getUser($username);
  $result = $db->deleteAssignedUser($userid['userid'], $cardsetid);

  if ($result != NULL) {
    $response["error"] = false;
    $response['message'] = "Benutzer wurde erfolgreich entfernt!";
    echoRespnse(200, $response);
  } else {
    $response["error"] = true;
    $response["message"] = "Fehler! Benutzer/Kartensatz konnte nicht gefunden werden.";
    echoRespnse(201, $response);
  }
});

$app->run();
