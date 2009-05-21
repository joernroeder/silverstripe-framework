<?php
/**
 * Global database interface, complete with static methods.
 * Use this class for interacting with the database.
 * @package sapphire
 * @subpackage model
 */
class DB {
	/**
	 * The global database connection.
	 * @var Database
	 */
	protected static $globalConn;

	/**
	 * The last SQL query run.
	 * @var string
	 */
	public static $lastQuery;

	/**
	 * Set the global database connection.
	 * Pass an object that's a subclass of Database.  This object will be used when {@link DB::query()}
	 * is called.
	 * @var Database $globalConn
	 */
	static function setConn($globalConn) {
		DB::$globalConn = $globalConn;
	}

	/**
	 * Get the global database connection.
	 * @return Database
	 */
	static function getConn() {
		return DB::$globalConn;
	}
	
	/**
	 * Set an alternative database to use for this browser session.
	 * This is useful when using testing systems other than SapphireTest; for example, Windmill.
	 * Set it to null to revert to the main database.
	 */
	static function set_alternative_database_name($dbname) {
		$_SESSION["alternativeDatabaseName"] = $dbname;
	}
	
	/**
	 * Get the name of the database in use
	 */
	static function get_alternative_database_name() {
		return $_SESSION["alternativeDatabaseName"];	
	}

	/**
	 * Connect to a database.
	 * Given the database configuration, this method will create the correct subclass of Database,
	 * and set it as the global connection.
	 * @param array $database A map of options. The 'type' is the name of the subclass of Database to use. For the rest of the options, see the specific class.
	 */
	static function connect($databaseConfig) {
		// This is used by TestRunner::startsession() to test up a test session using an alt
		if(isset($_SESSION["alternativeDatabaseName"]) && $dbname = $_SESSION["alternativeDatabaseName"]) $databaseConfig['database'] = $dbname;
		
		if(!isset($databaseConfig['type']) || empty($databaseConfig['type'])) {
			user_error("DB::connect: Not passed a valid database config", E_USER_ERROR);
		}
		if (isset($databaseConfig['pdo']) && $databaseConfig['pdo']) { // TODO:pkrenn_remove
			$conn = new PDODatabase($databaseConfig);
		} else { // TODO:pkrenn_remove begin
			$dbClass = $databaseConfig['type'];
			$conn = new $dbClass($databaseConfig);
		} // TODO:pkrenn_remove end
		DB::setConn($conn);
	}

	/**
	 * Build the connection string from input.
	 * @param array $parameters The connection details.
	 * @return string $connect The connection string.
	 **/
	public function getConnect($parameters) {
		return DB::$globalConn->getConnect($parameters);
	}

	/**
	 * Execute the given SQL query.
	 * @param string $sql The SQL query to execute
	 * @param int $errorLevel The level of error reporting to enable for the query
	 * @return Query
	 */
	static function query($sql, $errorLevel = E_USER_ERROR) {
		DB::$lastQuery = $sql;
		/* debug helper for query efficiency
		if(substr(strtolower($sql),0,6) == 'select') {
			$product = 1;
			foreach(DB::$globalConn->query("explain " . $sql, $errorLevel) as $explainRow) {
				if($explainRow['rows']) $product *= $explainRow['rows'];
			}
			if($product > 100)
			Debug::message("Cartesian product $product for SQL: $sql");
		} */
		
		return DB::$globalConn->query($sql, $errorLevel);
	}

	/**
	 * Execute a complex manipulation on the database.
	 * A manipulation is an array of insert / or update sequences.  The keys of the array are table names,
	 * and the values are map containing 'command' and 'fields'.  Command should be 'insert' or 'update',
	 * and fields should be a map of field names to field values, including quotes.  The field value can
	 * also be a SQL function or similar.
	 * @param array $manipulation
	 */
	static function manipulate($manipulation) {
		DB::$lastQuery = $manipulation;
		return DB::$globalConn->manipulate($manipulation);
	}

	/**
	 * Get the autogenerated ID from the previous INSERT query.
	 * @return int
	 */
	static function getGeneratedID($table) {
		return DB::$globalConn->getGeneratedID($table);
	}

	/**
	 * Get the ID for the next new record for the table.
	 * @var string $table The name od the table.
	 * @return int
	 */
	static function getNextID($table) {
		return DB::$globalConn->getNextID($table);
	}

	/**
	 * Check if the connection to the database is active.
	 * @return boolean
	 */
	static function isActive() {
		if(DB::$globalConn) return DB::$globalConn->isActive();
		else return false;
	}

	/**
	 * Create the database and connect to it. This can be called if the
	 * initial database connection is not successful because the database
	 * does not exist.
	 * @param string $connect Connection string
	 * @param string $username Database username
	 * @param string $password Database Password
	 * @param string $database Database to which to create
	 * @return boolean Returns true if successful
	 */
	static function createDatabase($connect, $username, $password, $database) {
		return DB::$globalConn->createDatabase($connect, $username, $password, $database);
	}

	/**
	 * Create a new table.
	 * @param $tableName The name of the table
	 * @param $fields A map of field names to field types
	 * @param $indexes A map of indexes
	 * @param $options An map of additional options.  The available keys are as follows:
	 *   - 'MSSQLDatabase'/'MySQLDatabase'/'PostgreSQLDatabase' - database-specific options such as "engine" for MySQL.
	 *   - 'temporary' - If true, then a temporary table will be created
	 * @return The table name generated.  This may be different from the table name, for example with temporary tables.
	 */
	static function createTable($table, $fields = null, $indexes = null, $options = null) {
		return DB::$globalConn->createTable($table, $fields, $indexes, $options);
	}

	/**
	 * Create a new field on a table.
	 * @param string $table Name of the table.
	 * @param string $field Name of the field to add.
	 * @param string $spec The field specification, eg 'INTEGER NOT NULL'
	 */
	static function createField($table, $field, $spec) {
		return DB::$globalConn->createField($table, $field, $spec);
	}

	/**
	 * Generate the following table in the database, modifying whatever already exists
	 * as necessary.
	 * @param string $table The name of the table
	 * @param string $fieldSchema A list of the fields to create, in the same form as DataObject::$db
	 * @param string $indexSchema A list of indexes to create.  The keys of the array are the names of the index.
	 * @param boolean $hasAutoIncPK A flag indicating that the primary key on this table is an autoincrement type
	 * The values of the array can be one of:
	 *   - true: Create a single column index on the field named the same as the index.
	 *   - array('fields' => array('A','B','C'), 'type' => 'index/unique/fulltext'): This gives you full
	 *     control over the index.
	 * @param string $options SQL statement to append to the CREATE TABLE call.
	 */
	static function requireTable($table, $fieldSchema = null, $indexSchema = null, $hasAutoIncPK=true, $options = null) {
		return DB::$globalConn->requireTable($table, $fieldSchema, $indexSchema, $hasAutoIncPK, $options);
	}

	/**
	 * Generate the given field on the table, modifying whatever already exists as necessary.
	 * @param string $table The table name.
	 * @param string $field The field name.
	 * @param string $spec The field specification.
	 */
	static function requireField($table, $field, $spec) {
		return DB::$globalConn->requireField($table, $field, $spec);
	}

	/**
	 * Generate the given index in the database, modifying whatever already exists as necessary.
	 * @param string $table The table name.
	 * @param string $index The index name.
	 * @param string|boolean $spec The specification of the index. See requireTable() for more information.
	 */
	static function requireIndex($table, $index, $spec) {
		return DB::$globalConn->requireIndex($table, $index, $spec);
	}

	/**
	 * If the given table exists, move it out of the way by renaming it to _obsolete_(tablename).
	 * @param string $table The table name.
	 */
	static function dontRequireTable($table) {
		return DB::$globalConn->dontRequireTable($table);
	}

	/**
	 * Checks a table's integrity and repairs it if necessary.
	 * @var string $tableName The name of the table.
	 * @return boolean Return true if the table has integrity after the method is complete.
	 */
	static function checkAndRepairTable($table) {
		return DB::$globalConn->checkAndRepairTable($table);
	}

	/**
	 * Return the number of rows affected by the previous operation.
	 * @return int
	 */
	static function affectedRows() {
		return DB::$globalConn->affectedRows();
	}

	/**
	 * Returns a list of all tables in the database.
	 * The table names will be in lower case.
	 * @return array
	 */
	static function tableList() {
		return DB::$globalConn->tableList();
	}
	
	/**
	 * Get a list of all the fields for the given table.
	 * Returns a map of field name => field spec.
	 * @param string $table The table name.
	 * @return array
	 */
	static function fieldList($table) {
		return DB::$globalConn->fieldList($table);
	}

	/**
	 * Enable supression of database messages.
	 */
	static function quiet() {
		return DB::$globalConn->quiet();
	}
}
?>