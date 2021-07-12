<?php

namespace OCA\Deck\Command\Helper;

use OCA\Deck\Command\BoardImport;
use Symfony\Component\Console\Command\Command;

class ImportAbstract {
	/** @var Command */
	private $command;

	public function setCommand(Command $command): void {
		$this->command = $command;
	}

	/**
	 * @return BoardImport
	 */
	public function getCommand() {
		return $this->command;
	}

	/**
	 * Get a setting
	 *
	 * @param string $setting Setting name
	 * @return mixed
	 */
	public function getSetting($setting) {
		return $this->getCommand()->settings->$setting;
	}

	/**
	 * Define a setting
	 *
	 * @param string $settingName
	 * @param mixed $value
	 * @return void
	 */
	public function setSetting($settingName, $value) {
		$this->getCommand()->settings->$settingName = $value;
	}
}
