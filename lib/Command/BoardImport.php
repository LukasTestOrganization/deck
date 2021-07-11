<?php
/**
 * @copyright Copyright (c) 2021 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Deck\Command;

use OCA\Deck\Command\Helper\ImportInterface;
use OCA\Deck\Command\Helper\TrelloHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class BoardImport extends Command {
	/** @var string */
	private $system;
	private $allowedSystems = ['trello'];
	/** @var TrelloHelper */
	private $trelloHelper;
	/**
	 * Data object created from settings JSON
	 *
	 * @var \StdClass
	 */
	public $settings;

	public function __construct(
		TrelloHelper $trelloHelper
	) {
		parent::__construct();
		$this->trelloHelper = $trelloHelper;
	}

	/**
	 * @return void
	 */
	protected function configure() {
		$this
			->setName('deck:import')
			->setDescription('Import data')
			->addOption(
				'system',
				null,
				InputOption::VALUE_REQUIRED,
				'Source system for import. Available options: trello.',
				'trello'
			)
			->addOption(
				'setting',
				null,
				InputOption::VALUE_REQUIRED,
				'Configuration json file.',
				'config.json'
			)
			->addOption(
				'data',
				null,
				InputOption::VALUE_REQUIRED,
				'Data file to import.',
				'data.json'
			)
		;
	}

	/**
	 * @inheritDoc
	 *
	 * @return void
	 */
	protected function interact(InputInterface $input, OutputInterface $output) {
		$this->validateSystem($input, $output);
		$this->getSystem()
			->validate($input, $output);
	}

	private function setSystem(string $system): void {
		$this->system = $system;
	}

	/**
	 * @return ImportInterface
	 */
	private function getSystem() {
		$helper = $this->{$this->system . 'Helper'};
		$helper->setCommand($this);
		return $helper;
	}

	/**
	 * @return void
	 */
	private function validateSystem(InputInterface $input, OutputInterface $output) {
		$system = $input->getOption('system');
		if (in_array($system, $this->allowedSystems)) {
			$this->setSystem($system);
			return;
		}
		$helper = $this->getHelper('question');
		$question = new ChoiceQuestion(
			'Please inform a source system',
			$this->allowedSystems,
			0
		);
		$question->setErrorMessage('System %s is invalid.');
		$system = $helper->ask($input, $output, $question);
		$input->setOption('system', $system);
		$this->setSystem($system);
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$this->getSystem()
			->import($input, $output);
		$output->writeln('Done!');
		return 0;
	}
}
