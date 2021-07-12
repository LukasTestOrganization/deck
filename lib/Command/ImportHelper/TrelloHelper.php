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

namespace OCA\Deck\Command\ImportHelper;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class TrelloHelper extends AImport {
	public function validate(InputInterface $input, OutputInterface $output): void {
		$this->validateData($input, $output);
		$this->trelloImportService->validateOwner();
		$this->trelloImportService->validateUsers();
	}

	public function import(InputInterface $input, OutputInterface $output): void {
		$this->trelloImportService->setUserId();
		$output->writeln('Importing board...');
		$this->trelloImportService->importBoard();
		$output->writeln('Assign users to board...');
		$this->trelloImportService->assignUsersToBoard();
		$output->writeln('Importing labels...');
		$this->trelloImportService->importLabels();
		$output->writeln('Importing stacks...');
		$this->trelloImportService->importStacks();
		$output->writeln('Importing cards...');
		$this->trelloImportService->importCards();
	}

	private function validateData(InputInterface $input, OutputInterface $output): void {
		$filename = $input->getOption('data');
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
			$data = $helper->ask($input, $output, $question);
			$input->setOption('data', $data);
		}
		$this->trelloImportService->setData(json_decode(file_get_contents($filename)));
		if (!$this->trelloImportService->getData()) {
			$output->writeln('<error>Is not a json file: ' . $filename . '</error>');
			$this->validateData($input, $output);
		}
	}
}
