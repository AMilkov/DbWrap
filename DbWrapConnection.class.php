<?php

/**
 * creates a connection to the database and queries it with ability to auto reconnect
 */
class DbWrapConnection {

	/**
	 * @var bool if defunct calling execute will kill the script again but this time if it's used inside register shutdown functions and this variable allows the code to know this is what has happened and is now running in the register shutdown function
	 */
	private $registerShutdownDefunct = false;

	/**
	 * @var DbWrapProfilerResults where benchmarks are sent
	 */
	private $profiler = null;

	/**
	 * @var array how many reconnect attempts before declaring the query as failed
	 */
	private $reconnectRetryDelays = array(
		0.5,			//1st reconnect after 0.5 seconds
		1,			//2nd reconnect after 1 second
		3			//3rd reconnect after 3 seconds
	);

	private $credentials = array(
		"dsn" => "",
		"username" => "",
		"password" => ""
	);

	/**
	 * @var bool registers if these actions should be within a transaction
	 */
	private $isTransactionActive = false;

	/**
	 * @var PDO connection to use
	 */
	private $connection = null;

	/**
	 * @var array cached prepared statements for the given connection
	 */
	private $preparedStatementsPool = array();

	/**
	 * @var string current query
	 */
	private $activeRawQuery = "";

	/**
	 * @var current PDO prepared statement
	 */
	private $activePreparedStatement = null;

	/**
	 * @param string $dsn connection details to the db
	 * @param string $username
	 * @param string $password
	 */
	public function __construct($dsn, $username, $password) {

		//store the credentials
		$this->credentials['dsn'] = $dsn;
		$this->credentials['username'] = $username;
		$this->credentials['password'] = $password;

	}

	/**
	 * @param iDbWrapProfilerResults $profiler where queries will be stored
	 */
	public function setProfiler(iDbWrapProfilerResults $profiler) {
		$this->profiler = clone $profiler;
	}

	/**
	 * provides a copy of the profiler results for analytics and logging purposes
	 * @return DbWrapProfilerResults
	 */
	public function getProfiler() {
		if ($this->profiler) {
			return clone $this->profiler;
		}
		return null;
	}

	/**
	 * attempt to create a connection to the database
	 */
	private function connect() {

		//if there is already an active connection, re-use that one
		if ($this->connection) {
			return true;
		}

		try {

			// Don't allow reconnection if part of a transaction
			if ($this->isTransactionActive) {
				throw new Exception("Unable to automatically reconnect to the DB because it was half way through a transaction!");
			}

			// Create the connection to database via PDO
			$this->connection = new PDO(
				$this->credentials['dsn'],
				$this->credentials['username'],
				$this->credentials['password'],
				array(
					PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
				)
			);

			//used to force a timeout allowing to test the auto reconnect feature
			//$this->connection->query("SET session wait_timeout=5");

			return true;

			} catch (Exception $e) {

			//couldn't connect
			$this->connection = null;

		}

		return false;

	}

	/**
	 * @param string $attempt message containing how many retries have happened and the maximum retries before dieing
	 * @return bool
	 */
	private function reconnect($attempt) {

		//destroy the current connection
		$this->connection = null;

		//destroy all the previously prepared statements as they will no longer exist
		$this->preparedStatementsPool = array();

		//destroy the currently prepared statement as it will need preparing again
		$this->activePreparedStatement = null;

		//attempt a reconnect
		return $this->connect();

	}

	/**
	 * @param string $query
	 * @return $this allows you to method chain prepare() with execute()
	 */
	public function prepare($query) {

		//save it ready to prepare it later if needs be (when ->execute() is called)
		$this->activeRawQuery = $query;

		//allow method chaining
		return $this;

	}

	/**
	 * you only need to check this when you are attempting db interaction AFTER the main execution of your script is finished and your script is in the process of "shutting down".
	 * such as after a call to die(), exit() or a fatal error has been triggered (by this db class) and is now executing registered shutdown functions (if you do happen to forget check this method you will get an error triggered anyway)
	 */
	public function isDefunct() {
		return $this->registerShutdownDefunct;
	}

	/**
	 * executes the prepared query - can be method chained
	 * @param array $params assoc array key=>value. You can also expand infinite unknown values such as when using IN(:member_ids) by entering an array of values for the key eg: array("member_ids" => array(1,2,3,4,5))
	 * @param boolean $autoReconnect prevents mysql from timing out and then the query failing - do not change the default!!!
	 * @return DbWrapResults
	 * @throws Exception
	 */
	public function execute(array $params = array(), $autoReconnect = true) {

		//don't allow the register shutdown function to run because the db connection has already failed
		if ($this->registerShutdownDefunct) {
			throw new Exception("Your database connection failed in the main execution of your script and is now executing in the shutdown phase (such as those callbacks passed to the register_shutdown function). However, inside these functions you are trying to re-establish the database connection which has already failed in your main script and caused you to enter the shutdown phase! It is recommended that when you program your shutdown functions that you check the method isDefunct() on your database handle before executing code in the shutdown phase. This will prevent this error and allow you to continue executing non db related shutdown functions.");
		}

		//attempt to execute it
		try {

			//this method will recursively execute if there is something wrong with the query
			if ($this->connect() == false) {
				if ($autoReconnect == false) {
					return null;
					} else {
					throw new Exception("Lost connection to the database and could not re-establish a connection.");
				}
			}

			//the query
			$query = $this->activeRawQuery;

			//expand batched tokens by injecting multiple names and params to replace the single instance (such as when used with IN () and so on that are comma delimited)
			foreach ($params as $key=>$value) {
				if (is_array($value)) {
					$insertParams = array();
					$replacementToken = "";
					foreach ($value as $counter=>$string) {
						$generatedKey = "dbautogenkey_{$key}_{$counter}";
						if (strpos($query, ":{$generatedKey}") !== false) {

							//used to mark the db as defunct so when the register shutdown functions execute they an be made aware of this
							$this->registerShutdownDefunct = true;

							//trigger error
							throw new Exception("Key clash detected when generating temporary keys for the batch replacement. The generated key that clashed was called {$generatedKey} when trying to prepare this statement:\n{$query}\n");

						}
						$replacementToken .= ":{$generatedKey},";
						$insertParams[$generatedKey] = $string;
					}
					$replacementToken = rtrim($replacementToken, ",");
					$query = preg_replace("@:" . preg_quote($key) ."([^a-zA-Z0-9_])@", $replacementToken . '$1', $query);
					$params = array_merge(
						$params,
						$insertParams
					);
					unset($params[$key]);
				}
			}

			//create a unique hash for this prepared statement
			$queryHash = hash("sha256", $query);

			//see if it's already prepared
			if (empty($this->preparedStatementsPool[$queryHash])) {
				$this->preparedStatementsPool[$queryHash] = $this->connection->prepare($query);
			}

			//set it as the active prepared statement (used by ->execute())
			$this->activePreparedStatement = $this->preparedStatementsPool[$queryHash];

			//execute and benchmark the query
			$time_start = microtime(true);
			$this->activePreparedStatement->execute($params);
			$queryExecutionTime = microtime(true) - $time_start;

			//add the benchmark to the profiler
			if ($this->profiler) {
				$this->profiler->addQuery($this->simulateExecutedQuery($query, $params), $queryExecutionTime, $this->activePreparedStatement->rowCount());
			}

			//ensure we can fetch the results - this stops exceptions being thrown for inserts and deletes which instead return an empty array
			$this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
			$rawResults = $this->activePreparedStatement->fetchAll(PDO::FETCH_ASSOC);
			$this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

			//where to store the raw results
			$resultsObject = new DbWrapResults();

			//scan over each row
			foreach ($rawResults as $row) {
				$resultsObject->addRow($row);
			}

			//done
			return $resultsObject;

		} catch (Exception $e) {

			//see if this is the initial call to this function...
			if ($autoReconnect == true) {

				//only attempt a reconnect if the server has gone away i.e. ping fails
				if ($this->ping() == false) {

					//attempt X retries
					foreach ($this->reconnectRetryDelays as $attempt=>$timeout_seconds) {

						//sleep for x seconds
						usleep($timeout_seconds * 1000000);

						//attempt automatic reconnect
						if ($this->reconnect(($attempt + 1) ." of " . count($this->reconnectRetryDelays))) {
							$result = $this->execute($params, false);
							if ($result !== null) {
								return $result;
							}
						}

					}

				}

				//so that if any register shutdown functions use this library, it won't re-trigger the auto reconnect system and create misleading errors
				$this->registerShutdownDefunct = true;

				//couldn't reconnect so what ever the cause, the query hard failed...
				$simulatedQuery = "";
				if (isset($query) && isset($params)) {
					$simulatedQuery = $this->simulateExecutedQuery($query, $params);
				}
				throw new Exception("Could not execute the follow query because of: {$e->getCode()} => {$e->getMessage()}\n{$simulatedQuery}" . print_r($params, true));

			}

		}

		//didn't work - but this is primarily used by the automatic reconnect functionality and won't ever be returned to the person using this db class
		return null;

	}

	/**
	 * will establish if the server has disconnected - helps work out if there is an SQL syntax error for example and not re-issue the query (causing duplicate queries)
	 * @return bool
	 */
	private function ping() {

		//connect if not already connected
		if ($this->connect() == false) {
			return false;
		}

		try {
			$this->connection->query('SELECT 1');
			} catch (Exception $e) {
			return false;
		}

		return true;

	}

	/**
	 * @return string the last insert id
	 */
	public function lastInsertId() {
		return $this->connection->lastInsertId();
	}

	/**
	 * @return array containing each table name in the database
	 */
	public function getAllTables() {

		//fetch the tables
		$tables = array();
		$this->prepare("SHOW TABLES;");
		$result = $this->execute();
		foreach ($result->fetchArray() as $result) {
			if ($result) {
				$tables[] = array_shift($result);
			}
		}

		return $tables;

	}

	/**
	 * simulates the query that would have been executed
	 * @param $query
	 * @param $params
	 * @return string
	 */
	public function simulateExecutedQuery($query, $params) {

		//connect if not already connected
		if ($this->connect() == false) {
			return "";
		}

		//add some whitespace so the named parameter replacement still works if right at the end of the query
		$query = " {$query} ";

		//scan over each named parameter and replace it
		foreach ($params as $key=>$value) {
			//the regex is the same that is used in the PDO source code
			$query = preg_replace("@:{$key}([^a-zA-Z0-9_])@", $this->connection->quote($value) . '$1', $query);
		}

		//output the estimated query that will be executed removing the whitespace that was added
		return trim($query);

	}

	/**
	 * @return bool
	 */
	public function transactionBegin() {

		//connect if not already connected
		if ($this->connect() == false) {
			return false;
		}

		try {
			$this->isTransactionActive = true;
			$this->connection->beginTransaction();
			} catch (Exception $e) {
			return false;
		}

		return true;

	}

	/**
	 * @return bool
	 */
	public function transactionRollback() {

		//connect if not already connected
		if ($this->connect() == false) {
			return false;
		}

		try {
			$this->connection->rollBack();
			$this->isTransactionActive = false;
			} catch (Exception $e) {
			return false;
		}

		return true;

	}

	/**
	 * @return bool
	 */
	public function transactionCommit() {

		//connect if not already connected
		if ($this->connect() == false) {
			return false;
		}

		try {
			$this->connection->commit();
			$this->isTransactionActive = false;
			} catch (Exception $e) {
			return false;
		}

		return true;

	}

}

?>