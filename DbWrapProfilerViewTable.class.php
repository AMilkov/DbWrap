<?php

class DbWrapWrapProfileViewTable extends DbWrapProfilerView {

	/**
	 * @return string
	 */
	public function __toString() {

		$out = "";
		foreach ($this->results->getBreakdown() as $type => $profile) {
			$out .= "<p style='font-weight:bold;'>" . strtoupper("{$type}S") . " (" . count($profile['queries']) .") - " . round($profile['total_execution_seconds'], 6) . "</p>";
			$out .= "<ul>";
			foreach ($profile['queries'] as $query) {
				$out .= "<li>" . number_format($query['total_execution_seconds'], 6) . " ({$query['total_affected_rows']}) - " . htmlspecialchars($query['query']) . "</li>";
			}
			$out .= "</ul>";
		}
		return $out;

	}

}

?>