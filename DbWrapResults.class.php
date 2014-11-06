<?php

/*
//get a a single result from the first field of the first row such as when using COUNT()
$result = DbWrapHandler::create()
	->prepare("")
	->execute()
	->result();

//execute a query and fetch an array of objects representing each row
$rows = DbWrapHandler::create()
	->prepare("")
	->execute()
	->fetchObject();
foreach ($rows as $row) {

//execute a query and fetch an array of arrays representing each row
$rows = DbWrapHandler::create()
	->prepare("")
	->execute()
	->fetchArray();
foreach ($rows as $row) {

//fetch a single row as an object, false if doesn't exist
DbWrapHandler::create()
	->prepare("")
	->execute()
	->fetchObject(0);

//safely prepare an infinite multiple values in functions with comma separated lists such as IN()
DbWrapHandler::create()
	->prepare("SELECT `username`, `email_address` FROM `member_accounts` WHERE `member_id` IN(:member_ids)")
	->execute(
		array(
			"member_ids" => array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10)
		)
	)
	->fetchArray();
*/

class DbWrapResults {

	private $results = array();

	public function addRow(array $assocRow) {
		$this->results[] = $assocRow;
	}

	/**
	 * @return mixed if there is no result, will return null
	 */
	public function result() {
		return isset($this->results[0]) ? reset($this->results[0]) : null;
	}

	/**
	 * @param bool $cherryPickRow will return the single row meaning you can instantly use it, offset starts at 0
	 * @return array of objects or just a cherry picked object/null if couldn't be cherry picked
	 */
	public function fetchObject($cherryPickRow = false) {
		$results = array();
		foreach ($this->results as $row) {
			$results[] = (object) $row;
		}
		if ($cherryPickRow !== false) {
			return isset($results[$cherryPickRow]) ? $results[$cherryPickRow] : null;
		}
		return $results;
	}

	/**
	 * @param bool $cherryPickRow will return the single row meaning you can instantly use it, offset starts at 0
	 * @return array or just a cherry picked array/null if couldn't be cherry picked
	 */
	public function fetchArray($cherryPickRow = false) {
		if ($cherryPickRow !== false) {
			return isset($this->results[$cherryPickRow]) ? $this->results[$cherryPickRow] : null;
		}
		return $this->results;
	}

}

?>