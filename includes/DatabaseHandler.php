<?php
/**
 * MysqliDb Class
 *
 * @category  Database Access
 * @package   MysqliDb
 * @author    Jeffery Way <jeffrey@jeffrey-way.com>
 * @author    Josh Campbell <jcampbell@ajillion.com>
 * @author    Alexander V. Butenko <a.butenka@gmail.com>
 * @copyright Copyright (c) 2010-2017
 * @license   http://opensource.org/licenses/gpl-3.0.html GNU Public License
 * @link      http://github.com/joshcam/PHP-MySQLi-Database-Class
 * @version   2.9.3
 */

namespace realSamy\tools;

use Exception;
use mysqli;
use mysqli_result;
use mysqli_stmt;
use ReflectionClass;
use RuntimeException;
use stdClass;

class DatabaseHandler
{
    /**
     * Table prefix
     *
     * @var string
     */
    public static $prefix = '';
    /**
     * Static instance of self
     *
     * @var self
     */
    protected static $_instance;
    /**
     * Variable which holds an amount of returned rows during get/getOne/select queries
     *
     * @var string
     */
    public $count = 0;
    /**
     * Variable which holds an amount of returned rows during get/getOne/select queries with withTotalCount()
     *
     * @var string
     */
    public $totalCount = 0;
    /**
     * Return type: 'array' to return results as array, 'object' as object
     * 'json' as json string
     *
     * @var string
     */
    public $returnType = 'array';
    public    $trace = [];
    /**
     * Per page limit for pagination
     *
     * @var int
     */
    public $pageLimit = 20;
    /**
     * Variable that holds total pages count of last paginate() query
     *
     * @var int
     */
    public $totalPages = 0;
    /**
     * @var string the name of a default (main) mysqli connection
     */
    public $defConnectionName = 'default';
    public    $autoReconnect      = true;
    /**
     * MySQLi instances
     *
     * @var mysqli[]
     */
    protected $_mysqli = [];
    /**
     * The SQL query to be prepared and executed
     *
     * @var string
     */
    protected $_query;
    /**
     * The previously executed SQL query
     *
     * @var string
     */
    protected $_lastQuery;
    /**
     * The SQL query options required after SELECT, INSERT, UPDATE or DELETE
     *
     * @var array
     */
    protected $_queryOptions = [];
        /**
     * An array that holds where joins
     *
     * @var array
     */
    protected $_join = []; // Create the empty 0 index
    /**
     * An array that holds where conditions
     *
     * @var array
     */
    protected $_where = [];
    /**
     * An array that holds where join ands
     *
     * @var array
     */
    protected $_joinAnd = [];
    /**
     * An array that holds having conditions
     *
     * @var array
     */
    protected $_having = [];
    /**
     * Dynamic type list for order by condition value
     *
     * @var array
     */
    protected $_orderBy = [];
    /**
     * Dynamic type list for group by condition value
     *
     * @var array
     */
    protected $_groupBy = [];
    /**
     * Dynamic type list for temporary locking tables.
     *
     * @var array
     */
    protected $_tableLocks = [];
    /**
     * Variable which holds the current table lock method.
     *
     * @var string
     */
    protected $_tableLockMethod = "READ";
/**
     * Dynamic array that holds a combination of where condition/table data value types and parameter references
     *
     * @var array
     */
    protected $_bindParams = [''];
    /**
     * Variable which holds last statement error
     *
     * @var string
     */
    protected $_stmtError;
    /**
     * Variable which holds last statement error code
     *
     * @var int
     */
    protected $_stmtErrno;
    /**
     * Is Subquery object
     *
     * @var bool
     */
    protected $isSubQuery = false;
    /**
     * Name of the auto increment column
     *
     * @var int
     */
    protected $_lastInsertId = null;
    /**
     * Column names for update when using onDuplicate method
     *
     * @var array
     */
    protected $_updateColumns = null;
    /**
     * Should join() results be nested by table
     *
     * @var bool
     */
    protected $_nestJoin = false;
    /**
     * FOR UPDATE flag
     *
     * @var bool
     */
    protected $_forUpdate = false;
    /**
     * LOCK IN SHARE MODE flag
     *
     * @var bool
     */
    protected $_lockInShareMode = false;
    /**
     * Key field for Map()'ed result array
     *
     * @var string
     */
    protected $_mapKey = null;
    /**
     * Variables for query execution tracing
     */
    protected $traceStartQ;
    protected $traceEnabled;
    protected $traceStripPrefix;
    /**
     * @var array connections settings [profile_name=>[same_as_contruct_args]]
     */
    protected $connectionsSettings = [];
    protected $autoReconnectCount = 0;
    /**
     * @var bool Operations in transaction indicator
     */
    protected $_transaction_in_progress = false;
    /**
     * Table name (with prefix, if used)
     *
     * @var string
     */
    private $_tableName = '';

    /**
     * @param string|array $host
     * @param string       $username
     * @param string       $password
     * @param string       $db
     * @param int          $port
     * @param string       $charset
     * @param string       $socket
     * @param string|null  $prefix
     */
    final public function __construct($host = null, string $username = null, string $password = null, string $db = null, int $port = null, string $charset = 'utf8', string $socket = null, string $prefix = null)
    {
        $isSubQuery = false;
        // if params were passed as array
        if (is_array($host)) {
            foreach ($host as $key => $val) {
                $$key = $val;
            }
        }
        $this->addConnection('default', [
            'host'     => $host,
            'username' => $username,
            'password' => $password,
            'db'       => $db,
            'port'     => $port,
            'socket'   => $socket,
            'charset'  => $charset,
        ]);
        if ($isSubQuery) {
            $this->isSubQuery = true;
            return;
        }
        if (isset($prefix)) {
            $this->setPrefix($prefix);
        }
        static::$_instance = $this;
    }

    /**
     * Create & store at _mysqli new mysqli instance
     *
     * @param string $name
     * @param array  $params
     * @return self
     */
    final public function addConnection(string $name, array $params): self
    {
        $this->connectionsSettings[$name] = [];
        foreach (['host', 'username', 'password', 'db', 'port', 'socket', 'charset'] as $k) {
            $prm = $params[$k] ?? null;
            if ($k === 'host') {
                if (is_object($prm)) {
                    $this->_mysqli[$name] = $prm;
                }
                if (!is_string($prm)) {
                    $prm = null;
                }
            }
            $this->connectionsSettings[$name][$k] = $prm;
        }
        return $this;
    }

    /**
     * Method to set a prefix
     *
     * @param string $prefix Contains a table prefix
     * @return self
     */
    final public function setPrefix(string $prefix = ''): self
    {
        static::$prefix = $prefix;
        return $this;
    }

    /**
     * A method of returning the static instance to allow access to the
     * instantiated object from within another class.
     * Inheriting this class would require reloading connection info.
     *
     * @return self Returns the current instance.
     * @uses $db = MySqliDb::getInstance();
     */
    public static function getInstance(): self
    {
        return static::$_instance;
    }

    /**
     * Method creates new mysqlidb object for a subquery generation
     *
     * @param string $subQueryAlias
     * @return self
     */
    public static function subQuery(string $subQueryAlias = ""): self 
    {
        return new self(['host' => $subQueryAlias, 'isSubQuery' => true]);
    }

    /**
     * @throws Exception
     */
    final public function disconnectAll(): void
    {
        foreach (array_keys($this->_mysqli) as $k) {
            $this->disconnect($k);
        }
    }

    /**
     * A method to disconnect from the database
     *
     * @params string $connection connection name to disconnect
     * @param string $connection
     * @return void
     */
    final public function disconnect(string $connection = 'default'): void
    {
        if (!isset($this->_mysqli[$connection])) {
            return;
        }
        $this->_mysqli[$connection]->close();
        unset($this->_mysqli[$connection]);
    }

    /**
     * Set the connection name to use in the next query
     *
     * @param string $name
     * @return self
     * @throws Exception
     */
    final public function connection(string $name): self
    {
        if (!isset($this->connectionsSettings[$name])) {
            throw new Exception('Connection ' . $name . ' was not added.');
        }
        $this->defConnectionName = $name;
        return $this;
    }

    /**
     * Helper function to create dbObject with JSON return type
     *
     * @return self
     */
    final public function jsonBuilder(): self
    {
        $this->returnType = 'json';
        return $this;
    }

    /**
     * Helper function to create dbObject with object return type.
     *
     * @return self
     */
    final public function objectBuilder(): self
    {
        $this->returnType = 'object';
        return $this;
    }

    /**
     * Helper function to execute raw SQL query and return only 1 row of results.
     * Note that function do not add 'limit 1' to the query by itself
     * Same idea as getOne()
     *
     * @param string $query      User-provided query to execute.
     * @param array  $bindParams Variables array to bind to the SQL statement.
     * @return array|null Contains the returned row from the query.
     * @throws Exception
     */
    final public function rawQueryOne(string $query, array $bindParams = null): ?array
    {
        $res = $this->rawQuery($query, $bindParams);
        if (is_array($res) && isset($res[0])) {
            return $res[0];
        }
        return null;
    }

    /**
     * Execute raw SQL query.
     *
     * @param string $query      User-provided query to execute.
     * @param array  $bindParams Variables array to bind to the SQL statement.
     * @return array Contains the returned rows from the query.
     * @throws Exception
     */
    final public function rawQuery(string $query, array $bindParams = null): array
    {
        $params = ['']; // Create the empty 0 index
        $this->_query = $query;
        $stmt = $this->_prepareQuery();
        if (is_array($bindParams) === true) {
            foreach ($bindParams as $prop => $val) {
                $params[0] .= $this->_determineType($val);
                $params[] = $bindParams[$prop];
            }
            call_user_func_array([$stmt, 'bind_param'], $this->refValues($params));
        }
        $stmt->execute();
        $this->count = $stmt->affected_rows;
        $this->_stmtError = $stmt->error;
        $this->_stmtErrno = $stmt->errno;
        $this->_lastQuery = $this->replacePlaceHolders($this->_query, $params);
        $res = $this->_dynamicBindResults($stmt);
        $this->reset();
        return $res;
    }

    /**
     * Method attempts to prepare the SQL query
     * and throws an error if there was a problem.
     *
     * @return mysqli_stmt
     * @throws Exception
     */
    protected function _prepareQuery()
    {
        $stmt = $this->mysqli()->prepare($this->_query);
        if ($stmt !== false) {
            if ($this->traceEnabled) {
                $this->traceStartQ = microtime(true);
            }
            return $stmt;
        }
        if ($this->mysqli()->errno === 2006 && $this->autoReconnect === true && $this->autoReconnectCount === 0) {
            $this->connect($this->defConnectionName);
            $this->autoReconnectCount++;
            return $this->_prepareQuery();
        }
        $error = $this->mysqli()->error;
        $query = $this->_query;
        $errno = $this->mysqli()->errno;
        $this->reset();
        throw new Exception(sprintf('%s query: %s', $error, $query), $errno);
    }

    /**
     * A method to get mysqli object or create it in case needed
     *
     * @return mysqli
     * @throws Exception
     */
    final public function mysqli(): mysqli
    {
        if (!isset($this->_mysqli[$this->defConnectionName])) {
            $this->connect($this->defConnectionName);
        }
        return $this->_mysqli[$this->defConnectionName];
    }

    /**
     * A method to connect to the database
     *
     * @param null|string $connectionName
     * @return void
     * @throws Exception
     */
    final public function connect(string $connectionName = 'default'): void
    {
        if (!isset($this->connectionsSettings[$connectionName])) {
            throw new RuntimeException('Connection profile not set');
        }
        $pro = $this->connectionsSettings[$connectionName];
        $params = array_values($pro);
        $charset = array_pop($params);
        if ($this->isSubQuery) {
            return;
        }
        if (empty($pro['host']) && empty($pro['socket'])) {
            throw new RuntimeException('MySQL host or socket is not set');
        }
        $mysqlic = new ReflectionClass('mysqli');
        $mysqli = $mysqlic->newInstanceArgs($params);
        if ($mysqli->connect_error) {
            throw new RuntimeException('Connect Error ' . $mysqli->connect_errno . ': ' . $mysqli->connect_error, $mysqli->connect_errno);
        }
        if (!empty($charset)) {
            $mysqli->set_charset($charset);
        }
        $this->_mysqli[$connectionName] = $mysqli;
    }

    /**
     * Reset states after an execution
     *
     * @return self Returns the current instance.
     */
    final public function reset(): self
    {
        if ($this->traceEnabled) {
            $this->trace[] = [$this->_lastQuery, (microtime(true) - $this->traceStartQ), $this->_traceGetCaller()];
        }
        $this->_where = [];
        $this->_having = [];
        $this->_join = [];
        $this->_joinAnd = [];
        $this->_orderBy = [];
        $this->_groupBy = [];
        $this->_bindParams = ['']; // Create the empty 0 index
        $this->_query = null;
        $this->_queryOptions = [];
        $this->returnType = 'array';
        $this->_nestJoin = false;
        $this->_forUpdate = false;
        $this->_lockInShareMode = false;
        $this->_tableName = '';
        $this->_lastInsertId = null;
        $this->_updateColumns = null;
        $this->_mapKey = null;
        if (!$this->_transaction_in_progress) {
            $this->defConnectionName = 'default';
        }
        $this->autoReconnectCount = 0;
        return $this;
    }

    /**
     * Get where and what function was called for query stored in MysqliDB->trace
     *
     * @return string with information
     */
    private function _traceGetCaller()
    {
        $dd = debug_backtrace();
        $caller = next($dd);
        while (isset($caller) && $caller["file"] == __FILE__) {
            $caller = next($dd);
        }
        return __CLASS__ . "->" . $caller["function"] . "() >>  file \"" .
            str_replace($this->traceStripPrefix, '', $caller["file"]) . "\" line #" . $caller["line"] . " ";
    }

    /**
     * This method is needed for prepared statements. They require
     * the data type of the field to be bound with "i" s", etc.
     * This function takes the input, determines what type it is,
     * and then updates the param_type.
     *
     * @param mixed $item Input to determine the type.
     * @return string The joined parameter types.
     */
    protected function _determineType($item)
    {
        switch (gettype($item)) {
            case 'NULL':
            case 'string':
                return 's';
                break;
            case 'boolean':
            case 'integer':
                return 'i';
                break;
            case 'blob':
                return 'b';
                break;
            case 'double':
                return 'd';
                break;
        }
        return '';
    }

    /**
     * Referenced data array is required by mysqli since PHP 5.3+
     *
     * @param array $arr
     * @return array
     */
    protected function refValues(array &$arr)
    {
        //Reference in the function arguments are required for HHVM to work
        //https://github.com/facebook/hhvm/issues/5155
        //Referenced data array is required by mysqli since PHP 5.3+
        if (strnatcmp(phpversion(), '5.3') >= 0) {
            $refs = [];
            foreach ($arr as $key => $value) {
                $refs[$key] = &$arr[$key];
            }
            return $refs;
        }
        return $arr;
    }

    /**
     * Function to replace ? with variables from bind variable
     *
     * @param string $str
     * @param array  $vals
     * @return string
     */
    protected function replacePlaceHolders($str, $vals)
    {
        $i = 1;
        $newStr = "";
        if (empty($vals)) {
            return $str;
        }
        while ($pos = strpos($str, "?")) {
            $val = $vals[$i++];
            if (is_object($val)) {
                $val = '[object]';
            }
            if ($val === null) {
                $val = 'NULL';
            }
            $newStr .= substr($str, 0, $pos) . "'" . $val . "'";
            $str = substr($str, $pos + 1);
        }
        $newStr .= $str;
        return $newStr;
    }

    /**
     * This helper method takes care of prepared statements' "bind_result method
     * , when the number of variables to pass is unknown.
     *
     * @param mysqli_stmt $stmt Equal to the prepared statement object.
     * @return array|string The results of the SQL fetch.
     * @throws Exception
     */
    protected function _dynamicBindResults(mysqli_stmt $stmt)
    {
        $parameters = [];
        $results = [];
        /**
         * @see http://php.net/manual/en/mysqli-result.fetch-fields.php
         */
        $mysqlLongType = 252;
        $shouldStoreResult = false;
        $meta = $stmt->result_metadata();
        // if $meta is false yet sqlstate is true, there's no sql error but the query is
        // most likely an update/insert/delete which doesn't produce any results
        if (!$meta && $stmt->sqlstate) {
            return [];
        }
        $row = [];
        while ($field = $meta->fetch_field()) {
            if ($field->type == $mysqlLongType) {
                $shouldStoreResult = true;
            }
            if ($this->_nestJoin && $field->table != $this->_tableName) {
                $field->table = substr($field->table, strlen(static::$prefix));
                $row[$field->table][$field->name] = null;
                $parameters[] = &$row[$field->table][$field->name];
            }
            else {
                $row[$field->name] = null;
                $parameters[] = &$row[$field->name];
            }
        }
        // avoid out of memory bug in php 5.2 and 5.3. Mysqli allocates lot of memory for long*
        // and blob* types. So to avoid out of memory issues store_result is used
        // https://github.com/joshcam/PHP-MySQLi-Database-Class/pull/119
        if ($shouldStoreResult) {
            $stmt->store_result();
        }
        call_user_func_array([$stmt, 'bind_result'], $parameters);
        $this->totalCount = 0;
        $this->count = 0;
        while ($stmt->fetch()) {
            if ($this->returnType == 'object') {
                $result = new stdClass ();
                foreach ($row as $key => $val) {
                    if (is_array($val)) {
                        $result->$key = new stdClass ();
                        foreach ($val as $k => $v) {
                            $result->$key->$k = $v;
                        }
                    }
                    else {
                        $result->$key = $val;
                    }
                }
            }
            else {
                $result = [];
                foreach ($row as $key => $val) {
                    if (is_array($val)) {
                        foreach ($val as $k => $v) {
                            $result[$key][$k] = $v;
                        }
                    }
                    else {
                        $result[$key] = $val;
                    }
                }
            }
            $this->count++;
            if ($this->_mapKey) {
                $results[$row[$this->_mapKey]] = count($row) > 2 ? $result : end($result);
            }
            else {
                array_push($results, $result);
            }
        }
        if ($shouldStoreResult) {
            $stmt->free_result();
        }
        $stmt->close();
        // stored procedures sometimes can return more then 1 resultset
        if ($this->mysqli()->more_results()) {
            $this->mysqli()->next_result();
        }
        if (in_array('SQL_CALC_FOUND_ROWS', $this->_queryOptions)) {
            $stmt = $this->mysqli()->query('SELECT FOUND_ROWS()');
            $totalCount = $stmt->fetch_row();
            $this->totalCount = $totalCount[0];
        }
        if ($this->returnType == 'json') {
            return json_encode($results);
        }
        return $results;
    }

    /**
     * Helper function to execute raw SQL query and return only 1 column of results.
     * If 'limit 1' will be found, then string will be returned instead of array
     * Same idea as getValue()
     *
     * @param string $query      User-provided query to execute.
     * @param array  $bindParams Variables array to bind to the SQL statement.
     * @return mixed Contains the returned rows from the query.
     * @throws Exception
     */
    final public function rawQueryValue(string $query, array $bindParams = null)
    {
        $res = $this->rawQuery($query, $bindParams);
        if (!$res) {
            return null;
        }
        $limit = preg_match('/limit\s+1;?$/i', $query);
        $key = key($res[0]);
        if (isset($res[0][$key]) && $limit == true) {
            return $res[0][$key];
        }
        $newRes = [];
        for ($i = 0; $i < $this->count; $i++) {
            $newRes[] = $res[$i][$key];
        }
        return $newRes;
    }

    /**
     * A method to perform select query
     *
     * @param string    $query   Contains a user-provided select query.
     * @param int|array $numRows Array to define SQL limit in format Array ($offset, $count)
     * @return array Contains the returned rows from the query.
     * @throws Exception
     */
    final public function query(string $query, $numRows = null): array
    {
        $this->_query = $query;
        $stmt = $this->_buildQuery($numRows);
        $stmt->execute();
        $this->_stmtError = $stmt->error;
        $this->_stmtErrno = $stmt->errno;
        $res = $this->_dynamicBindResults($stmt);
        $this->reset();
        return $res;
    }

    /**
     * Abstraction method that will compile the WHERE statement,
     * any passed update data, and the desired rows.
     * It then builds the SQL query.
     *
     * @param int|array $numRows     Array to define SQL limit in format Array ($offset, $count)
     *                               or only $count
     * @param array     $tableData   Should contain an array of data for updating the database.
     * @return mysqli_stmt|bool Returns the $stmt object.
     * @throws Exception
     */
    protected function _buildQuery($numRows = null, $tableData = null)
    {
        // $this->_buildJoinOld();
        $this->_buildJoin();
        $this->_buildInsertQuery($tableData);
        $this->_buildCondition('WHERE', $this->_where);
        $this->_buildGroupBy();
        $this->_buildCondition('HAVING', $this->_having);
        $this->_buildOrderBy();
        $this->_buildLimit($numRows);
        $this->_buildOnDuplicate($tableData);
        if ($this->_forUpdate) {
            $this->_query .= ' FOR UPDATE';
        }
        if ($this->_lockInShareMode) {
            $this->_query .= ' LOCK IN SHARE MODE';
        }
        $this->_lastQuery = $this->replacePlaceHolders($this->_query, $this->_bindParams);
        if ($this->isSubQuery) {
            return;
        }
        // Prepare query
        $stmt = $this->_prepareQuery();
        // Bind parameters to statement if any
        if (count($this->_bindParams) > 1) {
            call_user_func_array([$stmt, 'bind_param'], $this->refValues($this->_bindParams));
        }
        return $stmt;
    }

    /**
     * Abstraction method that will build an JOIN part of the query
     */
    protected function _buildJoin()
    {
        if (empty ($this->_join)) {
            return;
        }
        foreach ($this->_join as $data) {
            [$joinType, $joinTable, $joinCondition] = $data;
            if (is_object($joinTable)) {
                $joinStr = $this->_buildPair("", $joinTable);
            }
            else {
                $joinStr = $joinTable;
            }
            $this->_query .= " " . $joinType . " JOIN " . $joinStr .
                (false !== stripos($joinCondition, 'using') ? " " : " on ")
                . $joinCondition;
            // Add join and query
            if (!empty($this->_joinAnd) && isset($this->_joinAnd[$joinStr])) {
                foreach ($this->_joinAnd[$joinStr] as $join_and_cond) {
                    [$concat, $varName, $operator, $val] = $join_and_cond;
                    $this->_query .= " " . $concat . " " . $varName;
                    $this->conditionToSql($operator, $val);
                }
            }
        }
    }

    /**
     * Helper function to add variables into bind parameters array and will return
     * its SQL part of the query according to operator in ' $operator ?' or
     * ' $operator ($subquery) ' formats
     *
     * @param string $operator
     * @param mixed  $value Variable with values
     * @return string
     */
    protected function _buildPair($operator, $value)
    {
        if (!is_object($value)) {
            $this->_bindParam($value);
            return ' ' . $operator . ' ? ';
        }
        $subQuery = $value->getSubQuery();
        $this->_bindParams($subQuery['params']);
        return " " . $operator . " (" . $subQuery['query'] . ") " . $subQuery['alias'];
    }

    /**
     * Helper function to add variables into bind parameters array
     *
     * @param string Variable value
     */
    protected function _bindParam($value)
    {
        $this->_bindParams[0] .= $this->_determineType($value);
        array_push($this->_bindParams, $value);
    }

    /**
     * Helper function to add variables into bind parameters array in bulk
     *
     * @param array $values Variable with values
     */
    protected function _bindParams($values)
    {
        foreach ($values as $value) {
            $this->_bindParam($value);
        }
    }

    /**
     * Convert a condition and value into the sql string
     *
     * @param String       $operator The where constraint operator
     * @param String|array $val      The where constraint value
     */
    private function conditionToSql($operator, $val)
    {
        switch (strtolower($operator)) {
            case 'not in':
            case 'in':
                $comparison = ' ' . $operator . ' (';
                if (is_object($val)) {
                    $comparison .= $this->_buildPair("", $val);
                }
                else {
                    foreach ($val as $v) {
                        $comparison .= ' ?,';
                        $this->_bindParam($v);
                    }
                }
                $this->_query .= rtrim($comparison, ',') . ' ) ';
                break;
            case 'not between':
            case 'between':
                $this->_query .= " $operator ? AND ? ";
                $this->_bindParams($val);
                break;
            case 'not exists':
            case 'exists':
                $this->_query .= $operator . $this->_buildPair("", $val);
                break;
            default:
                if (is_array($val)) {
                    $this->_bindParams($val);
                }
                elseif ($val === null) {
                    $this->_query .= $operator . " NULL";
                }
                elseif ($val != 'DBNULL' || $val == '0') {
                    $this->_query .= $this->_buildPair($operator, $val);
                }
        }
    }

    /**
     * Abstraction method that will build an INSERT or UPDATE part of the query
     *
     * @param array $tableData
     * @throws Exception
     */
    protected function _buildInsertQuery($tableData)
    {
        if (!is_array($tableData)) {
            return;
        }
        $isInsert = preg_match('/^[INSERT|REPLACE]/', $this->_query);
        $dataColumns = array_keys($tableData);
        if ($isInsert) {
            if (isset ($dataColumns[0])) {
                $this->_query .= ' (`' . implode('`, `', $dataColumns) . '`) ';
            }
            $this->_query .= ' VALUES (';
        }
        else {
            $this->_query .= " SET ";
        }
        $this->_buildDataPairs($tableData, $dataColumns, $isInsert);
        if ($isInsert) {
            $this->_query .= ')';
        }
    }

    /**
     * Insert/Update query helper
     *
     * @param array $tableData
     * @param array $tableColumns
     * @param bool  $isInsert INSERT operation flag
     * @throws Exception
     */
    final public function _buildDataPairs($tableData, $tableColumns, $isInsert)
    {
        foreach ($tableColumns as $column) {
            $value = $tableData[$column];
            if (!$isInsert) {
                if (strpos($column, '.') === false) {
                    $this->_query .= "`" . $column . "` = ";
                }
                else {
                    $this->_query .= str_replace('.', '.`', $column) . "` = ";
                }
            }
            // Subquery value
            if ($value instanceof self) {
                $this->_query .= $this->_buildPair("", $value) . ", ";
                continue;
            }
            // Simple value
            if (!is_array($value)) {
                $this->_bindParam($value);
                $this->_query .= '?, ';
                continue;
            }
            // Function value
            $key = key($value);
            $val = $value[$key];
            switch ($key) {
                case '[I]':
                    $this->_query .= $column . $val . ", ";
                    break;
                case '[F]':
                    $this->_query .= $val[0] . ", ";
                    if (!empty($val[1])) {
                        $this->_bindParams($val[1]);
                    }
                    break;
                case '[N]':
                    if ($val == null) {
                        $this->_query .= "!" . $column . ", ";
                    }
                    else {
                        $this->_query .= "!" . $val . ", ";
                    }
                    break;
                default:
                    throw new RuntimeException("Wrong operation");
            }
        }
        $this->_query = rtrim($this->_query, ', ');
    }

    /**
     * Abstraction method that will build the part of the WHERE conditions
     *
     * @param string $operator
     * @param array  $conditions
     */
    protected function _buildCondition($operator, &$conditions)
    {
        if (empty($conditions)) {
            return;
        }
        //Prepare the where portion of the query
        $this->_query .= ' ' . $operator;
        foreach ($conditions as $cond) {
            [$concat, $varName, $operator, $val] = $cond;
            $this->_query .= " " . $concat . " " . $varName;
            switch (strtolower($operator)) {
                case 'not in':
                case 'in':
                    $comparison = ' ' . $operator . ' (';
                    if (is_object($val)) {
                        $comparison .= $this->_buildPair("", $val);
                    }
                    else {
                        foreach ($val as $v) {
                            $comparison .= ' ?,';
                            $this->_bindParam($v);
                        }
                    }
                    $this->_query .= rtrim($comparison, ',') . ' ) ';
                    break;
                case 'not between':
                case 'between':
                    $this->_query .= " $operator ? AND ? ";
                    $this->_bindParams($val);
                    break;
                case 'not exists':
                case 'exists':
                    $this->_query .= $operator . $this->_buildPair("", $val);
                    break;
                default:
                    if (is_array($val)) {
                        $this->_bindParams($val);
                    }
                    elseif ($val === null) {
                        $this->_query .= ' ' . $operator . " NULL";
                    }
                    elseif ($val != 'DBNULL' || $val == '0') {
                        $this->_query .= $this->_buildPair($operator, $val);
                    }
            }
        }
    }

    /**
     * Abstraction method that will build the GROUP BY part of the WHERE statement
     *
     * @return void
     */
    protected function _buildGroupBy()
    {
        if (empty($this->_groupBy)) {
            return;
        }
        $this->_query .= " GROUP BY ";
        foreach ($this->_groupBy as $key => $value) {
            $this->_query .= $value . ", ";
        }
        $this->_query = rtrim($this->_query, ', ') . " ";
    }

    /**
     * Abstraction method that will build the LIMIT part of the WHERE statement
     *
     * @return void
     */
    protected function _buildOrderBy()
    {
        if (empty($this->_orderBy)) {
            return;
        }
        $this->_query .= " ORDER BY ";
        foreach ($this->_orderBy as $prop => $value) {
            if (strtolower(str_replace(" ", "", $prop)) === 'rand()') {
                $this->_query .= "rand(), ";
            }
            else {
                $this->_query .= $prop . " " . $value . ", ";
            }
        }
        $this->_query = rtrim($this->_query, ', ') . " ";
    }

    /**
     * Abstraction method that will build the LIMIT part of the WHERE statement
     *
     * @param int|array $numRows     Array to define SQL limit in format Array ($offset, $count)
     *                               or only $count
     * @return void
     */
    protected function _buildLimit($numRows)
    {
        if (!isset($numRows)) {
            return;
        }
        if (is_array($numRows)) {
            $this->_query .= ' LIMIT ' . (int)$numRows[0] . ', ' . (int)$numRows[1];
        }
        else {
            $this->_query .= ' LIMIT ' . (int)$numRows;
        }
    }

    /**
     * Helper function to add variables into the query statement
     *
     * @param array $tableData Variable with values
     * @throws Exception
     */
    protected function _buildOnDuplicate($tableData)
    {
        if (is_array($this->_updateColumns) && !empty($this->_updateColumns)) {
            $this->_query .= " ON DUPLICATE KEY UPDATE ";
            if ($this->_lastInsertId) {
                $this->_query .= $this->_lastInsertId . "=LAST_INSERT_ID (" . $this->_lastInsertId . "), ";
            }
            foreach ($this->_updateColumns as $key => $val) {
                // skip all params without a value
                if (is_numeric($key)) {
                    $this->_updateColumns[$val] = '';
                    unset($this->_updateColumns[$key]);
                }
                else {
                    $tableData[$key] = $val;
                }
            }
            $this->_buildDataPairs($tableData, array_keys($this->_updateColumns), false);
        }
    }

    /**
     * A convenient SELECT COLUMN function to get a single column value from one row
     *
     * @param string $tableName The name of the database table to work with.
     * @param string $column    The desired column
     * @param int    $limit     Limit of rows to select. Use null for unlimited..1 by default
     * @return mixed Contains the value of a returned column / array of values
     * @throws Exception
     */
    final public function getValue(string $tableName, string $column, int $limit = 1)
    {
        $res = $this->ArrayBuilder()->get($tableName, $limit, "{$column} AS retval");
        if (!$res) {
            return null;
        }
        if ($limit === 1) {
            return $res[0]["retval"] ?? null;
        }
        $newRes = [];
        for ($i = 0; $i < $this->count; $i++) {
            $newRes[] = $res[$i]['retval'];
        }
        return $newRes;
    }

    /**
     * A convenient SELECT * function.
     *
     * @param string    $tableName   The name of the database table to work with.
     * @param int|array $numRows     Array to define SQL limit in format Array ($offset, $count)
     *                               or only $count
     * @param string    $columns     Desired columns
     * @return array|self Contains the returned rows from the select query.
     * @throws Exception
     */
    final public function get(string $tableName, $numRows = null, string $columns = '*'): array
    {
        if (empty($columns)) {
            $columns = '*';
        }
        $column = is_array($columns) ? implode(', ', $columns) : $columns;
        if (strpos($tableName, '.') === false) {
            $this->_tableName = static::$prefix . $tableName;
        }
        else {
            $this->_tableName = $tableName;
        }
        $this->_query = 'SELECT ' . implode(' ', $this->_queryOptions) . ' ' .
            $column . " FROM " . $this->_tableName;
        $stmt = $this->_buildQuery($numRows);
        if ($this->isSubQuery) {
            return $this;
        }
        $stmt->execute();
        $this->_stmtError = $stmt->error;
        $this->_stmtErrno = $stmt->errno;
        $res = $this->_dynamicBindResults($stmt);
        $this->reset();
        return $res;
    }

    /**
     * Helper function to create dbObject with array return type
     * Added for consistency as that's default output type
     *
     * @return self
     */
    final public function arrayBuilder(): self
    {
        $this->returnType = 'array';
        return $this;
    }

    /**
     * Insert method to add several rows at once
     *
     * @param string $tableName       The name of the table.
     * @param array  $multiInsertData Two-dimensional Data-array containing information for inserting into the DB.
     * @param array  $dataKeys        Optional Table Key names, if not set in insertDataSet.
     * @return bool|array Boolean indicating the insertion failed (false), else return id-array ([int])
     * @throws Exception
     */
    final public function insertMulti(string $tableName, array $multiInsertData, array $dataKeys = null)
    {
        // only auto-commit our inserts, if no transaction is currently running
        $autoCommit = (isset($this->_transaction_in_progress) ? !$this->_transaction_in_progress : true);
        $ids = [];
        if ($autoCommit) {
            $this->startTransaction();
        }
        foreach ($multiInsertData as $insertData) {
            if ($dataKeys !== null) {
                // apply column-names if given, else assume they're already given in the data
                $insertData = array_combine($dataKeys, $insertData);
            }
            $id = $this->insert($tableName, $insertData);
            if (!$id) {
                if ($autoCommit) {
                    $this->rollback();
                }
                return false;
            }
            $ids[] = $id;
        }
        if ($autoCommit) {
            $this->commit();
        }
        return $ids;
    }

    /**
     * Begin a transaction
     *
     * @throws Exception
     * @uses register_shutdown_function(array($this, "_transaction_shutdown_check"))
     * @uses mysqli->autocommit(false)
     */
    final public function startTransaction()
    {
        $this->mysqli()->autocommit(false);
        $this->_transaction_in_progress = true;
        register_shutdown_function([$this, "_transaction_status_check"]);
    }

    /**
     * Insert method to add new row
     *
     * @param string $tableName  The name of the table.
     * @param array  $insertData Data containing information for inserting into the DB.
     * @return bool Boolean indicating whether the insert query was completed successfully.
     * @throws Exception
     */
    final public function insert(string $tableName, array $insertData): bool
    {
        return $this->_buildInsert($tableName, $insertData, 'INSERT');
    }

    /**
     * Internal function to build and execute INSERT/REPLACE calls
     *
     * @param string $tableName  The name of the table.
     * @param array  $insertData Data containing information for inserting into the DB.
     * @param string $operation  Type of operation (INSERT, REPLACE)
     * @return bool Boolean indicating whether the insert query was completed successfully.
     * @throws Exception
     */
    private function _buildInsert($tableName, $insertData, $operation)
    {
        if ($this->isSubQuery) {
            return null;
        }
        $this->_query = $operation . " " . implode(' ', $this->_queryOptions) . " INTO " . static::$prefix . $tableName;
        $stmt = $this->_buildQuery(null, $insertData);
        $status = $stmt->execute();
        $this->_stmtError = $stmt->error;
        $this->_stmtErrno = $stmt->errno;
        $haveOnDuplicate = !empty ($this->_updateColumns);
        $this->reset();
        $this->count = $stmt->affected_rows;
        if ($stmt->affected_rows < 1) {
            // in case of onDuplicate() usage, if no rows were inserted
            if ($status && $haveOnDuplicate) {
                return true;
            }
            return false;
        }
        if ($stmt->insert_id > 0) {
            return $stmt->insert_id;
        }
        return true;
    }

    /**
     * Transaction rollback function
     *
     * @throws Exception
     * @uses mysqli->autocommit(true);
     * @uses mysqli->rollback();
     */
    final public function rollback()
    {
        $result = $this->mysqli()->rollback();
        $this->_transaction_in_progress = false;
        $this->mysqli()->autocommit(true);
        return $result;
    }

    /**
     * Transaction commit
     *
     * @throws Exception
     * @uses mysqli->autocommit(true);
     * @uses mysqli->commit();
     */
    final public function commit()
    {
        $result = $this->mysqli()->commit();
        $this->_transaction_in_progress = false;
        $this->mysqli()->autocommit(true);
        return $result;
    }

    /**
     * Replace method to add new row
     *
     * @param string $tableName  The name of the table.
     * @param array  $insertData Data containing information for inserting into the DB.
     * @return bool Boolean indicating whether the insert query was completed successfully.
     * @throws Exception
     */
    final public function replace(string $tableName, array $insertData): bool
    {
        return $this->_buildInsert($tableName, $insertData, 'REPLACE');
    }

    /**
     * A convenient function that returns TRUE if exists at least an element that
     * satisfy the where condition specified calling the "where" method before this one.
     *
     * @param string $tableName The name of the database table to work with.
     * @return bool
     * @throws Exception
     */
    final public function has(string $tableName): bool
    {
        $this->getOne($tableName, '1');
        return $this->count >= 1;
    }

    /**
     * A convenient SELECT * function to get one record.
     *
     * @param string $tableName The name of the database table to work with.
     * @param string $columns   Desired columns
     * @return array Contains the returned rows from the select query.
     * @throws Exception
     */
    final public function getOne(string $tableName, string $columns = '*'): array
    {
        $res = $this->get($tableName, 1, $columns);
        if ($res instanceof self) {
            return $res;
        }
        if (is_array($res) && isset($res[0])) {
            return $res[0];
        }
        if ($res) {
            return $res;
        }
        return [];
    }

    /**
     * Update query. Be sure to first call the "where" method.
     *
     * @param string $tableName The name of the database table to work with.
     * @param array  $tableData Array of data to update the desired row.
     * @param int    $numRows   Limit on the number of rows that can be updated.
     * @return bool
     * @throws Exception
     */
    final public function update(string $tableName, array $tableData, int $numRows = null): bool
    {
        if ($this->isSubQuery) {
            return false;
        }
        $this->_query = "UPDATE " . static::$prefix . $tableName;
        $stmt = $this->_buildQuery($numRows, $tableData);
        $status = $stmt->execute();
        $this->reset();
        $this->_stmtError = $stmt->error;
        $this->_stmtErrno = $stmt->errno;
        $this->count = $stmt->affected_rows;
        return $status;
    }

    /**
     * Delete query. Call the "where" method first.
     *
     * @param string    $tableName   The name of the database table to work with.
     * @param int|array $numRows     Array to define SQL limit in format Array ($offset, $count)
     *                               or only $count
     * @return bool Indicates success. 0 or 1.
     * @throws Exception
     */
    final public function delete(string $tableName, $numRows = null): bool
    {
        if ($this->isSubQuery) {
            return false;
        }
        $table = static::$prefix . $tableName;
        if (count($this->_join)) {
            $this->_query = "DELETE " . preg_replace('/.* (.*)/', '$1', $table) . " FROM " . $table;
        }
        else {
            $this->_query = "DELETE FROM " . $table;
        }
        $stmt = $this->_buildQuery($numRows);
        $stmt->execute();
        $this->_stmtError = $stmt->error;
        $this->_stmtErrno = $stmt->errno;
        $this->count = $stmt->affected_rows;
        $this->reset();
        return ($stmt->affected_rows > -1);    //	-1 indicates that the query returned an error
    }

    /**
     * This function store update column's name and column name of the
     * autoincrement column
     *
     * @param array  $updateColumns Variable with values
     * @param string $lastInsertId  Variable value
     * @return self
     */
    final public function onDuplicate(array $updateColumns, string $lastInsertId = null): self
    {
        $this->_lastInsertId = $lastInsertId;
        $this->_updateColumns = $updateColumns;
        return $this;
    }

    /**
     * This method allows you to specify multiple (method chaining optional) OR WHERE statements for SQL queries.
     *
     * @param string $whereProp  The name of the database field.
     * @param mixed  $whereValue The value of the database field.
     * @param string $operator   Comparison operator. Default is =
     * @return self
     * @uses $MySqliDb->orWhere('id', 7)->orWhere('title', 'MyTitle');
     */
    final public function orWhere($whereProp, $whereValue = 'DBNULL', $operator = '=')
    {
        return $this->where($whereProp, $whereValue, $operator, 'OR');
    }

    /**
     * This method allows you to specify multiple (method chaining optional) AND WHERE statements for SQL queries.
     *
     * @param string $whereProp  The name of the database field.
     * @param mixed  $whereValue The value of the database field.
     * @param string $operator   Comparison operator. Default is =
     * @param string $cond       Condition of where statement (OR, AND)
     * @return self
     * @uses $MySqliDb->where('id', 7)->where('title', 'MyTitle');
     */
    final public function where(string $whereProp, string $whereValue = 'DBNULL', string $operator = '=', string $cond = 'AND'): self
    {
        if (count($this->_where) === 0) {
            $cond = '';
        }
        $this->_where[] = [$cond, $whereProp, $operator, $whereValue];
        return $this;
    }

    /**
     * This method allows you to specify multiple (method chaining optional) OR HAVING statements for SQL queries.
     *
     * @param string $havingProp  The name of the database field.
     * @param mixed  $havingValue The value of the database field.
     * @param string $operator    Comparison operator. Default is =
     * @return self
     * @uses $MySqliDb->orHaving('SUM(tags) > 10')
     */
    final public function orHaving($havingProp, $havingValue = null, $operator = null)
    {
        return $this->having($havingProp, $havingValue, $operator, 'OR');
    }

    /**
     * This method allows you to specify multiple (method chaining optional) AND HAVING statements for SQL queries.
     *
     * @param string $havingProp  The name of the database field.
     * @param mixed  $havingValue The value of the database field.
     * @param string $operator    Comparison operator. Default is =
     * @param string $cond
     * @return self
     * @uses $MySqliDb->having('SUM(tags) > 10')
     */
    final public function having($havingProp, $havingValue = 'DBNULL', $operator = '=', $cond = 'AND')
    {
        // forkaround for an old operation api
        if (is_array($havingValue) && ($key = key($havingValue)) != "0") {
            $operator = $key;
            $havingValue = $havingValue[$key];
        }
        if (count($this->_having) == 0) {
            $cond = '';
        }
        $this->_having[] = [$cond, $havingProp, $operator, $havingValue];
        return $this;
    }

    /**
     * This method allows you to concatenate joins for the final SQL statement.
     *
     * @param string $joinTable     The name of the table.
     * @param string $joinCondition the condition.
     * @param string $joinType      'LEFT', 'INNER' etc.
     * @return self
     * @throws Exception
     * @uses $MySqliDb->join('table1', 'field1 <> field2', 'LEFT')
     */
    final public function join($joinTable, $joinCondition, $joinType = '')
    {
        $allowedTypes = ['LEFT', 'RIGHT', 'OUTER', 'INNER', 'LEFT OUTER', 'RIGHT OUTER', 'NATURAL'];
        $joinType = strtoupper(trim($joinType));
        if ($joinType && !in_array($joinType, $allowedTypes)) {
            throw new Exception('Wrong JOIN type: ' . $joinType);
        }
        if (!is_object($joinTable)) {
            $joinTable = static::$prefix . $joinTable;
        }
        $this->_join[] = [$joinType, $joinTable, $joinCondition];
        return $this;
    }

    /**
     * This is a basic method which allows you to import raw .CSV data into a table
     * Please check out http://dev.mysql.com/doc/refman/5.7/en/load-data.html for a valid .csv file.
     *
     * @param string $importTable    The database table where the data will be imported into.
     * @param string $importFile     The file to be imported. Please use double backslashes \\ and make sure you
     * @param string $importSettings An Array defining the import settings as described in the README.md
     * @return boolean
     * @throws Exception
     * @author Jonas Barascu (Noneatme)
     */
    final public function loadData($importTable, $importFile, $importSettings = null)
    {
        // We have to check if the file exists
        if (!file_exists($importFile)) {
            // Throw an exception
            throw new Exception("importCSV -> importFile " . $importFile . " does not exists!");
        }
        // Define the default values
        // We will merge it later
        $settings = ["fieldChar" => ';', "lineChar" => PHP_EOL, "linesToIgnore" => 1];
        // Check the import settings
        if (gettype($importSettings) == "array") {
            // Merge the default array with the custom one
            $settings = array_merge($settings, $importSettings);
        }
        // Add the prefix to the import table
        $table = static::$prefix . $importTable;
        // Add 1 more slash to every slash so maria will interpret it as a path
        $importFile = str_replace("\\", "\\\\", $importFile);
        // Switch between LOAD DATA and LOAD DATA LOCAL
        $loadDataLocal = isset($settings["loadDataLocal"]) ? 'LOCAL' : '';
        // Build SQL Syntax
        $sqlSyntax = sprintf('LOAD DATA %s INFILE \'%s\' INTO TABLE %s',
            $loadDataLocal, $importFile, $table);
        // FIELDS
        $sqlSyntax .= sprintf(' FIELDS TERMINATED BY \'%s\'', $settings["fieldChar"]);
        if (isset($settings["fieldEnclosure"])) {
            $sqlSyntax .= sprintf(' ENCLOSED BY \'%s\'', $settings["fieldEnclosure"]);
        }
        // LINES
        $sqlSyntax .= sprintf(' LINES TERMINATED BY \'%s\'', $settings["lineChar"]);
        if (isset($settings["lineStarting"])) {
            $sqlSyntax .= sprintf(' STARTING BY \'%s\'', $settings["lineStarting"]);
        }
        // IGNORE LINES
        $sqlSyntax .= sprintf(' IGNORE %d LINES', $settings["linesToIgnore"]);
        // Execute the query unprepared because LOAD DATA only works with unprepared statements.
        $result = $this->queryUnprepared($sqlSyntax);
        // Are there rows modified?
        // Let the user know if the import failed / succeeded
        return (bool)$result;
    }

    /**
     * Pushes a unprepared statement to the mysqli stack.
     * WARNING: Use with caution.
     * This method does not escape strings by default so make sure you'll never use it in production.
     *
     * @param string $query
     * @return bool|mysqli_result
     * @throws Exception
     * @author Jonas Barascu
     */
    private function queryUnprepared(string $query)
    {
        // Execute query
        $stmt = $this->mysqli()->query($query);
        // Failed?
        if ($stmt !== false) {
            return $stmt;
        }
        if ($this->mysqli()->errno === 2006 && $this->autoReconnect === true && $this->autoReconnectCount === 0) {
            $this->connect($this->defConnectionName);
            $this->autoReconnectCount++;
            return $this->queryUnprepared($query);
        }
        throw new RuntimeException(sprintf('Unprepared Query Failed, ERRNO: %u (%s)', $this->mysqli()->errno, $this->mysqli()->error), $this->mysqli()->errno);
    }

    /**
     * This method is useful for importing XML files into a specific table.
     * Check out the LOAD XML syntax for your MySQL server.
     *
     * @param string $importTable    The table in which the data will be imported to.
     * @param string $importFile     The file which contains the .XML data.
     * @param string $importSettings An Array defining the import settings as described in the README.md
     * @return boolean Returns true if the import succeeded, false if it failed.
     * @throws Exception
     * @author Jonas Barascu
     */
    final public function loadXml($importTable, $importFile, $importSettings = null)
    {
        // We have to check if the file exists
        if (!file_exists($importFile)) {
            // Does not exists
            throw new Exception("loadXml: Import file does not exists");
            return;
        }
        // Create default values
        $settings = ["linesToIgnore" => 0];
        // Check the import settings
        if (gettype($importSettings) == "array") {
            $settings = array_merge($settings, $importSettings);
        }
        // Add the prefix to the import table
        $table = static::$prefix . $importTable;
        // Add 1 more slash to every slash so maria will interpret it as a path
        $importFile = str_replace("\\", "\\\\", $importFile);
        // Build SQL Syntax
        $sqlSyntax = sprintf('LOAD XML INFILE \'%s\' INTO TABLE %s',
            $importFile, $table);
        // FIELDS
        if (isset($settings["rowTag"])) {
            $sqlSyntax .= sprintf(' ROWS IDENTIFIED BY \'%s\'', $settings["rowTag"]);
        }
        // IGNORE LINES
        $sqlSyntax .= sprintf(' IGNORE %d LINES', $settings["linesToIgnore"]);
        // Exceute the query unprepared because LOAD XML only works with unprepared statements.
        $result = $this->queryUnprepared($sqlSyntax);
        // Are there rows modified?
        // Let the user know if the import failed / succeeded
        return (bool)$result;
    }

    /**
     * This method allows you to specify multiple (method chaining optional) ORDER BY statements for SQL queries.
     *
     * @param string $orderByField         The name of the database field.
     * @param string $orderbyDirection
     * @param mixed  $customFieldsOrRegExp Array with fieldset for ORDER BY FIELD() ordering or string with regular
     *                                     expression for ORDER BY REGEXP ordering
     * @return self
     * @throws Exception
     * @uses $MySqliDb->orderBy('id', 'desc')->orderBy('name', 'desc', '^[a-z]')->orderBy('name', 'desc');
     */
    final public function orderBy($orderByField, $orderbyDirection = "DESC", $customFieldsOrRegExp = null)
    {
        $allowedDirection = ["ASC", "DESC"];
        $orderbyDirection = strtoupper(trim($orderbyDirection));
        $orderByField = preg_replace("/[^ -a-z0-9\.\(\),_`\*\'\"]+/i", '', $orderByField);
        // Add table prefix to orderByField if needed.
        //FIXME: We are adding prefix only if table is enclosed into `` to distinguish aliases
        // from table names
        $orderByField = preg_replace('/(\`)([`a-zA-Z0-9_]*\.)/', '\1' . static::$prefix . '\2', $orderByField);
        if (empty($orderbyDirection) || !in_array($orderbyDirection, $allowedDirection)) {
            throw new Exception('Wrong order direction: ' . $orderbyDirection);
        }
        if (is_array($customFieldsOrRegExp)) {
            foreach ($customFieldsOrRegExp as $key => $value) {
                $customFieldsOrRegExp[$key] = preg_replace("/[^\x80-\xff-a-z0-9\.\(\),_` ]+/i", '', $value);
            }
            $orderByField = 'FIELD (' . $orderByField . ', "' . implode('","', $customFieldsOrRegExp) . '")';
        }
        elseif (is_string($customFieldsOrRegExp)) {
            $orderByField = $orderByField . " REGEXP '" . $customFieldsOrRegExp . "'";
        }
        elseif ($customFieldsOrRegExp !== null) {
            throw new Exception('Wrong custom field or Regular Expression: ' . $customFieldsOrRegExp);
        }
        $this->_orderBy[$orderByField] = $orderbyDirection;
        return $this;
    }

    /**
     * This method allows you to specify multiple (method chaining optional) GROUP BY statements for SQL queries.
     *
     * @param string $groupByField The name of the database field.
     * @return self
     * @uses $MySqliDb->groupBy('name');
     */
    final public function groupBy($groupByField)
    {
        $groupByField = preg_replace("/[^-a-z0-9\.\(\),_\* <>=!]+/i", '', $groupByField);
        $this->_groupBy[] = $groupByField;
        return $this;
    }

    /**
     * This method sets the current table lock method.
     *
     * @param string $method The table lock method. Can be READ or WRITE.
     * @return self
     * @throws Exception
     * @author Jonas Barascu
     */
    final public function setLockMethod($method)
    {
        // Switch the uppercase string
        switch (strtoupper($method)) {
            // Is it READ or WRITE?
            case "READ" || "WRITE":
                // Succeed
                $this->_tableLockMethod = $method;
                break;
            default:
                // Else throw an exception
                throw new Exception("Bad lock type: Can be either READ or WRITE");
                break;
        }
        return $this;
    }

    /**
     * Locks a table for R/W action.
     *
     * @param string|array $table The table to be locked. Can be a table or a view.
     * @return bool if succeeded;
     * @throws Exception
     * @author Jonas Barascu
     */
    final public function lock($table)
    {
        // Main Query
        $this->_query = "LOCK TABLES";
        // Is the table an array?
        if (gettype($table) == "array") {
            // Loop trough it and attach it to the query
            foreach ($table as $key => $value) {
                if (gettype($value) == "string") {
                    if ($key > 0) {
                        $this->_query .= ",";
                    }
                    $this->_query .= " " . static::$prefix . $value . " " . $this->_tableLockMethod;
                }
            }
        }
        else {
            // Build the table prefix
            $table = static::$prefix . $table;
            // Build the query
            $this->_query = "LOCK TABLES " . $table . " " . $this->_tableLockMethod;
        }
        // Execute the query unprepared because LOCK only works with unprepared statements.
        $result = $this->queryUnprepared($this->_query);
        $errno = $this->mysqli()->errno;
        // Reset the query
        $this->reset();
        // Are there rows modified?
        if ($result) {
            // Return true
            // We can't return ourself because if one table gets locked, all other ones get unlocked!
            return true;
        }
        // Something went wrong
        else {
            throw new Exception("Locking of table " . $table . " failed", $errno);
        }
        // Return the success value
        return false;
    }

    /**
     * Unlocks all tables in a database.
     * Also commits transactions.
     *
     * @return self
     * @throws RuntimeException
     * @author Jonas Barascu
     */
    final public function unlock(): self
    {
        // Build the query
        $this->_query = "UNLOCK TABLES";
        // Execute the query unprepared because UNLOCK and LOCK only works with unprepared statements.
        $result = $this->queryUnprepared($this->_query);
        $errno = $this->mysqli()->errno;
        // Reset the query
        $this->reset();
        // Are there rows modified?
        if ($result) {
            // return self
            return $this;
        }
        // Something went wrong
        throw new RuntimeException("Unlocking of tables failed", $errno);
    }

    /**
     * This methods returns the ID of the last inserted item
     *
     * @return int The last inserted item ID.
     * @throws Exception
     */
    final public function getInsertId():int
    {
        return $this->mysqli()->insert_id;
    }

    /**
     * Escape harmful characters which might affect a query.
     *
     * @param string $str The string to escape.
     * @return string The escaped string.
     * @throws Exception
     */
    final public function escape(string $str): string
    {
        return $this->mysqli()->real_escape_string($str);
    }

    /**
     * Method to call mysqli->ping() to keep unused connections open on
     * long-running scripts, or to reconnect timed out connections (if php.ini has
     * global mysqli.reconnect set to true). Can't do this directly using object
     * since _mysqli is protected.
     *
     * @return bool True if connection is up
     * @throws Exception
     */
    final public function ping(): bool
    {
        return $this->mysqli()->ping();
    }

    /* Helper functions */

    /**
     * Method returns last executed query
     *
     * @return string
     */
    final public function getLastQuery(): string
    {
        return $this->_lastQuery;
    }

    /**
     * Method returns mysql error
     *
     * @return string
     * @throws Exception
     */
    final public function getLastError(): string
    {
        if (!isset($this->_mysqli[$this->defConnectionName])) {
            return "mysqli is null";
        }
        return trim($this->_stmtError . " " . $this->mysqli()->error);
    }

    /**
     * Method returns mysql error code
     *
     * @return int
     */
    final public function getLastErrno(): int
    {
        return $this->_stmtErrno;
    }

    /**
     * Mostly internal method to get query and its params out of subquery object
     * after get() and getAll()
     *
     * @return array
     */
    final public function getSubQuery(): ?array
    {
        if (!$this->isSubQuery) {
            return null;
        }
        array_shift($this->_bindParams);
        $val = ['query'  => $this->_query,
                'params' => $this->_bindParams,
                'alias'  => isset($this->connectionsSettings[$this->defConnectionName]) ? $this->connectionsSettings[$this->defConnectionName]['host'] : null,
        ];
        $this->reset();
        return $val;
    }

    /**
     * Method returns generated interval function as an insert/update function
     *
     * @param string $diff interval in the formats:
     *                     "1", "-1d" or "- 1 day" -- For interval - 1 day
     *                     Supported intervals [s]econd, [m]inute, [h]hour, [d]day, [M]onth, [Y]ear
     *                     Default null;
     * @param string $func Initial date
     * @return array
     * @throws Exception
     */
    final public function now(string $diff = null, string $func = "NOW()"): array
    {
        return ["[F]" => [$this->interval($diff, $func)]];
    }

    /**
     * Method returns generated interval function as a string
     *
     * @param string $diff interval in the formats:
     *                     "1", "-1d" or "- 1 day" -- For interval - 1 day
     *                     Supported intervals [s]econd, [m]inute, [h]hour, [d]day, [M]onth, [Y]ear
     *                     Default null;
     * @param string $func Initial date
     * @return string
     * @throws Exception
     */
    final public function interval(string $diff, string $func = "NOW()"): string
    {
        $types = ["s" => "second", "m" => "minute", "h" => "hour", "d" => "day", "M" => "month", "Y" => "year"];
        $incr = '+';
        $items = '';
        $type = 'd';
        if ($diff && preg_match('/([+-]?) ?(\d+) ?([a-zA-Z]?)/', $diff, $matches)) {
            if (!empty($matches[1])) {
                $incr = $matches[1];
            }
            if (!empty($matches[2])) {
                $items = $matches[2];
            }
            if (!empty($matches[3])) {
                $type = $matches[3];
            }
            if (!array_key_exists($type, $types)) {
                throw new RuntimeException("invalid interval type in '{$diff}'");
            }
            $func .= " " . $incr . " interval " . $items . " " . $types[$type] . " ";
        }
        return $func;
    }

    /**
     * Method generates incremental function call
     *
     * @param int $num increment by int or float. 1 by default
     * @return array
     * @throws RuntimeException
     */
    final public function inc(int $num = 1): array
    {
        if (!is_numeric($num)) {
            throw new RuntimeException('Argument supplied to inc must be a number');
        }
        return ["[I]" => "+" . $num];
    }

    /**
     * Method generates decremental function call
     *
     * @param int $num increment by int or float. 1 by default
     * @return array
     * @throws RuntimeException
     */
    final public function dec(int $num = 1): array
    {
        if (!is_numeric($num)) {
            throw new RuntimeException('Argument supplied to dec must be a number');
        }
        return ["[I]" => "-" . $num];
    }

    /**
     * Method generates change boolean function call
     *
     * @param string $col column name. null by default
     * @return array
     */
    final public function not(string $col = null): array
    {
        return ["[N]" => (string)$col];
    }

    /**
     * Method generates user defined function call
     *
     * @param string $expr user function body
     * @param array  $bindParams
     * @return array
     */
    final public function func(string $expr, array $bindParams = null): array
    {
        return ["[F]" => [$expr, $bindParams]];
    }

    /**
     * Method returns a copy of a mysqlidb subquery object
     *
     * @return self new mysqlidb object
     */
    final public function copy(): self
    {
        $copy = unserialize(serialize($this), ['allowed_classes' => true]);
        $copy->_mysqli = [];
        return $copy;
    }

    /**
     * Shutdown handler to rollback uncommited operations in order to keep
     * atomic operations sane.
     *
     * @throws Exception
     * @uses mysqli->rollback();
     */
    final public function _transaction_status_check(): void
    {
        if (!$this->_transaction_in_progress) {
            return;
        }
        $this->rollback();
    }

    /**
     * Query execution time tracking switch
     *
     * @param bool   $enabled     Enable execution time tracking
     * @param string $stripPrefix Prefix to strip from the path in exec log
     * @return self
     */
    final public function setTrace(bool $enabled, string $stripPrefix = null): self
    {
        $this->traceEnabled = $enabled;
        $this->traceStripPrefix = $stripPrefix;
        return $this;
    }

    /**
     * Method to check if needed table is created
     *
     * @param array $tables Table name or an Array of table names to check
     * @return bool True if table exists
     * @throws Exception
     */
    final public function tableExists(array $tables): bool
    {
        $tables = !is_array($tables) ? [$tables] : $tables;
        $count = count($tables);
        if ($count === 0) {
            return false;
        }
        foreach ($tables as $i => $value) {
            $tables[$i] = static::$prefix . $value;
        }
        $db = isset($this->connectionsSettings[$this->defConnectionName]) ? $this->connectionsSettings[$this->defConnectionName]['db'] : null;
        $this->where('table_schema', $db);
        $this->where('table_name', $tables, 'in');
        $this->get('information_schema.tables', $count);
        return $this->count == $count;
    }

    /**
     * Return result as an associative array with $idField field value used as a record key
     * Array Returns an array($k => $v) if get(.."param1, param2"), array ($k => array ($v, $v)) otherwise
     *
     * @param string $idField field name to use for a mapped element key
     * @return self
     */
    final public function map(string $idField): self
    {
        $this->_mapKey = $idField;
        return $this;
    }

    /**
     * Pagination wrapper to get()
     *
     * @access public
     * @param string       $table  The name of the database table to work with
     * @param int          $page   Page number
     * @param array|string $fields Array or coma separated list of fields to fetch
     * @return array
     * @throws Exception
     */
    final public function paginate(string $table,int $page, $fields = null): array
    {
        $offset = $this->pageLimit * ($page - 1);
        $res = $this->withTotalCount()->get($table, [$offset, $this->pageLimit], $fields);
        $this->totalPages = ceil($this->totalCount / $this->pageLimit);
        return $res;
    }

    /**
     * Function to enable SQL_CALC_FOUND_ROWS in the get queries
     *
     * @return self
     * @throws Exception
     */
    final public function withTotalCount(): self
    {
        $this->setQueryOption('SQL_CALC_FOUND_ROWS');
        return $this;
    }

    /**
     * This method allows you to specify multiple (method chaining optional) options for SQL queries.
     *
     * @param string|array $options The options name of the query.
     * @return self
     * @throws Exception
     * @uses $MySqliDb->setQueryOption('name');
     */
    final public function setQueryOption(array $options): self
    {
        $allowedOptions = ['ALL', 'DISTINCT', 'DISTINCTROW', 'HIGH_PRIORITY', 'STRAIGHT_JOIN', 'SQL_SMALL_RESULT',
                           'SQL_BIG_RESULT', 'SQL_BUFFER_RESULT', 'SQL_CACHE', 'SQL_NO_CACHE', 'SQL_CALC_FOUND_ROWS',
                           'LOW_PRIORITY', 'IGNORE', 'QUICK', 'MYSQLI_NESTJOIN', 'FOR UPDATE', 'LOCK IN SHARE MODE'];
        if (!is_array($options)) {
            $options = [$options];
        }
        foreach ($options as $option) {
            $option = strtoupper($option);
            if (!in_array($option, $allowedOptions)) {
                throw new RuntimeException('Wrong query option: ' . $option);
            }
            if ($option === 'MYSQLI_NESTJOIN') {
                $this->_nestJoin = true;
            }
            elseif ($option === 'FOR UPDATE') {
                $this->_forUpdate = true;
            }
            elseif ($option === 'LOCK IN SHARE MODE') {
                $this->_lockInShareMode = true;
            }
            else {
                $this->_queryOptions[] = $option;
            }
        }
        return $this;
    }

    /**
     * This method allows you to specify multiple (method chaining optional) OR WHERE statements for the join table on
     * part of the SQL query.
     *
     * @param string $whereJoin  The name of the table followed by its prefix.
     * @param string $whereProp  The name of the database field.
     * @param mixed  $whereValue The value of the database field.
     * @param string $operator
     * @return self
     * @uses $dbWrapper->joinWhere('user u', 'u.id', 7)->where('user u', 'u.title', 'MyTitle');
     */
    final public function joinOrWhere(string $whereJoin, string $whereProp, $whereValue = 'DBNULL', string $operator = '='): self
    {
        return $this->joinWhere($whereJoin, $whereProp, $whereValue, $operator, 'OR');
    }

    /**
     * This method allows you to specify multiple (method chaining optional) AND WHERE statements for the join table on
     * part of the SQL query.
     *
     * @param string $whereJoin  The name of the table followed by its prefix.
     * @param string $whereProp  The name of the database field.
     * @param mixed  $whereValue The value of the database field.
     * @param string $operator
     * @param string $cond
     * @return self
     * @uses $dbWrapper->joinWhere('user u', 'u.id', 7)->where('user u', 'u.title', 'MyTitle');
     */
    final public function joinWhere(string $whereJoin, string $whereProp, $whereValue = 'DBNULL', string $operator = '=', string $cond = 'AND'): self
    {
        $this->_joinAnd[static::$prefix . $whereJoin][] = [$cond, $whereProp, $operator, $whereValue];
        return $this;
    }

    /**
     * Abstraction method that will build an JOIN part of the query
     *
     * @return void
     */
    final public function _buildJoinOld():void 
    {
        if (empty($this->_join)) {
            return;
        }
        foreach ($this->_join as $data) {
            [$joinType, $joinTable, $joinCondition] = $data;
            if (is_object($joinTable)) {
                $joinStr = $this->_buildPair("", $joinTable);
            }
            else {
                $joinStr = $joinTable;
            }
            $this->_query .= " " . $joinType . " JOIN " . $joinStr .
                (false !== stripos($joinCondition, 'using') ? " " : " on ")
                . $joinCondition;
        }
    }
}
