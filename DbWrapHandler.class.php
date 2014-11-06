<?php

/**
 * allows the ability to pool connections for re-use and holds details
 */
class DbWrapHandler {

	/**
	 * @var array containing the connection objects to use
	 */
	private static $registeredConnections = array();

	/**
	 * registers a connection but does not yet connect it until needed
	 * @param DbWrapConnection $connection
	 * @param string $connectionName
	 */
	public static function registerConnection(DbWrapConnection $connection, $connectionName = "default") {
		static::$registeredConnections[$connectionName] = clone $connection;
	}

	/**
	 * gets an existing connection to the database if one exists, else creates a new one
	 * @param string $connectionName
	 * @return DbWrapConnection
	 * @throws Exception
	 */
	public static function create($connectionName = "default") {

		//ensure the connection exists
		if (isset(static::$registeredConnections[$connectionName]) == false) {
			throw new Exception("The connection name '{$connectionName}' has not yet been registered. Can not connect to the database.");
		}

		//return the connection
		return static::$registeredConnections[$connectionName];

	}

	/**
	 * Disconnects all pooled database connections
	 */
	public static function disconnectAllConnections() {

		//close each connection
		foreach (static::$registeredConnections as $key=>$connection) {

			//some reason unset appears to work best compared to setting it to null etc
			unset(static::$registeredConnections[$key]);

		}

	}

}


?>