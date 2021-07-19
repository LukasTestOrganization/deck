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

use OC\Comments\Comment;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\Assignment;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\Label;
use OCA\Deck\Db\LabelMapper;
use OCA\Deck\Db\Stack;
use OCA\Deck\Db\StackMapper;
use OCP\Comments\ICommentsManager;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;

class BoardImportServiceTest extends \Test\TestCase {
	/** @var IDBConnection|MockObject */
	protected $dbConn;
	/** @var IUserManager|MockObject */
	private $userManager;
	/** @var BoardMapper|MockObject */
	private $boardMapper;
	/** @var AclMapper|MockObject */
	private $aclMapper;
	/** @var LabelMapper|MockObject */
	private $labelMapper;
	/** @var StackMapper|MockObject */
	private $stackMapper;
	/** @var CardMapper|MockObject */
	private $cardMapper;
	/** @var AssignmentMapper|MockObject */
	private $assignmentMapper;
	/** @var ICommentsManager|MockObject */
	private $commentsManager;
	/** @var BoardImportTrelloService|MockObject */
	private $importTrelloService;
	/** @var BoardImportService|MockObject */
	private $boardImportService;
	public function setUp(): void {
		$this->dbConn = $this->createMock(IDBConnection::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->boardMapper = $this->createMock(BoardMapper::class);
		$this->aclMapper = $this->createMock(AclMapper::class);
		$this->labelMapper = $this->createMock(LabelMapper::class);
		$this->stackMapper = $this->createMock(StackMapper::class);
		$this->cardMapper = $this->createMock(AssignmentMapper::class);
		$this->assignmentMapper = $this->createMock(CardMapper::class);
		$this->commentsManager = $this->createMock(ICommentsManager::class);
		$this->boardImportService = new BoardImportService(
			$this->dbConn,
			$this->userManager,
			$this->boardMapper,
			$this->aclMapper,
			$this->labelMapper,
			$this->stackMapper,
			$this->cardMapper,
			$this->assignmentMapper,
			$this->commentsManager
		);

		$this->boardImportService->setSystem('trello');

		$data = json_decode(file_get_contents(__DIR__ . '/../../data/data-trello.json'));
		$this->boardImportService->setData($data);

		$configInstance = json_decode(file_get_contents(__DIR__ . '/../../data/config-trello.json'));
		$this->boardImportService->setConfigInstance($configInstance);

		$this->importTrelloService = $this->createMock(BoardImportTrelloService::class);
		$this->boardImportService->setImportSystem($this->importTrelloService);

		$owner = $this->createMock(IUser::class);
		$owner
			->method('getUID')
			->willReturn('admin');

		$johndoe = $this->createMock(IUser::class);
		$johndoe
			->method('getUID')
			->willReturn('johndoe');
		$this->userManager
			->method('get')
			->withConsecutive(
				['admin'],
				['johndoe']
			)
			->willReturnonConsecutiveCalls(
				$owner,
				$johndoe
			);
	}

	public function testImportSuccess() {
		$this->boardMapper
			->expects($this->once())
			->method('insert');

		$this->importTrelloService
			->method('getAclList')
			->willReturn([new Acl()]);
		$this->aclMapper
			->expects($this->once())
			->method('insert');

		$this->importTrelloService
			->method('getLabels')
			->willReturn([new Label()]);
		$this->labelMapper
			->expects($this->once())
			->method('insert');

		$this->importTrelloService
			->method('getStacks')
			->willReturn([new Stack()]);
		$this->stackMapper
			->expects($this->once())
			->method('insert');

		$this->importTrelloService
			->method('getCards')
			->willReturn([new Card()]);
		$this->cardMapper
			->expects($this->any())
			->method('insert');

		$this->importTrelloService
			->method('getComments')
			->willReturn([
				'fakecardid' => [new Comment()]
			]);
		$this->commentsManager
			->expects($this->once())
			->method('save');

		$this->importTrelloService
			->method('getCardAssignments')
			->willReturn([
				'fakecardid' => [new Assignment()]
			]);
		$this->assignmentMapper
			->expects($this->once())
			->method('insert');

		$actual = $this->boardImportService->import();

		$this->assertNull($actual);
	}
}
