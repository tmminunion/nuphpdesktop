<?php

namespace SimpleTemplateEngine;

class Template implements \ArrayAccess
{
	protected $templatePath;
	protected $environment;
	protected $content;
	private $stack = array();
	protected $blocks = array();
	protected $extends = null;

	public function __construct($path = null)
	{
		$this->templatePath = $path;
		$this->environment = null;
		$this->content = new Block();
	}

	public static function withEnvironment(Environment $environment, $path)
	{
		$obj = ($path === null) ? new self(null) : new self($environment->getTemplatePath($path));
		$obj->setEnvironment($environment);
		return $obj;
	}

	public function extend($path)
	{
		if ($path === null) {
			return;
		} else if ($this->environment !== null) {
			if ($this->templatePath == $this->environment->getTemplatePath($path))
				return;
			$this->extends = Template::withEnvironment($this->environment, $path);
		} else if ($this->templatePath != $path) {
			$this->extends = new Template($path);
		}
	}

	public function block($name = null, $value = null)
	{
		if ($value !== null) {
			if ($name !== null) {
				$block = new Block($name);
				$block->setContent($value);
				$this->blocks[$name] = $block;
			} else {
				throw new \LogicException(sprintf("You are assigning a value of %s to a block with no name!", $value));
			}
			return;
		}

		if (!empty($this->stack)) {
			$content = ob_get_contents();
			foreach ($this->stack as &$b)
				$b->append($content);
		}

		ob_start();
		$block = new Block($name);
		array_push($this->stack, $block);
	}

	public function endblock(\Closure $filter = null)
	{
		$content = ob_get_clean();
		foreach ($this->stack as &$b)
			$b->append($content);
		$block = array_pop($this->stack);

		if ($filter !== null) {
			$block->setContent($filter($block->getContent()));
		}

		if (($name = $block->getName()) != null)
			$this->blocks[$block->getName()] = $block;
		return $block;
	}

	public function getBlocks()
	{
		if (!$this['content'])
			$this['content'] = $this->content;
		else
			$this['content'] = $this['content'] . $this->content;
		return $this->blocks;
	}

	public function setBlocks(array $blocks)
	{
		$this->blocks = $blocks;
	}

	public function render(array $variables = array())
	{
		if ($this->templatePath !== null) {
			$_file = $this->templatePath;

			if (!file_exists($_file))
				throw new \InvalidArgumentException(sprintf("Could not render. The file %s could not be found", $_file));

			extract($variables, EXTR_SKIP);
			ob_start();
			require($_file);
			$this->content->append(ob_get_clean());
		}

		if ($this->extends !== null) {
			$this->extends->setBlocks($this->getBlocks());
			$content = (string)$this->extends->render();
			return $content;
		}

		return (string)$this->content;
	}

	public function setEnvironment(Environment $environment)
	{
		$this->environment = $environment;
	}

	public function __isset($id)
	{
		return isset($this->environment->$id);
	}

	public function __get($id)
	{
		return $this->environment->$id;
	}

	public function __set($id, $value)
	{
		$this->environment->$id = $value;
	}

	public function offsetExists($offset): bool
	{
		return isset($this->blocks[$offset]);
	}

	public function offsetGet($offset): mixed
	{
		return $this->blocks[$offset] ?? false;
	}

	public function offsetSet($offset, $value): void
	{
		if (isset($this->blocks[$offset])) {
			$this->blocks[$offset]->setContent((string)$value);
		} else {
			$block = new Block($offset);
			$block->setContent((string)$value);
			$this->blocks[$offset] = $block;
		}
	}

	public function offsetUnset($offset): void
	{
		unset($this->blocks[$offset]);
	}
}
