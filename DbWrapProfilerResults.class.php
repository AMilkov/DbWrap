<?php

/**
 * used by a connection to provide debugging and profiling information
 */
class DbWrapProfilerResults implements iDbWrapProfilerResults {

	const QUERY_TYPE_SELECT = 'select';
	const QUERY_TYPE_UPDATE = 'update';
	const QUERY_TYPE_DELETE = 'delete';
	const QUERY_TYPE_INSERT = 'insert';
	const QUERY_TYPE_OTHER = 'other';

	/**
	 * @var array
	 */
	protected $queries = array();

	/**
	 * @var running totals
	 */
	protected $totals = array(
		'total_queries' => 0,
		'total_affected_rows' => 0,
		'total_execution_seconds' => 0
	);

	/**
	 * @param $query
	 * @param $executionTimeSeconds
	 * @param $totalAffectedRows
	 */
	public function addQuery($query, $executionTimeSeconds, $totalAffectedRows) {
		$this->queries[] = array(
			'total_execution_seconds' => $executionTimeSeconds,
			'total_affected_rows' => $totalAffectedRows,
			'query' => $query
		);
		$this->totals['total_queries']++;
		$this->totals['total_affected_rows'] += $totalAffectedRows;
		$this->totals['total_execution_seconds'] += $executionTimeSeconds;
	}

	public function getTotalExecutionSeconds() {
		return $this->totals['total_execution_seconds'];
	}

	/**
	 * @return int
	 */
	public function getTotalQueries() {
		return $this->totals['total_queries'];
	}

	/**
	 * @return int
	 */
	public function getTotalAffectedRows() {
		return $this->totals['total_affected_rows'];
	}

	/**
	 *
	 * @return array
	 */
	public function getBreakdown() {

		//structure
		$breakdown = array(
			self::QUERY_TYPE_SELECT => array(
				'total_execution_seconds' => 0,
				'total_affected_rows' => 0,
				'queries' => array()
			),
			self::QUERY_TYPE_UPDATE => array(
				'total_execution_seconds' => 0,
				'total_affected_rows' => 0,
				'queries' => array()
			),
			self::QUERY_TYPE_INSERT => array(
				'total_execution_seconds' => 0,
				'total_affected_rows' => 0,
				'queries' => array()
			),
			self::QUERY_TYPE_DELETE => array(
				'total_execution_seconds' => 0,
				'total_affected_rows' => 0,
				'queries' => array()
			),
			self::QUERY_TYPE_OTHER => array(
				'total_execution_seconds' => 0,
				'total_affected_rows' => 0,
				'queries' => array()
			)

		);

		foreach ($this->queries as $result) {

			$type = $this->getQueryType($result['query']);

			$breakdown[$type]['total_execution_seconds'] += $result['total_execution_seconds'];
			$breakdown[$type]['total_affected_rows'] += $result['total_affected_rows'];
			$breakdown[$type]['queries'][] = $result;

		}

		return $breakdown;

	}

	/**
	 * @param $query
	 * @return mixed
	 */
	private function getQueryType($query) {

		//make the basic search more accurate
		$query = trim($query);

		//what type off query is this?
		$type = self::QUERY_TYPE_OTHER;;
		if (stripos($query, self::QUERY_TYPE_SELECT) === 0) {
			$type = self::QUERY_TYPE_SELECT;
		} elseif (stripos($query, self::QUERY_TYPE_UPDATE) === 0) {
			$type = self::QUERY_TYPE_UPDATE;
		} elseif (stripos($query, self::QUERY_TYPE_INSERT) === 0) {
			$type = self::QUERY_TYPE_INSERT;
		} elseif (stripos($query, self::QUERY_TYPE_DELETE) === 0) {
			$type = self::QUERY_TYPE_DELETE;
		}

		return $type;

	}

}

?>