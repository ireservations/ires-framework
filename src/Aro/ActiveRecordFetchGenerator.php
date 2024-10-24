<?php

namespace Framework\Aro;

use App\Services\Aro\AppActiveRecordObject;

/**
 * @template Value of AppActiveRecordObject
 * @extends ActiveRecordGenerator<Value>
 */
class ActiveRecordFetchGenerator extends ActiveRecordGenerator {

	protected function fetch() : array {
		$offset = $this->page++ * $this->pageSize;
		$objects = call_user_func([$this->aroClass, 'fetch'], "$this->query LIMIT $this->pageSize OFFSET $offset", $this->args);
		$this->fetched($objects);
		return $objects;
	}

	protected function getTotal() : int {
		return call_user_func([$this->aroClass, 'count'], $this->query, ...$this->args);
	}

}
