<?php

namespace Framework\Aro;

use Countable;
use IteratorAggregate;
use Traversable;

class ActiveRecordGenerator implements IteratorAggregate, Countable {

	protected $aroClass;
	protected $query;
	protected $args = [];

	protected $afterFetch;
	protected $eagerLoad = [];
	protected $pageSize;
	protected $limit;

	protected $page = 0;
	protected $total;

	public function __construct( string $aroClass, string $query, array $args = [], array $options = [] ) {
		$this->aroClass = $aroClass;
		$this->query = $query;
		$this->args = $args;

		$this->pager($aroClass::ITERATOR_PAGE_SIZE);
		$this->setOptions($options);
	}

	protected function setOptions( array $options ) : static {
		if ( isset($options['page_size']) ) $this->pager($options['page_size']);

		if ( isset($options['limit']) ) $this->limit($options['limit']);

		if ( isset($options['eager_load']) ) $this->eagerLoad($options['eager_load']);
		elseif ( isset($options['after_fetch']) ) $this->afterFetch($options['after_fetch']);

		return $this;
	}

	public function pager( int $num ) : static {
		$this->pageSize = $num;
		return $this;
	}

	public function limit( ?int $num ) : static {
		$this->limit = $num;
		return $this;
	}

	public function eagerLoad( array $relationships ) : static {
		$this->eagerLoad = $relationships;
		return $this;
	}

	public function eagerLoadMore( array $relationships ) : static {
		$this->eagerLoad = array_merge($this->eagerLoad, $relationships);
		return $this;
	}

	public function afterFetch( callable $callable ) : static {
		$this->afterFetch = $callable;
		return $this;
	}

	protected function fetch() : array {
		$offset = $this->page++ * $this->pageSize;
		$objects = call_user_func([$this->aroClass, 'byQuery'], "$this->query LIMIT $this->pageSize OFFSET $offset", $this->args);
		$this->fetched($objects);
		return $objects;
	}

	protected function fetched( array $objects ) : void {
		if ( !count($objects) ) return;

		if ( count($this->eagerLoad) ) {
			$object = reset($objects);
			call_user_func([get_class($object), 'eagers'], $objects, $this->eagerLoad);
		}

		if ( $this->afterFetch ) {
			call_user_func($this->afterFetch, $objects);
		}
	}

	public function get( int $length ) : array {
		$objects = [];
		foreach ( $this as $object ) {
			$objects[] = $object;
			if ( count($objects) >= $length ) {
				return $objects;
			}
		}

		return $objects;
	}

	public function getIterator() : Traversable {
		$objects = $this->fetch();
		$done = 0;
		while ( count($objects) ) {
			foreach ( $objects as $object ) {
				yield $object;
				$done++;

				if ( $this->limit && $done >= $this->limit ) {
					return;
				}
			}

			$objects = count($objects) == $this->pageSize ? $this->fetch() : [];
		}
	}

	public function count() : int {
		return $this->limit ? min($this->limit, $this->total()) : $this->total();
	}

	public function total() : int {
		if ( $this->total === null ) {
			$this->total = $this->getTotal();
		}
		return $this->total;
	}

	protected function getTotal() : int {
		$db = call_user_func([$this->aroClass, 'getDbObject']);
		$query = $db->prepAndReplaceQMarks($this->query, $this->args);
		return $db->count_rows($query);
	}

}
