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

use OCA\Deck\Command\BoardImport;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

interface ImportInterface {
	/**
	 * Validate data before run execute method
	 *
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return void
	 */
	public function validate(InputInterface $input, OutputInterface $output): void;

	/**
	 * Run import
	 *
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return void
	 */
	public function import(InputInterface $input, OutputInterface $output): void;

	/**
	 * Define Command instance
	 *
	 * @param Command $command
	 * @return void
	 */
	public function setCommand(Command $command): void;

	/**
	 * @return BoardImport
	 */
	public function getCommand();

	/**
	 * Define a config
	 *
	 * @param string $configName
	 * @param mixed $value
	 * @return void
	 */
	public function setConfig(string $configName, $value): void;

	/**
	 * Get a config
	 *
	 * @param string $configName config name
	 * @return mixed
	 */
	public function getConfig($configName);
}
