<?php
require '../lib/vendor/autoload.php';

require_once '../include/DbHandler.php';
require_once '../include/PassHash.php';

$user_id = NULL;
$app = new Slim\Slim();

function verifyRequiredParams($required_fields) {
    $error = false;
    $error_fields = "";
    $request_params = $_REQUEST;
    if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
        $app = Slim\Slim::getInstance();
        parse_str($app->request->getBody(), $request_params);
    }

    foreach ($required_fields as $fields) {
        if (!isset($request_params[$fields]) || strlen(trim($request_params[$fields])) <= 0) {
            $error = true;
            $error_fields .= $fields . ', ';
        }
    }

    if ($error) {
        $response = array();
        $app = \Slim\Slim::getInstance();
        $response['error'] = true;
        $response['message'] = 'Required params ' . substr($error_fields, 0, -2) . 'is missing.';
        echoResponse(400, $response);
        $app->stop();
    }
}

function authentice(\Slim\Route $route) {
    $headers = apache_request_headers();
    $response = array();
    $app = \Slim\Slim::getInstance();

    if (isset($headers['Authorization'])) {
        $db = new DbHandler();
        $api_key = $headers['Authorization'];
        if (!$db->isValidApiKey($api_key)) {
            $response['error'] = true;
            $response['message'] = "Access Denied. Invalid Api key";
            echoResponse(401, $response);
            $app->stop();
        }
        else {
            global $user_id;
            $user = $db->getUserId($api_key);
            if ($user != null) {
                $user_id = $user['id'];
            }
        }
    }
    else {
        $response['error'] = true;
        $response['message'] = 'Api key is missing';
        echoResponse(400, $response);
        $app->stop();
    }
}

function validateEmail($email) {
    $app = \Slim\Slim::getInstance();
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response = array();
        $response['error'] = true;
        $response['messge'] = "Email address is not valid";
        echoResponse(400, $response);
        $app->stop();
    }
}

function echoResponse($status_code, $response) {
    $app = Slim\Slim::getInstance();
    $app->status($status_code);
    $app->contentType("application/json");
    echo json_encode($response);
}

$app->post('/register', function() use ($app) {
    verifyRequiredParams(array('name', 'email', 'password'));
    $response = array();

    $name = $app->request->post('name');
    $email = $app->request->post('email');
    $password = $app->request->post('password');

    validateEmail($email);
    $db = new DbHandler();
    $res = $db->createUser($name, $email, $password);
    if ($res == USER_CREATED_SUCCESSFULLY) {
        $response['error'] = false;
        $response['message'] = "Create user successfuly";
        echoResponse(201, $response);
    }
    else if ($res = USER_CREATE_FAILED) {
        $response['error'] = true;
        $response['message'] = "Oops! An error occurred while registereing";
        echoResponse(200, $response);
    }
    else {
        $response['error'] = true;
        $response['message'] = "Sorry, this email already existed";
        echoResponse(200, $response);
    }
});

$app->post('/login', function () use ($app) {
    verifyRequiredParams(array('email', 'password'));
    $email = $app->request->post('email');
    $password = $app->request->post('password');
    $response = array();

    $db = new DbHandler();
    if ($db->checkLogin($email, $password)) {
        $user = $db->getUserByEmail($email);
        if ($user != null) {
            $response['error'] = false;
            $response['name'] = $user['name'];
            $response['email'] = $user['email'];
            $response['apiKey'] = $user['api_key'];
            $response['createAt'] = $user['created_at'];
        }
        else {
            $response['error'] = true;
            $response['message'] = "An error occurred. Please try again";
        }
    }
    else {
        $response['error'] = true;
        $response['message'] = "Login failed. Incorrect credentials";
    }
    echoResponse(200, $response);
});

$app->post('/tasks', 'authentice', function () use ($app) {
    verifyRequiredParams(array('task'));
    $response = array();
    $task = $app->request->post('task');

    global $user_id;
    $db = new DbHandler();

    $task_id = $db->createTask($user_id, $task);
    if ($task_id != null) {
        $response['error'] = false;
        $response["message"] = "Task created successfully";
        $response["task_id"] = $task_id;
    }
    else {
        $response["error"] = true;
        $response["message"] = "Failed to create task. Please try again";
    }
    echoResponse(201, $response);
});

$app->get('/tasks', 'authentice', function () use ($app) {
    global $user_id;
    $response = array();
    $db = new DbHandler();
    $result = $db->getAllUserTasks($user_id);
    $response['error'] = false;
    $response['tasks'] = array();

    while ($task = $result->fetch_assoc()) {
        $tmp = array();
        $tmp["id"] = $task["id"];
        $tmp["task"] = $task["task"];
        $tmp["status"] = $task["status"];
        $tmp["createdAt"] = $task["created_at"];
        array_push($response["tasks"], $tmp);
    }
    echoResponse(201, $response);
});

$app->get('/tasks/:id', 'authentice', function ($task_id) {
    global $user_id;
    $response = array();
    $db = new DbHandler();
    $result = $db->getTask($task_id, $user_id);
    if ($result != null) {
        $response['error'] = false;
        $response['id'] = $result['id'];
        $response["task"] = $result["task"];
        $response["status"] = $result["status"];
        $response["createdAt"] = $result["created_at"];
        echoResponse(200, $response);
    }
    else {
        $response["error"] = true;
        $response["message"] = "The requested resource doesn't exists";
        echoResponse(404, $response);
    }
});

$app->run();