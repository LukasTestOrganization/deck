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

use OCA\Deck\Service\BoardImportCommandService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class BoardImport extends Command {
	/** @var boardImportCommandService */
	private $boardImportCommandService;

	public function __construct(
		BoardImportCommandService $boardImportCommandService
	) {
		$this->boardImportCommandService = $boardImportCommandService;
		parent::__construct();
	}

	/**
	 * @return void
	 */
	protected function configure() {
		$allowedSystems = $this->boardImportCommandService->getAllowedImportSystems();
		$this
			->setName('deck:import')
			->setDescription('Import data')
			->addOption(
				'system',
				null,
				InputOption::VALUE_REQUIRED,
				'Source system for import. Available options: ' . implode(', ', $allowedSystems) . '.',
				'trello'
			)
			->addOption(
				'config',
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
		$this->boardImportCommandService
			->setInput($input)
			->setOutput($output)
			->setSystem($input->getOption('system'))
			->setConfigInstance($input->getOption('config'))
			->validate();
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$this
			->boardImportCommandService
			->setInput($input)
			->setOutput($output)
			->import();
		$output->writeln('Done!');
		return 0;
	}
}
