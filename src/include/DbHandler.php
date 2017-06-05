<?php

class DbHandler {
    private $conn;

    function __construct() {
        include_once 'DbConnect.php';
        $db = new DbConnect();
        $this->conn = $db->connect();
    }

    public function createUser($name, $email, $password) {
        require_once 'PassHash.php';
        if (!$this->isUserExists($email)) {
            $password_hash = PassHash::hash($password);
            $api_key = $this->generateApiKey();
            $stmt = $this->conn->prepare("insert into users (name, email, password_hash, api_key, status) values (?, ?, ?, ?, 1)");
            $stmt->bind_param("ssss", $name, $email, $password_hash, $api_key);
            $result = $stmt->execute();
            if ($result) {
                return USER_CREATED_SUCCESSFULLY;
            }
            else {
                return USER_CREATE_FAILED;
            }
        }
        else {
            return USER_ALREADY_EXISTED;
        }
    }

    public function checkLogin($email, $password) {
        $stmt = $this->conn->prepare("select password_hash from users where email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->bind_result($password_hash);
        $stmt->store_result();
        $number_rows = $stmt->num_rows;
        if ($number_rows > 0) {
            $stmt->fetch();
            $stmt->close();
            if (PassHash::check_password($password_hash, $password)) {
                return true;
            }
            else {
                return false;
            }
        }
        else {
            $stmt->close();
            return false;
        }
    }

    public function getUserByEmail($email) {
        $stmt = $this->conn->prepare("SELECT name, email, api_key, status, created_at FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        if ($stmt->execute()) {
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $user;
        }
        else {
            return null;
        }
    }

    public function getApiKeyById($id) {
        $stmt = $this->conn->prepare("select api_key from users where id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $api_key = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $api_key;
        }
        else {
            return null;
        }
    }

    public function getUserId($api_key) {
        $stmt = $this->conn->prepare("select id from users where api_key = ?");
        $stmt->bind_param("s", $api_key);
        if ($stmt->execute()) {
            $id = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $id;
        }
        else {
            return null;
        }
    }

    public function isValidApiKey($api_key) {
        $stmt = $this->conn->prepare("select id from users where api_key = ?");
        $stmt->bind_param("s", $api_key);
        $stmt->execute();
        $stmt->store_result();
        $number_rows = $stmt->num_rows;
        $stmt->close();
        return $number_rows;
    }

    private function isUserExists($email) {
        $stmt = $this->conn->prepare("select id from users where email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        $number_rows = $stmt->num_rows;
        $stmt->close();
        return $number_rows > 0;
    }

    private function generateApiKey() {
        return md5(uniqid(rand(), true));
    }

    // ------------------ tasks ------------------- //
    public function createTask($user_id, $task) {
        $stmt = $this->conn->prepare("insert into tasks(task) values(?)");
        $stmt->bind_param("s", $task);
        $result = $stmt->execute();
        $stmt->close();
        if ($result) {
            $new_task_id = $this->conn->insert_id;
            if ($this->createUserTask($user_id, $new_task_id)) {
                return $new_task_id;
            }
            else {
                return null;
            }
        }
        else {
            return null;
        }
    }

    public function getTask($task_id, $user_id) {
        $stmt = $this->conn->prepare("SELECT t.id, t.task, t.status, t.created_at from tasks t, user_tasks ut WHERE t.id = ? AND ut.task_id = t.id AND ut.user_id = ?");
        $stmt->bind_param("ii", $task_id, $user_id);
        if ($stmt->execute()) {
            $task = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $task;
        }
        else {
            return null;
        }
    }

    public function getAllUserTasks($user_id) {
        $stmt = $this->conn->prepare("SELECT t.* FROM tasks t, user_tasks ut WHERE t.id = ut.task_id AND ut.user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $tasks = $stmt->get_result();
        $stmt->close();
        return $tasks;
    }

    public function updateTask($user_id, $task_id, $task, $status) {
        $stmt = $this->conn->prepare("update tasks t, user_tasks ut set t.task = ?, t.status = ? 
                where t.id = ? AND  t.id = ut.task_id AND ut.user_id = ?");
        $stmt->bind_param("siii", $task, $status, $task_id, $user_id);
        $stmt->execute();
        $number_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $number_affected_rows > 0;
    }

    public function deleteTask($user_id, $task_id) {
        $stmt = $this->conn->prepare("delete t from tasks t, user_tasks ut 
                  where t.id = ? and t.id = ut.task_id AND ut.user_id = ?");
        $stmt->bind_param("ii", $task_id, $user_id);
        $stmt->execute();
        $number_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $number_affected_rows > 0;
    }


    // ------------------ user task -------------- //
    public function createUserTask($user_id, $task_id) {
        $stmt = $this->conn->prepare("insert into user_tasks(user_id, task_id) values(?, ?)");
        $stmt->bind_param("ii", $user_id, $task_id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
}