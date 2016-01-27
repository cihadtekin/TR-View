<?php
/**
 * @author Cihad Tekin <cihadtekin@gmail.com>
 * @version 0.0.1
 * @throws TR_View_Exception
 */
class TR_View {

	/**
	 * @var array
	 */
	public static $loaders = array();
	/**
	 * TR_View instance cache
	 * @var array
	 */
	public static $instances = array();

	/**
	 * Returns a TR_View instance
	 * 
	 * @param  string  $templateName
	 * @return TR_View
	 */
	public static function factory($templateName, $args = array())
	{
		if ( empty( self::$instances[$templateName] ) ) {
			self::$instances[$templateName] = new self($templateName, $args);
		}
		return self::$instances[$templateName];
	}

	/**
	 * Binds a template loader
	 * 
	 * @param  string   $name   Loader index for storing
	 * @param  function $loader Loader function
	 * @return void
	 */
	public static function bindLoader($name, $loader = NULL)
	{
		self::$loaders[] = $loader;

		if (is_callable($name)) {
			$loader = $name;
			$name = NULL;
		}
		if ($name === NULL) {
			self::$loaders[] = $loader;
		} else {
			self::$loaders[$name] = $loader;
		}
	}

	/**
	 * Removes a loader
	 * 
	 * @param  string $index Loader name
	 * @return void
	 */
	public static function removeLoader($index)
	{
		if ( isset(self::$loaders[$index]) ) {
			unset(self::$loaders[$index]);
		}
	}

	/**
	 * Loads a view
	 * 
	 * @param  string            $blockName
	 * @return string
	 * @throws TR_View_Exception
	 */
	public static function loadView($blockName, $args = array())
	{
		if (empty(static::$loaders)) {
			throw new TR_View_Exception('En az bir tane loader olmalı');
		}

		foreach (static::$loaders as $loader) {
			if (is_callable($loader) && $file = $loader($blockName, $args)) {
				return $file;
			}
		}

		throw new TR_View_Exception('"' . $blockName . '" bloğu yüklenemiyor');
	}

	/**
	 * Loads a block
	 * 
	 * @param  string            $blockName
	 * @param  array             $context   $blockName tag's parent
	 * @return array
	 * @throws TR_View_Exception
	 */
	public static function loadBlock($blockName, $context = NULL, $args = array())
	{
		$splittedName = explode('.', $blockName);

		// Sub block of $context
		if ($context)
		{
			$parentBuild = $context;
			foreach ($splittedName as $name)
			{
				if ( ! empty($parentBuild['children']) && ! empty($parentBuild['children'][$name]) ) {
					$parentBuild = $parentBuild['children'][$name];
				} else {
					$parentBuild = FALSE;
					break;
				}
			}
			if ($parentBuild) {
				return $parentBuild;
			}
		}

		$build = self::factory($splittedName[0], $args)->build;

		if (count($splittedName) === 1) {
			return $build;
		}
		// Remove the first element
		unset($splittedName[0]);
		// Find the requested block
		foreach ( $splittedName as $name )
		{
			if (empty($build['children'][$name])) {
				throw new Exception('Block "' . $name . '" could not find in "'. $blockName .'"');
			}

			$build = $build['children'][$name];
		}
		return $build;
	}


	/**
	 * @var null
	 */
	public $template = NULL;
	/**
	 * @var null
	 */
	public $build = NULL;
	/**
	 * @var array
	 */
	public $args = array();

	function __construct($templateName, $args = array())
	{
		$this->args = $args;
		$this->template = self::loadView($templateName, $this->args);
		$this->build = $this->build( $this->scan($this->template) );
	}


	function __toString()
	{
		return $this->render();
	}


	public function render()
	{
		return $this->compile();
	}

	/**
	 * TOKENIZER
	 */

	private $rootBlock = NULL;

	private $currentBlock = NULL;

	private $line;

	private function scan()
	{
		$len = strlen($this->template);
		$this->line = 1;
		$tagStarted = FALSE;
		$tagContent = '';

		for ($i = 0; $i < $len; $i++)
		{
			$prevChar = empty( $this->template[$i - 1] ) ? NULL : $this->template[$i - 1];
			$nextChar = empty( $this->template[$i + 1] ) ? NULL : $this->template[$i + 1];

			if ($tagStarted)
			{
				// "Close" delimiter
				if ($this->template[$i] === '%' && $prevChar !== '\\' && $nextChar === '}')
				{
					$tagContent = trim($tagContent);

					if ( $tagContent[0] === '/' ) {
						$this->tagClosed( trim($tagContent, '/ ') ); // Current tag has closed
					} elseif ( $tagContent[ strlen($tagContent) - 1 ] === '/' ) {
						$this->tagInclude( trim($tagContent, '/ ') ); // Include a block
					} else { 
						$this->tagOpened($tagContent); // A tag has opened
					}

					// Tag content finished
					$tagStarted = FALSE;
					$i++;
				}
				else
				{
					$tagContent .= $this->template[$i];
				}
			}
			else
			{
				// "Open" delimiter
				if ($this->template[$i] === '{' && $prevChar !== '\\' && $nextChar === '%') {
					$tagStarted = TRUE;
					$tagContent = '';
					$i++;
				} elseif ($this->currentBlock) {
					if (count($this->currentBlock['content'])) {
						$lastKey = count($this->currentBlock['content']) - 1;
						if (is_array($this->currentBlock['content'][ $lastKey ])) {
							$this->currentBlock['content'][] = '';
							$lastKey++;
						}
					} else {
						$this->currentBlock['content'][] = '';
						$lastKey = 0;
					}
					$this->currentBlock['content'][$lastKey] .= $this->template[$i];
				}
			}
			// For exceptions:
			if ($this->template[$i] === "\n") {
				$this->line++;
			}
		}
		// Sonucu döndürmek için root'un yedeğini al
		$result = $this->rootBlock;
		// Referansı kaldır
		unset($this->rootBlock, $this->currentBlock);
		// Reset
		$this->currentBlock = NULL;
		$this->rootBlock = NULL;
		$this->line = NULL;

		return $result;
	}

	private function tagOpened($tag, $empty = FALSE)
	{
		// Yeni block propları
		$block = $this->getTagProps($tag);
		// Eğer şuan bir block içindeysek:
		if ( $this->rootBlock )
		{
			if ($block['nested'])
			{
				$splittedName = explode('.', $block['name']);
				$realName = array_pop($splittedName);

				foreach ($splittedName as $name) {
					if ( ! $name ) {
						throw new TR_View_Exception('Invalid block name ', $this->line);
					}
					$this->tagOpened($name . '>', TRUE);
				}

				$block['name'] = $realName;
			}

			// Yeni bloğun parentına currentBlock'u ekle
			$block['parent'] = &$this->currentBlock;
			// Referansın kalkması için unset et
			unset($this->currentBlock);
			// Mevcut bloğu değiştir
			$this->currentBlock = $block;
			// Parent Bloğun childrenlarına ekle
			$this->currentBlock['parent']['children'][ $this->currentBlock['name'] ] = &$this->currentBlock;
			// Bu bloğu parentın contentine ekle
			if ($empty) {
				$this->currentBlock['empty'] = TRUE;
			} else {
				$this->currentBlock['parent']['content'][] = array(
					'type' => 'children',
					'name' => $this->currentBlock['name']
				);
			}
		}
		// Eğer şuan bir blok içinde değilsek:
		else
		{
			// Mevcut bloğu ata
			$this->currentBlock = $block;
			// En dış blok yap
			$this->rootBlock = &$this->currentBlock;
		}
	}

	private function tagClosed($tag)
	{
		// Kapanış bloğunun propları
		$block = $this->getTagProps($tag);

		if ($block['nested'])
		{
			$splittedName = explode('.', $block['name']);
			$realName = array_pop($splittedName);
			$block['name'] = $realName;
		}

		// If there is no opened block:
		if ( ! $this->currentBlock ) {
			throw new TR_View_Exception('Block "'. $this->currentBlock['name'] .'" hasn\'t opened', $this->line);
		}

		// If closing tag is not for the opened block
		if ( $block['name'] !== $this->currentBlock['name'] ) {
			throw new TR_View_Exception('Block "'. $this->currentBlock['name'] .'" hasn\'t closed', $this->line);
		}

		// Store current
		$block = &$this->currentBlock;
		// Remove reference
		unset($this->currentBlock);
		// Point to parent
		$this->currentBlock = &$block['parent'];

		if (isset($realName)) {
			$splittedName = array_reverse($splittedName);
			foreach ($splittedName as $name) {
				if ( ! $name ) {
					throw new TR_View_Exception('Invalid block name', $this->line);
				}
				$this->tagClosed($name);
			}
		}
	}


	private function tagInclude($tag)
	{
		// Nearest extending block
		if (preg_match('/^(super)(\..+)/', $tag))
		{
			$context = $this->currentBlock;
			$superBlock = NULL;

			do {
				if ($context['extends']) {
					$superBlock = $context['extends'];
					break;
				}
			} while ($context = $context['parent']);

			if ( ! $superBlock ) {
				throw new Exception('Could not find inherited block');
			}

			$tag = preg_replace('/^super/', $superBlock, $tag);
		}

		$this->currentBlock['content'][] = array(
			'type' => 'include',
			'name' => $tag
		);
	}


	private function getTagProps($tag)
	{
		$block = array(
			'name'      => '',    // Required
			'overwrite' => TRUE,
			'extends'   => FALSE,
			'content'   => array(),
			'parent'    => NULL,
			'nested'    => FALSE,
			'empty'     => FALSE,
			'children'  => array(),
		);

		preg_match('/^'.
			'(?<name>[\w._]+)\s*'.
			'(?<operator>>?>)?\s*'.
			'(?<extends>[\w._]+)?\s*'.
			'$/', $tag, $matches);

		if ( ! empty($matches['operator']) ) {
			if ( ! empty($matches['extends']) ) {
				$block['extends'] = $matches['extends'];
				$block['overwrite'] = $matches['operator'] !== '>>';
			} else {
				$block['overwrite'] = $matches['operator'] !== '>';
			}
		}

		$block['name'] = $matches['name'];

		if ( empty($block['name']) ) {
			throw new TR_View_Exception('Invalid block name', $this->line);
		}

		$block['nested'] = preg_match('/\./', $block['name']);

		return $block;
	}


	private function build($child, $parent = NULL)
	{
		// Get parent if this build is extending another
		if ( $child['extends'] ) {
			$parent = TR_View::loadBlock($child['extends'], NULL, $this->args);
		}

		// Build with parent
		if ($parent)
		{
			// Replace this build's content with parent's if overwrite is allowed
			if ( ! $child['overwrite'] ) {
				$child['content'] = $parent['content'];
			}

			// Build each sub block
			foreach ($child['children'] as $name => $childBlock)
			{
				if ( ! empty($parent['children'][$name]) )
				{
					$child['children'][$name] = $this->build(
						$childBlock, $parent['children'][$name]
					);
				}
			}

			// Inherit from parent build
			$child['children'] = array_replace($parent['children'], $child['children']);
		}

		if ( ! empty($child['children']) ) {
			foreach ($child['children'] as $name => $childBlock) {
				$child['children'][$name] = $this->build($childBlock);
			}
		}

		return $child;
	}

	/**
	 * COMPILER
	 */
	
	private function compile($build = NULL)
	{
		if ($build === NULL) {
			$build = $this->build;
		}

		$renderedResult = '';

		for ( $i = 0; $i < count($build['content']); $i++ )
		{
			if ( is_string($build['content'][$i]) )
			{
				$renderedResult .= $build['content'][$i];
			}
			else
			{
				if ($build['content'][$i]['type'] === 'children')
				{
					$renderedResult .= $this->compile(
						$build['children'][ $build['content'][$i]['name'] ]
					);
				}
				elseif ($build['content'][$i]['type'] === 'include')
				{
					$block = TR_View::loadBlock( $build['content'][$i]['name'], $build, $this->args );

					$renderedResult .= $this->compile($block);
				}
			}
		}

		return $renderedResult;
	}
}


class TR_View_Exception extends Exception {

}