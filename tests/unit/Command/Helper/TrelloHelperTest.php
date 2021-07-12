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

use OCA\Deck\Command\ImportHelper\TrelloHelper;
use OCA\Deck\Service\TrelloImportService;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TrelloHelperTest extends \Test\TestCase {
	/** @var TrelloImportService */
	private $trelloImportService;
	/** @var TrelloHelper */
	private $trelloHelper;
	public function setUp(): void {
		parent::setUp();
		$this->trelloImportService = $this->createMock(TrelloImportService::class);
		$this->trelloHelper = new TrelloHelper(
			$this->trelloImportService
		);
		$questionHelper = new QuestionHelper();
		$command = new BoardImport($this->trelloHelper);
		$command->setHelperSet(
			new HelperSet([
				$questionHelper
			])
		);
		$this->trelloHelper->setCommand($command);
	}

	public function testImportWithSuccess() {
		$input = $this->createMock(InputInterface::class);

		$input->method('getOption')
			->withConsecutive(
				[$this->equalTo('system')],
				[$this->equalTo('config')]
			)
			->will($this->returnValueMap([
				['system', 'trello'],
				['config', __DIR__ . '/../fixtures/config-trello.json']
			]));
		$output = $this->createMock(OutputInterface::class);

		$this->invokePrivate($this->trelloHelper->getCommand(), 'validateSystem', [$input, $output]);
		$this->invokePrivate($this->trelloHelper->getCommand(), 'validateConfig', [$input, $output]);
		$actual = $this->trelloHelper->import($input, $output);
		$this->assertNull($actual);
	}
}
