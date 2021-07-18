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

namespace OCA\Deck\Service;

use OCA\Deck\Exceptions\ConflictException;
use OCA\Deck\NotFoundException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

class BoardImportCommandService extends BoardImportService {
	/**
	 * @var Command
	 * @psalm-suppress PropertyNotSetInConstructor
	 */
	private $command;
	/**
	 * @var InputInterface
	 * @psalm-suppress PropertyNotSetInConstructor
	 */
	private $input;
	/**
	 * @var OutputInterface
	 * @psalm-suppress PropertyNotSetInConstructor
	 */
	private $output;

	public function setCommand(Command $command): self {
		$this->command = $command;
		return $this;
	}

	public function getCommand(): Command {
		return $this->command;
	}

	public function setInput(InputInterface $input): self {
		$this->input = $input;
		return $this;
	}

	public function getInput(): InputInterface {
		return $this->input;
	}

	public function setOutput(OutputInterface $output): self {
		$this->output = $output;
		return $this;
	}

	public function getOutput(): OutputInterface {
		return $this->output;
	}

	protected function validateConfig(): void {
		try {
			$config = $this->getInput()->getOption('config');
			if (is_string($config)) {
				if (!is_file($config)) {
					throw new NotFoundException('Please inform a valid config json file');
				}
				$config = json_decode(file_get_contents($config));
				if (!$config instanceof \stdClass) {
					throw new NotFoundException('Please inform a valid config json file');
				}
				$this->setConfigInstance($config);
			}
			parent::validateConfig();
			return;
		} catch (NotFoundException $e) {
			$helper = $this->getCommand()->getHelper('question');
			$question = new Question(
				'Please inform a valid config json file: ',
				'config.json'
			);
			$question->setValidator(function (string $answer) {
				if (!is_file($answer)) {
					throw new \RuntimeException(
						'config file not found'
					);
				}
				return $answer;
			});
			$configFile = $helper->ask($this->getInput(), $this->getOutput(), $question);
			$config = $this->getInput()->setOption('config', $configFile);
		} catch (ConflictException $e) {
			$this->getOutput()->writeln('<error>Invalid config file</error>');
			$this->getOutput()->writeln(array_map(function (array $v): string {
				return $v['message'];
			}, $e->getData()));
			$this->getOutput()->writeln('Valid schema:');
			$schemaPath = __DIR__ . '/fixtures/config-' . $this->getSystem() . '-schema.json';
			$this->getOutput()->writeln(print_r(file_get_contents($schemaPath), true));
			$this->getInput()->setOption('config', null);
		}
		parent::validateConfig();
		return;
	}

	public function validateSystem(): void {
		try {
			parent::validateSystem();
			return;
		} catch (\Throwable $th) {
		}
		$helper = $this->getCommand()->getHelper('question');
		$question = new ChoiceQuestion(
			'Please inform a source system',
			$this->getAllowedImportSystems(),
			0
		);
		$question->setErrorMessage('System %s is invalid.');
		$system = $helper->ask($this->getInput(), $this->getOutput(), $question);
		$this->getInput()->setOption('system', $system);
		$this->setSystem($system);
		return;
	}

	protected function validateData(): void {
		$data = $this->getInput()->getOption('data');
		if (is_string($data)) {
			$data = json_decode(file_get_contents($data));
			if ($data instanceof \stdClass) {
				$this->setData($data);
				return;
			}
		}
		$helper = $this->getCommand()->getHelper('question');
		$question = new Question(
			'Please inform a valid data json file: ',
			'data.json'
		);
		$question->setValidator(function (string $answer) {
			if (!is_file($answer)) {
				throw new \RuntimeException(
					'Data file not found'
				);
			}
			return $answer;
		});
		$data = $helper->ask($this->getInput(), $this->getOutput(), $question);
		$this->getInput()->setOption('data', $data);
		$this->validateData();
	}

	public function bootstrap(): void {
		$this->setSystem($this->getInput()->getOption('system'));
		parent::bootstrap();
	}

	public function import(): void {
		$this->getOutput()->writeln('Starting import...');
		$this->bootstrap();
		$this->getOutput()->writeln('Importing board...');
		$this->importBoard();
		$this->getOutput()->writeln('Assign users to board...');
		$this->importAcl();
		$this->getOutput()->writeln('Importing labels...');
		$this->importLabels();
		$this->getOutput()->writeln('Importing stacks...');
		$this->importStacks();
		$this->getOutput()->writeln('Importing cards...');
		$this->importCards();
		$this->getOutput()->writeln('Assign cards to labels...');
		$this->assignCardsToLabels();
		$this->getOutput()->writeln('Iporting comments...');
		$this->importComments();
		$this->getOutput()->writeln('Iporting participants...');
		$this->importParticipants();
	}
}
