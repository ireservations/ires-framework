<?php

namespace Framework\Annotations;

interface ControllerAnnotationInterface {

	public function controllerName() : string;

	public function controllerIsMultiple() : bool;

	public function controllerSingleValue() : mixed;

}
