<?php

namespace OCA\Deck\Service;

use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use OCA\Deck\Command\BoardImport;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

class BoardImportCommandService extends BoardImportService {
	/** @var Command */
	private $command;
	/** @var InputInterface */
	private $input;
	/** @var OutputInterface */
	private $output;
	/**
	 * Data object created from config JSON
	 *
	 * @var \StdClass
	 */
	public $config;

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

	public function setInput($input): self {
		$this->input = $input;
		return $this;
	}

	public function getInput(): InputInterface {
		return $this->input;
	}

	public function setOutput($output): self {
		$this->output = $output;
		return $this;
	}

	public function getOutput(): OutputInterface {
		return $this->output;
	}

	public function validate(): self {
		$this->validateSystem();
		$this->validateConfig();
		$this->validateData();
		return $this;
	}

	private function validateConfig(): void {
		$configFile = $this->getInput()->getOption('config');
		if (!is_file($configFile)) {
			$helper = $this->getCommand()->getHelper('question');
			$question = new Question(
				'Please inform a valid config json file: ',
				'config.json'
			);
			$question->setValidator(function ($answer) {
				if (!is_file($answer)) {
					throw new \RuntimeException(
						'config file not found'
					);
				}
				return $answer;
			});
			$configFile = $helper->ask($this->getInput(), $this->getOutput(), $question);
			$this->getInput()->setOption('config', $configFile);
		}

		$config = json_decode(file_get_contents($configFile));
		$system = $this->getSystem();
		$schemaPath = __DIR__ . '/fixtures/config-' . $system . '-schema.json';
		$validator = new Validator();
		$validator->validate(
			$config,
			(object)['$ref' => 'file://' . realpath($schemaPath)],
			Constraint::CHECK_MODE_APPLY_DEFAULTS
		);
		if (!$validator->isValid()) {
			$this->getOutput()->writeln('<error>Invalid config file</error>');
			$this->getOutput()->writeln(array_map(function ($v) {
				return $v['message'];
			}, $validator->getErrors()));
			$this->getOutput()->writeln('Valid schema:');
			$this->getOutput()->writeln(print_r(file_get_contents($schemaPath), true));
			$this->getInput()->setOption('config', null);
			$this->validateConfig($this->getInput(), $this->getOutput());
		}
		$this->setConfigInstance($config);
		$this->validateOwner();
	}

	/**
	 * @return void
	 */
	private function validateSystem(): self {
		$system = $this->getInput()->getOption('system');
		if (in_array($system, $this->getAllowedImportSystems())) {
			return $this->setSystem($system);
		}
		$helper = $this->getCommand()->getHelper('question');
		$question = new ChoiceQuestion(
			'Please inform a source system',
			$this->allowedSystems,
			0
		);
		$question->setErrorMessage('System %s is invalid.');
		$system = $helper->ask($this->getInput(), $this->getOutput(), $question);
		$this->getInput()->setOption('system', $system);
		return $this->setSystem($system);
	}

	private function validateData(): self {
		$filename = $this->getInput()->getOption('data');
		if (!is_file($filename)) {
			$helper = $this->getCommand()->getHelper('question');
			$question = new Question(
				'Please inform a valid data json file: ',
				'data.json'
			);
			$question->setValidator(function ($answer) {
				if (!is_file($answer)) {
					throw new \RuntimeException(
						'Data file not found'
					);
				}
				return $answer;
			});
			$data = $helper->ask($this->getInput(), $this->getOutput(), $question);
			$this->getInput()->setOption('data', $data);
		}
		$this->setData(json_decode(file_get_contents($filename)));
		if (!$this->getData()) {
			$this->getOutput()->writeln('<error>Is not a json file: ' . $filename . '</error>');
			$this->validateData($this->getInput(), $this->getOutput());
		}
		$this->validateUsers();
		return $this;
	}

	public function import(): void {
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
