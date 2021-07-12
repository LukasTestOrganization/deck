<?php

namespace OCA\Deck\Service;

abstract class AImportService {
	/**
	 * Data object created from config JSON
	 *
	 * @var \stdClass
	 */
	public $config;

	public function setConfigInstance(\stdClass $config) {
		$this->config = $config;
	}

	/**
	 * Define a config
	 *
	 * @param string $configName
	 * @param mixed $value
	 * @return void
	 */
	public function setConfig(string $configName, $value): void {
		if (!$this->config) {
			$this->setConfigInstance(new \stdClass);
		}
		$this->config->$configName = $value;
	}

	/**
	 * Get a config
	 *
	 * @param string $configName config name
	 * @return mixed
	 */
	public function getConfig(string $configName = null) {
		if (!is_object($this->config)) {
			return;
		}
		if (!$configName) {
			return $this->config;
		}
		if (!property_exists($this->config, $configName)) {
			return;
		}
		return $this->config->$configName;
	}
}
