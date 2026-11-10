<?php

namespace Framework\Tpl;

use Smarty\BlockHandler\BlockHandlerInterface;
use Smarty\BlockHandler\BlockPluginWrapper;
use Smarty\Compile\CompilerInterface;
use Smarty\Compile\Tag\BCPluginWrapper as TagPluginWrapper;
use Smarty\Extension\Base;
use Smarty\FunctionHandler\BCPluginWrapper as FunctionPluginWrapper;
use Smarty\FunctionHandler\FunctionHandlerInterface;

class PluginsDirExtension extends Base {

	/** @var array<string, ?callable-string> */
	protected array $plugins = [];

	/**
	 * @param list<string> $dirs
	 */
	public function __construct(
		protected array $dirs,
	) {}

	public function getTagCompiler( string $tag ) : ?CompilerInterface {
		$function = $this->findPlugin('compiler', $tag);
		if ( $function === null ) {
			return null;
		}

		return new TagPluginWrapper($function);
	}

	public function getFunctionHandler( string $functionName ) : ?FunctionHandlerInterface {
		$function = $this->findPlugin('function', $functionName);
		if ( $function === null ) {
			return null;
		}

		return new FunctionPluginWrapper($function);
	}

	public function getBlockHandler( string $blockTagName ) : ?BlockHandlerInterface {
		$function = $this->findPlugin('block', $blockTagName);
		if ( $function === null ) {
			return null;
		}

		return new BlockPluginWrapper($function);
	}

	public function getModifierCallback( string $modifierName ) {
		return $this->findPlugin('modifier', $modifierName);
	}

	/**
	 * @return ?callable-string
	 */
	protected function findPlugin( string $type, string $name ) : ?string {
		$function = "smarty_{$type}_{$name}";
		if ( !array_key_exists($function, $this->plugins) ) {
			$this->plugins[$function] = $this->loadPlugin($type, $name, $function);
		}

		return $this->plugins[$function];
	}

	/**
	 * @return ?callable-string
	 */
	protected function loadPlugin( string $type, string $name, string $function ) : ?string {
		$fileNames = [$name];
		if ( str_ends_with($name, 'close') ) {
			$fileNames[] = substr($name, 0, -5);
		}

		foreach ( $fileNames as $fileName ) {
			$file = $this->findFile("$type.$fileName.php");
			if ( $file !== null ) {
				require_once $file;
			}
		}

		return function_exists($function) ? $function : null;
	}

	protected function findFile( string $fileName ) : ?string {
		foreach ( $this->dirs as $dir ) {
			$file = "$dir/$fileName";
			if ( file_exists($file) ) {
				return $file;
			}
		}

		return null;
	}

}
