<?php

class MDDatabase
{
    
    private $_log = array();
    
    private $_connection;
    
    /*
     * CONVENIENCE METHODS
     */
    /**
     * Perform a select count(*) query based on the given criteria.
     * @return int Count result.
     * @param string $table Database table to select count on.
     * @param array $criteria[optional] Array of criteria that will be passed to "WHERE" statement
     * @param array $params[optiona] Any other params like 'orderBy', 'orderDir', 'start', 'limit', 'groupBy' etc.
     */
    public function count($table, $criteria = array(), $params = array()) {
        $criteria = $this->parseCriteria($criteria);

        // parse any optional params
        $groupBy        = isset($params['groupBy'])     ? $this->makeSafe($params['groupBy'])   : false;
        $orderBy        = isset($params['orderBy'])     ? $this->makeSafe($params['orderBy'])   : false;
        $orderDir       = ((isset($params['orderDir'])) AND in_array($params['orderDir'], array(MDOrderAscending, MDOrderDescending))) ? $params['orderDir'] : MDOrderAscending;
        $start          = isset($params['start'])       ? intval($params['start'])              : 0;
        $limit          = isset($params['limit'])       ? intval($params['limit'])              : false;
        
        $query = "
            SELECT
                COUNT(*) AS `count`
                ". ($groupBy        ? ", `". $groupBy ."`"                                  : null) ."
            FROM `". $this->makeSafe($table) ."`
            ". ($criteria           ? "WHERE ". $criteria   : null) ."
            ". ($groupBy            ? "GROUP BY `". $groupBy ."`"                           : null) ."
            ". ($orderBy            ? "ORDER BY `". $orderBy ."` ". $orderDir               : null) ."
            ". ($limit              ? "LIMIT ". $start .", ". $limit                        : null) ."
        ";
        $result = $this->query($query, true);
        return ($groupBy) ? $result : intval($result[0]['count']);
    }
    
    /**
     * Perform an insert of the given values to the given table.
     * @return int Insert ID.
     * @param string $table Database table to insert to.
     * @param array $values Values to insert.
     * @param array $params[optional] Optional parameters like whether or not to use IGNORE or ON DUPLICATE KEY UPDATE ...
     */
    public function insert($table, $values, $params = array()) {
        // parse params
        $ignore             = isset($params['ignore'])              ? $params['ignore']             : false;
        $updateDuplicate    = isset($params['updateDuplicate'])     ? $params['updateDuplicate']    : false;
        
        $updateDuplicateSql = null;
        if ($updateDuplicate) {
            $updateDuplicateSql = "
            ON DUPLICATE KEY UPDATE 
                ". $this->parseSetValues((is_array($updateDuplicate)) ? $updateDuplicate : $values) ."
            ";
        }
        
        // parse fields
        $fields = $this->makeSafe(array_keys($values));
        
        $sqlQuery = "
            INSERT ". ($ignore ? "IGNORE" : null) ."
            INTO `". $table ."` (`". implode('`,`', $fields) ."`) 
            VALUES
                (". $this->parseInsertValues($values) .")
            ". $updateDuplicateSql ."
        ";
        
        $this->query($sqlQuery);
        
        $insertId = $this->getInsertId();
        return $insertId;
    }
    
    /**
     * Update a table with the given values based on the given criteria.
     * @param string $table Name of the table to update.
     * @param array $values Array of values to be updated.
     * @param array $criteria[optional] Criteria on which to perform the update.
     */
    public function update($table, $values, $criteria = array()) {
        $criteria = $this->parseCriteria($criteria);
        
        $sqlQuery = "
            UPDATE `". $this->makeSafe($table) ."`
            SET
                ". $this->parseSetValues($values) ."
            ". ($criteria       ? "WHERE ". $criteria       : null) ."
        ";
        
        $this->query($sqlQuery);
    }
    
    /**
     * Delete data from table based on the given criteria.
     * @param string $table Name of the table to delete from.
     * @param array $criteria[optional] Criteria on which to perform the deletion.
     * @param bool $onlyFlag[optional] If (bool)true passed, then instead of deleting the rows it will set their 'deleted' fields to 1.
     */
    public function delete($table, $criteria = array(), $onlyFlag = false) {
        // prevent from deleting the whole table if no criteria set ;)
        if (empty($criteria)) return;
        
        if ($onlyFlag) {
            $this->update($table, array('deleted' => 1), $criteria);
            return;
        }
        
        $criteria = $this->parseCriteria($criteria);
        
        $sqlQuery = "
            DELETE FROM `". $this->makeSafe($table) ."`
            ". ($criteria       ? "WHERE ". $criteria       : null) ."
        ";
        $this->query($sqlQuery);
    }
    
    /*
     * LOGGING AND ERROR HANDLING
     */
    /**
     * Logs the query into internal log.
     * @param string $sqlQuery Query string.
     * @param double $time How long did it take?
     * @param int $affectedRows[optional] How many rows affected?
     */
    public function logQuery($sqlQuery, $time, $affectedRows = 0) {
        // get the trace and try to figure out where the query was called!
        // we will determine it based on file path! So the first function that has been called from outside MDKit directory is our suspected function! :)
        $file = '';
        $function = '';
        $mdkitDir = MDKit::getMDKit()->getKitDir();
        $mdkitDirLength = strlen($mdkitDir);
        $trace = MDDebug::getPrettyTrace(debug_backtrace());
        foreach($trace as $item) {
            if (substr($item['file'], 0, $mdkitDirLength) !== $mdkitDir) {
                $file = $item['file'];
                $function = $item['function'];
                break;
            }
        }
        
        $this->_log[] = array(
            'query' => $sqlQuery,
            'type' => self::determineQueryType($sqlQuery),
            'time' => $time,
            'affected' => $affectedRows,
            'file' => $file,
            'function' => $function
        );
    }
    
    /*
     * HELPERS
     */
    /**
     * Returns ID of the last insert.
     * @return int
     */
    public function getInsertId() {
        return mysql_insert_id($this->_connection);
    }
    
}
