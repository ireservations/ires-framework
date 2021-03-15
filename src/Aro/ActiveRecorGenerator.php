<?php

namespace Framework\Aro;

use Countable;
use IteratorAggregate;

class ActiveRecorGenerator implements IteratorAggregate, Countable {

	protected $aroClass;
	protected $query;
	protected $args = [];

	protected $afterFetch;
	protected $pageSize;

	protected $page = 0;
	protected $count;

	public function __construct( string $aroClass, string $query, array $args = [], array $options = [] ) {
		$this->aroClass = $aroClass;
		$this->query = $query;
		$this->args = $args;

		$this->pager($aroClass::ITERATOR_PAGE_SIZE);
		$this->setOptions($options);
	}

	protected function setOptions( array $options ) {
		if ( isset($options['page_size']) ) $this->pager($options['page_size']);
		elseif ( isset($options['limit']) ) $this->pager($options['limit']);

		if ( isset($options['after_fetch']) ) $this->with($options['after_fetch']);

		return $this;
	}

	public function pager( int $num ) {
		$this->pageSize = $num;
		return $this;
	}

	public function with( callable $callable ) {
		$this->afterFetch = $callable;
		return $this;
	}

	protected function fetch() {
		$offset = $this->page++ * $this->pageSize;
		$objects = call_user_func([$this->aroClass, 'byQuery'], "$this->query LIMIT $this->pageSize OFFSET $offset", $this->args);
		if ( $this->afterFetch && count($objects) ) {
			call_user_func($this->afterFetch, $objects);
		}
		return $objects;
	}

	public function get( int $length ) {
		$objects = [];
		foreach ( $this as $object ) {
			$objects[] = $object;
			if ( count($objects) >= $length ) {
				return $objects;
			}
		}

		return $objects;
	}

	public function getIterator() {
		$objects = $this->fetch();
		while ( count($objects) ) {
			foreach ( $objects as $object ) {
				yield $object;
			}

			$objects = count($objects) == $this->pageSize ? $this->fetch() : [];
		}
	}

	public function count() {
		if ( $this->count === null ) {
			$db = call_user_func([$this->aroClass, 'getDbObject']);
			$query = $db->prepAndReplaceQMarks($this->query, $this->args);
			$this->count = $db->count_rows($query);
		}

		return $this->count;
	}

}
