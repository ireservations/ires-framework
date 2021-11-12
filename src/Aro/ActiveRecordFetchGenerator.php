<?php

namespace Framework\Aro;

class ActiveRecordFetchGenerator extends ActiveRecordGenerator {

	protected function fetch() {
		$offset = $this->page++ * $this->pageSize;
		$objects = call_user_func([$this->aroClass, 'fetch'], "$this->query LIMIT $this->pageSize OFFSET $offset", $this->args);
		if ( $this->afterFetch && count($objects) ) {
			call_user_func($this->afterFetch, $objects);
		}
		return $objects;
	}

	public function count() {
		if ( $this->count === null ) {
			$this->count = call_user_func([$this->aroClass, 'count'], $this->query, ...$this->args);
		}

		return $this->count;
	}

}
