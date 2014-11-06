<?php

/**
 * Render profiler results
 */
abstract class DbWrapProfilerView {

	protected $results;

	public function __construct(iDbWrapProfilerResults $results) {
		$this->results = $results;
	}

	abstract public function __toString();

}

?>