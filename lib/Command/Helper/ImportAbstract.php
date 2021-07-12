<?php

namespace OCA\Deck\Command\Helper;

use Symfony\Component\Console\Command\Command;

class ImportAbstract {
	/** @var Command */
	private $command;

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
	public function setSetting($settingName, $value): void {
		$this->getCommand()->settings->$settingName = $value;
	}

	/**
	 * @inheritDoc
	 */
	public function getSetting($setting) {
		if (!is_object($this->getCommand()->settings)) {
			return;
		}
		if (!property_exists($this->getCommand()->settings, $setting)) {
			return;
		}
		return $this->getCommand()->settings->$setting;
	}
}
