<?php

namespace Framework\Aro;


/**
 * @template TValue of ActiveRecordObject
 * @extends ActiveRecordGenerator<TValue>
 *
 * @phpstan-import-type Args from \db_generic
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
