<?php

namespace Framework\Aro;

class ActiveRecordFetchGenerator extends ActiveRecordGenerator {

	protected function fetch() {
		$offset = $this->page++ * $this->pageSize;
		$objects = call_user_func([$this->aroClass, 'fetch'], "$this->query LIMIT $this->pageSize OFFSET $offset", $this->args);
		$this->fetched($objects);
		return $objects;
	}

	protected function getTotal() {
		return call_user_func([$this->aroClass, 'count'], $this->query, ...$this->args);
	}

}
