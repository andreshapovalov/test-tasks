<?php

namespace ASH\XMLProcessor\DB\Model\Repositories;

use PDO;

class UserRepository
{
    /**
     * Holds connection handle
     * @var PDO
     */
    private $db;

    /**
     * The table name, related to the repository
     * @var string
     */
    private $tableName = 'users';

    /**
     * UserRepository constructor.
     * @param PDO $db PDO instance
     */
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Saves bunch of users at a time
     * @param array $users
     * @return bool
     */
    public function saveMany(array $users)
    {
        $sqlRows = [];
        $toBind = [];
        $columnNames = array_keys($users[0]);

        foreach ($users as $index => $user) {
            $params = [];

            foreach ($user as $columnName => $columnValue) {
                $param = ':' . $columnName . $index;
                $params[] = $param;
                $toBind[$param] = $columnValue;
            }

            $sqlRows[] = '(' . implode(', ', $params) . ')';
        }

        $sql = "INSERT INTO `{$this->tableName}` (" . implode(", ", $columnNames) . ") VALUES " . implode(', ', $sqlRows);

        $statement = $this->db->prepare($sql);

        //bind values
        foreach ($toBind as $param => $val) {
            $statement->bindValue($param, $val);
        }

        return $statement->execute();
    }

    /**
     * Searches for users by criteria
     * @param array $criteria The search criteria
     * @return \Generator Yields found records
     */
    public function findByCriteria($criteria)
    {
        if ($criteria['operator'] === 'btw') {
            $statement = $this->db->prepare("SELECT * FROM `{$this->tableName}` WHERE `{$criteria['field']}` BETWEEN :min AND :max");
            $statement->bindParam(':min', $criteria['arguments'][0]);
            $statement->bindParam(':max', $criteria['arguments'][1]);
        } else {
            $statement = $this->db->prepare("SELECT * FROM `{$this->tableName}` WHERE `{$criteria['field']}`{$criteria['operator']}:value");
            $statement->bindParam(':value', $criteria['arguments'][0]);
        }

        $statement->execute();

        while ($row = $statement->fetch()) {
            yield $row;
        }
    }

    /**
     * Removes all table data
     * @return void
     */
    public function truncate()
    {
        $this->db->prepare("TRUNCATE TABLE `{$this->tableName}`")->execute();
    }
}