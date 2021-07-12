<?php

namespace OCA\Deck\Command\ImportHelper;

use Symfony\Component\Console\Command\Command;

class ImportAbstract {
	/** @var Command */
	private $command;
	/**
	 * Data object created from config JSON
	 *
	 * @var \StdClass
	 */
	public $config;

	/**
	 * @inheritDoc
	 */
	public function setCommand(Command $command): void {
		$this->command = $command;
	}

	/**
	 * @inheritDoc
	 */
	public function getCommand() {
		return $this->command;
	}

	/**
	 * @inheritDoc
	 */
	public function setConfig($property, $value): void {
		$this->config->$property = $value;
	}

	/**
	 * @inheritDoc
	 */
	public function getConfig($property) {
		if (!is_object($this->config)) {
			return;
		}
		if (!property_exists($this->config, $property)) {
			return;
		}
		return $this->config->$property;
	}
}
