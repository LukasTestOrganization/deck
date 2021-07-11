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

use OCA\Deck\Command\Helper\TrelloHelper;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\LabelService;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IUserManager;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TrelloHelperTest extends \Test\TestCase {
	/** @var BoardService */
	private $boardService;
	/** @var LabelService */
	private $labelService;
	/** @var StackMapper */
	private $stackMapper;
	/** @var CardMapper */
	private $cardMapper;
	/** @var IDBConnection */
	private $connection;
	/** @var IUserManager */
	private $userManager;
	/** @var TrelloHelper */
	private $trelloHelper;
	/** @var IL10N */
	private $l10n;
	public function setUp(): void {
		parent::setUp();
		$this->boardService = $this->createMock(BoardService::class);
		$this->labelService = $this->createMock(LabelService::class);
		$this->stackMapper = $this->createMock(StackMapper::class);
		$this->cardMapper = $this->createMock(CardMapper::class);
		$this->assignmentMapper = $this->createMock(AssignmentMapper::class);
		$this->aclMapper = $this->createMock(AclMapper::class);
		$this->connection = $this->createMock(IDBConnection::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->trelloHelper = new TrelloHelper(
			$this->boardService,
			$this->labelService,
			$this->stackMapper,
			$this->cardMapper,
			$this->assignmentMapper,
			$this->aclMapper,
			$this->connection,
			$this->userManager,
			$this->l10n
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
				[$this->equalTo('data')],
				[$this->equalTo('setting')]
			)
			->will($this->returnValueMap([
				['data', __DIR__ . '/../fixtures/data-trello.json'],
				['setting', __DIR__ . '/../fixtures/setting-trello.json']
			]));
		$output = $this->createMock(OutputInterface::class);

		$user = $this->createMock(\OCP\IUser::class);
		$user
			->method('getUID')
			->willReturn('admin');
		$this->userManager
			->method('get')
			->willReturn($user);
		$this->userManager
			->method('get')
			->willReturn($user);
		$board = $this->createMock(\OCA\Deck\Db\Board::class);
		$this->boardService
			->expects($this->once())
			->method('create')
			->willReturn($board);
		$label = $this->createMock(\OCA\Deck\Db\Label::class);
		$this->labelService
			->expects($this->once())
			->method('create')
			->willReturn($label);
		$stack = $this->createMock(\OCA\Deck\Db\Stack::class);
		$this->stackMapper
			->expects($this->once())
			->method('insert')
			->willReturn($stack);
		$card = $this->createMock(\OCA\Deck\Db\Card::class);
		$this->cardMapper
			->expects($this->once())
			->method('insert')
			->willReturn($card);

		$this->trelloHelper->validate($input, $output);
		$actual = $this->trelloHelper->import($input, $output);
		$this->assertNull($actual);
	}
}
