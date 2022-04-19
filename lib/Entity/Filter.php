<?php
namespace Coercive\Utility\Router\Entity;

use Exception;

/**
 * Class Filter
 *
 * @package Coercive\Utility\Router\Entity
 * @link https://github.com/Coercive/Router
 *
 * @author Anthony Moral <contact@coercive.fr>
 * @copyright 2022
 * @license MIT
 */
class Filter
{
	const TYPES = [
		'boolean', 'bool',
		'integer', 'int',
		'float', 'double',
		'string',
	];

	private array $filters = [
		'methods' => [],
		'options' => [],
		'lang' => ''
	];

	/**
	 * List of access method to filter
	 *
	 * @param array $list
	 * @return $this
	 */
	public function methods(array $list): Filter
	{
		foreach ($list as $method) {
			$m = strtoupper($method);
			$this->filters['methods'][$m] = $m;
		}
		return $this;
	}

	/**
	 * Add filter on options field
	 *
	 * @param string $label
	 * @param string $value
	 * @param string $type [optional]
	 * @return $this
	 * @throws Exception
	 */
	public function options(string $label, string $value, string $type = ''): Filter
	{
		$type = $type ?: 'string';
		if(!in_array($type, self::TYPES)) {
			throw new Exception('Error: $type "' . $type . '" must be in > ' . implode(', ', self::TYPES));
		}
		settype($value, $type);
		$this->filters['options'][$label] = [
			'label' => $label,
			'value' => $value,
			'type' => $type
		];
		return $this;
	}

	/**
	 * The lang used to init Route
	 * If not set, the lang of the Router current Route will be used.
	 *
	 * @param string $lang
	 * @return $this
	 */
	public function lang(string $lang): Filter
	{
		$this->filters['lang'] = $lang;
		return $this;
	}

	/**
	 * @return array
	 */
	public function export(): array
	{
		return $this->filters;
	}
}