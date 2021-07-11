<?php

namespace OCA\Deck\Command\Helper;

use JsonSchema\Validator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class ImportAbstract {
	/** @var Command */
	private $command;
	/** @var \stdClass */
	private $settings;

	public function setCommand(Command $command): void {
		$this->command = $command;
	}

	/**
	 * @return Command
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
		return $this->settings->$setting;
	}

	/**
	 * Define a setting
	 *
	 * @param string $settingName
	 * @param mixed $value
	 * @return void
	 */
	public function setSetting($settingName, $value) {
		$this->settings->$settingName = $value;
	}

	protected function validateSettings(InputInterface $input, OutputInterface $output): void {
		$settingFile = $input->getOption('setting');
		if (!is_file($settingFile)) {
			$helper = $this->getCommand()->getHelper('question');
			$question = new Question(
				'Please inform a valid setting json file: ',
				'config.json'
			);
			$question->setValidator(function ($answer) {
				if (!is_file($answer)) {
					throw new \RuntimeException(
						'Setting file not found'
					);
				}
				return $answer;
			});
			$settingFile = $helper->ask($input, $output, $question);
			$input->setOption('setting', $settingFile);
		}

		$this->settings = json_decode(file_get_contents($settingFile));
		$validator = new Validator();
		$validator->validate(
			$this->settings,
			(object)['$ref' => 'file://' . realpath(__DIR__ . '/../fixtures/setting-schema.json')]
		);
		if (!$validator->isValid()) {
			$output->writeln('<error>Invalid setting file</error>');
			$output->writeln(array_map(function ($v) {
				return $v['message'];
			}, $validator->getErrors()));
			$output->writeln('Valid schema:');
			$output->writeln(print_r(file_get_contents(__DIR__ . '/fixtures/setting-schema.json'), true));
			$input->setOption('setting', null);
			$this->validateSettings($input, $output);
		}
	}
}
