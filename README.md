DbWrap
===========

DbWrap is a PHP DB Wrapper currently around PDO with the following features:

Full Profiling Support
------------------

Just pass in an instance of the profiler you want to use on which DB connection and all queries & execution time will be passed to it. There is currently one profiler you can use from the get go which will record all executed queries. You can then pass it to the relevant "view" for rendering it into something easy to read (with html formatting). You can create your own profilers (just implement the profiler interface) and views. For example it would be trivial to create a "tracer" profiler that logs all queries to a log file or just logs the slow ones etc...

You could easily turn this on or off during runtime.

Automatic Reconnection
-----------------------------

If a query fails, it will attempt an automatic reconnection (unless the last connection was part of a transaction in which case an error will be thrown). The default config is to wait 0.5 seconds, then 1 second, then 3 seconds. If the 3 attempts fail it will give up and throw an exception. You can add more retry attempts before giving up and/or reconfigure the timings.

Prepared Statements & Caching
-----------------------------

Support for both styles of prepared statements (? and :name replacements). The prepared statements are automatically cached globally and so any further call anyway in your script to the same prepared statement will use the cached version.

You are able to fully prepare dynamic values allowing you to properly escape them inside functions such as IN() i.e. IN(:member_ids)

Connection Pooling
-----------------------------

You register the known databases and connection details upfront. You are then create an instance like this: DbWrapHandler::create($optionalInstanceName)

The first time the connection is obtained, you will get a connection to the database and it will be wrapped and cached. The next time DbWrapHandler::create($optionalInstanceName) from anywhere in your project it will return the same DB connection.

Documentation
-----------------------------

The source code is very heavily documented so please read that for further details on how to use it.

Examples
====================

Register connection
-----------------------------

```php
// Add the default connection with profiling enabled - this would be in a db routine
$defaultConnection = new DbWrapConnection("mysql:host=192.168.0.1;dbname=website;charset=utf8", "user", "pass");

//$defaultConnection->setProfiler(new DbWrapProfilerResults()); // Uncomment this if you wish to capture all queries going through the connection

// Register the connection
DbWrapHandler::registerConnection($defaultConnection);

// Get a connection. this can be called from anywhere in your application layer - it will only make ONE connection per registered connection. If you want multiple connections for the same db, just register it with more instance names.
$db = DbWrapHandler::create();

// You are now setup ready to start building queries with DbWrapHandler::create()

```

Select Records & Process Rows As Array
-----------------------------

```php
$results = DbWrapHandler::create()
	->prepare("SELECT * FROM users WHERE `email_address` = :email LIMIT 10;")
	->execute(array("email" => "someone@somewhere.com"))
	->fetchArray();
foreach ($results as $row) {
	print_r($row);
}
```

Insert A Record
-------------------------------

```php
DbWrapHandler::create()
	->prepare("
		INSERT INTO `users` (
			`user_id`,
			`name`
		) VALUES (
			:user_id,
			:name
		);
	")
	->execute(
		array(
			"user_id" => 23,
			"name" => "scott",
		)
	);
```

If you want the auto inc insert id add this at the end of the above code snippet

```php
print DbWrapHandler::create()->lastInsertId();
```

Select Records Using IN()
-----------------------------

```php
$results = DbWrapHandler::create()
	->prepare("SELECT * FROM users WHERE `email_address` = :email AND IN(:user_ids) LIMIT 10;")
	->execute(
		array(
			"email" => "someone@somewhere.com",
			"user_ids" => array(1,3,4,7,8,9),
		)
	)
	->fetchArray();
foreach ($results as $row) {
	print_r($row);
}
```

Profile All Queries
---------------------------

When creating your connection, make sure you have given it a profiler (in this examples case you must call `$defaultConnection->setProfiler(new DbWrapProfilerResults());` when registering your db connection)

```php
// Output a summary of all the db queries
print new DbWrapWrapProfilerViewSummary(DbWrapHandler::create()->getProfiler());
```

Will produce output like this (after I executed this on the performance stress test):

>1.2631 Secs MySQL - 4001 Queries, 12010 Affected Rows<br />
>2001 Reads, 0 Updates, 1000 Inserts, 1000 Deletes, 0 Other

You will be best to look at the code now...