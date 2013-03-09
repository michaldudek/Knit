<?php
namespace Knit\Store;

use Knit\Store\StoreInterface;

class MySQLStore implements StoreInterface
{

    public function __construct($hostname, $username, $password, $database) {

    }

    public function get($table, array $criteria = array(), $params = array()) {
        return array();
    }

    public function count($table, array $criteria = array(), $params = array()) {
        return 0;
    }

    public function add($table, array $properties) {
        return 0;
    }

    public function update($table, array $criteria, array $values) {

    }

    public function delete($table, array $criteria) {

    }

}