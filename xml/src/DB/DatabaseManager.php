<?php

namespace ASH\XMLProcessor\DB;

use PDO;

class DatabaseManager
{
    private $host;
    private $db;
    private $port;
    private $user;
    private $password;
    private $connection;

    /**
     * @param $host
     * @param $db
     * @param $port
     * @param $user
     * @param $password
     * @param $connection
     */
    public function __construct($host, $db, $user, $password, $port = 3306)
    {
        $this->host = $host;
        $this->db = $db;
        $this->port = $port;
        $this->user = $user;
        $this->password = $password;
        $this->connection = null;
    }

    /**
     * Initiates connection to db
     * @return null|PDO
     */
    public function connect()
    {
        $this->connection = new PDO("mysql:host={$this->host};dbname={$this->db};", $this->user, $this->password, [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        return $this->connection;
    }

    /**
     * Returns active connection
     * @return null|PDO
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Creates db and tables
     */
    public function initDB()
    {
        // create database
        $dbCreationConnection = new PDO('mysql:host=' . $this->host, $this->user, $this->password);
        $dbCreationConnection->exec("CREATE DATABASE IF NOT EXISTS `{$this->db}`;
        GRANT ALL ON `{$this->db}`.* TO '{$this->user}'@'$this->host';
        FLUSH PRIVILEGES;");

        //create users table
        $usersTableSQL = "CREATE TABLE IF NOT EXISTS `users` (
                            `id`    INT(11)      NOT NULL AUTO_INCREMENT,
                            `name`  VARCHAR(255) NOT NULL,
                            `email` VARCHAR(50)  NOT NULL,
                            `age`   TINYINT               DEFAULT 0,
                            PRIMARY KEY (`id`)
                          ) ENGINE = InnoDB DEFAULT CHARSET = utf8;";

        $tableCreationConnection = $this->connect();
        $tableCreationConnection->exec($usersTableSQL);
    }
}