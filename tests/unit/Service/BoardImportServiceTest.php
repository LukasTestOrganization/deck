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

use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\LabelMapper;
use OCA\Deck\Db\StackMapper;
use OCP\Comments\ICommentsManager;
use OCP\IDBConnection;
use OCP\IUserManager;

class BoardImportServiceTest extends \Test\TestCase {
	/** @var IDBConnection */
	protected $dbConn;
	/** @var IUserManager */
	private $userManager;
	/** @var BoardMapper */
	private $boardMapper;
	/** @var AclMapper */
	private $aclMapper;
	/** @var LabelMapper */
	private $labelMapper;
	/** @var StackMapper */
	private $stackMapper;
	/** @var CardMapper */
	private $cardMapper;
	/** @var AssignmentMapper */
	private $assignmentMapper;
	/** @var ICommentsManager */
	private $commentsManager;
	/** @var BoardImportService */
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
	}

	public function testImportSuccess() {
		$importService = $this->createMock(ABoardImportService::class);
		$board = new Board();
		$importService
			->method('getBoard')
			->willReturn($board);
		$this->boardImportService->setImportSystem($importService);
		$actual = $this->boardImportService->import();
		$this->assertNull($actual);
	}
}
