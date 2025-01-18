<?php

namespace Framework\Annotations;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Post extends Route {

	public string $method = 'post';

}
