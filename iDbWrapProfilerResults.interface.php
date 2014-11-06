<?php

/**
 *
 * When this is passed to
 */
interface iDbWrapProfilerResults {

	/**
	 * @param $query
	 * @param $executionTimeSeconds
	 * @param $totalAffectedRows
	 */
	public function addQuery($query, $executionTimeSeconds, $totalAffectedRows);

}
?>