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
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BoardImportTest extends \Test\TestCase {
	/** @var BoardImportCommandService */
	private $boardImportCommandService;
	/** @var BoardImport */
	private $boardImport;

	public function setUp(): void {
		parent::setUp();
		$this->boardImportCommandService = $this->createMock(boardImportCommandService::class);
		$this->boardImport = new BoardImport(
			$this->boardImportCommandService
		);
		$questionHelper = new QuestionHelper();
		$this->boardImport->setHelperSet(
			new HelperSet([
				$questionHelper
			])
		);
	}

	public function testExecuteWithSuccess() {
		$input = $this->createMock(InputInterface::class);
		$input
			->method('getOption')
			->withConsecutive(
				['system'],
				['config']
			)
			->will($this->returnValueMap([
				['system', 'trello'],
				['config', null]
			]));

		$output = $this->createMock(OutputInterface::class);

		$output
			->expects($this->once())
			->method('writeLn')
			->with('Done!');

		$actual = $this->invokePrivate($this->boardImport, 'interact', [$input, $output]);
		$this->assertNull($actual);
		$actual = $this->invokePrivate($this->boardImport, 'execute', [$input, $output]);
		$this->assertEquals(0, $actual);
	}
}
