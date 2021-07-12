<?php

namespace OCA\Deck\Command\ImportHelper;

use OCA\Deck\Command\BoardImport;
use OCA\Deck\Service\AImportService;
use OCA\Deck\Service\TrelloImportService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AImport extends AImportService {
	/** @var TrelloImportService */
	protected $trelloImportService;
	/** @var Command */
	private $command;
	/**
	 * Data object created from config JSON
	 *
	 * @var \StdClass
	 */
	public $config;

	public function __construct(
		TrelloImportService $trelloImportService
	) {
		$this->trelloImportService = $trelloImportService;
	}

	abstract public function validate(InputInterface $input, OutputInterface $output): void;

	abstract public function import(InputInterface $input, OutputInterface $output): void;

	/**
	 * Define Command instance
	 *
	 * @param Command $command
	 * @return void
	 */
	public function setCommand(Command $command): void {
		$this->command = $command;
	}

	/**
	 * @return BoardImport
	 */
	public function getCommand() {
		return $this->command;
	}

	public function setConfigInstance(\stdClass $config) {
		$this->trelloImportService->setConfigInstance($config);
	}

	/**
	 * Define a config
	 *
	 * @param string $configName
	 * @param mixed $value
	 * @return void
	 */
	public function setConfig(string $configName, $value): void {
		$this->trelloImportService->setConfig($configName, $value);
	}

	/**
	 * Get a config
	 *
	 * @param string $configName config name
	 * @return mixed
	 */
	public function getConfig(string $configName = null) {
		return $this->trelloImportService->getConfig($configName);
	}
}
