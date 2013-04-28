<?php

class MDDatabase
{
    
    private $_log = array();
    
    private $_connection;
    
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
    
}
