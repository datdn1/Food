<?php

class DbConnect {
    private $conn;
    function __construct() { }

    function connect() {
        include_once 'Config.php';
        $this->conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
        return $this->conn;
    }
}