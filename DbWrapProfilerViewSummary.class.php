<?php

class DbWrapWrapProfilerViewSummary extends DbWrapProfilerView {

	/**
	 * @var int
	 */
	private $page_generation_time_seconds = 0;

	/**
	 * @param float $seconds
	 */
	public function set_page_generation_time_seconds($seconds) {
		$this->page_generation_time_seconds = $seconds;
	}

	/**
	 * @return string
	 */
	public function __toString() {

		//get the total query time
		$total_query_execution_time = $this->results->getTotalExecutionSeconds();

		//workout some stats
		$page_generation_time = $this->page_generation_time_seconds;
		$total_php_execution_time = $page_generation_time - $total_query_execution_time;
		$total_php_execution_time_percentage = $page_generation_time ? number_format(($total_php_execution_time / $page_generation_time) * 100, 0) : 0;
		$total_php_execution_time = number_format($total_php_execution_time, 4);
		$total_query_execution_time_percentage = $page_generation_time ? number_format(($total_query_execution_time / $page_generation_time) * 100, 0) : 0;
		$total_query_execution_time = number_format($total_query_execution_time, 4);

		$total_queries = $this->results->getTotalQueries();
		$total_rows_affected = $this->results->getTotalAffectedRows();

		//summary
		$sql_breakdown = $this->results->getBreakdown();
		$total_sql_select = count($sql_breakdown[DbWrapProfilerResults::QUERY_TYPE_SELECT]['queries']);
		$total_sql_update = count($sql_breakdown[DbWrapProfilerResults::QUERY_TYPE_UPDATE]['queries']);
		$total_sql_insert = count($sql_breakdown[DbWrapProfilerResults::QUERY_TYPE_INSERT]['queries']);
		$total_sql_delete = count($sql_breakdown[DbWrapProfilerResults::QUERY_TYPE_DELETE]['queries']);
		$total_sql_other = count($sql_breakdown[DbWrapProfilerResults::QUERY_TYPE_OTHER]['queries']);

		return "
			(--%)&nbsp;&nbsp;{$page_generation_time} Secs Page Generation<br />
			({$total_php_execution_time_percentage}%) {$total_php_execution_time} Secs PHP<br />
			({$total_query_execution_time_percentage}%) {$total_query_execution_time} Secs MySQL - {$total_queries} Queries, {$total_rows_affected} Affected Rows<br />
			{$total_sql_select} Reads, {$total_sql_update} Updates, {$total_sql_insert} Inserts, {$total_sql_delete} Deletes, {$total_sql_other} Other
		";

	}

}

?>