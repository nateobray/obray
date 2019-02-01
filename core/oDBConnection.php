<?php

class oDBConnection extends \PDO
{
    /**
     * oDBConnection constructor.
     * @param string $host
     * @param string $db
     * @param string $username
     * @param string $password
     */
    public function __construct(string $host, string $db, string $username, string $password)
    {
        $dsn = "mysql:host={$host};dbname={$db};charset=utf8";
        parent::__construct($dsn, $username, $password, [
            \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'
        ]);
        $this->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

}
