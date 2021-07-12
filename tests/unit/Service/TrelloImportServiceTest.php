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
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\StackMapper;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IUserManager;

class TrelloImportServiceTest extends \Test\TestCase {
	/** @var TrelloImportService */
	private $trelloImportService;
	/** @var BoardService */
	private $boardService;
	/** @var LabelService */
	private $labelService;
	/** @var StackMapper */
	private $stackMapper;
	/** @var CardMapper */
	private $cardMapper;
	/** @var AssignmentMapper */
	private $assignmentMapper;
	/** @var AclMapper */
	private $aclMapper;
	/** @var IDBConnection */
	private $connection;
	/** @var IUserManager */
	private $userManager;
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
		$this->trelloImportService = new TrelloImportService(
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
	}

	public function testValidateOwnerWithFaliure() {
		$owner = $this->createMock(\OCP\IUser::class);
		$owner
			->method('getUID')
			->willReturn('admin');
		$this->trelloImportService->setConfig('owner', $owner);
		$this->userManager
			->method('get')
			->willReturn(null);
		$this->expectErrorMessage('Owner "admin" not found on Nextcloud. Check setting json.');
		$this->trelloImportService->validateOwner();
	}

	public function testValidateOwnerWithSuccess() {
		$owner = $this->createMock(\OCP\IUser::class);
		$owner
			->method('getUID')
			->willReturn('admin');
		$this->trelloImportService->setConfig('owner', $owner);
		$this->userManager
			->method('get')
			->willReturn($owner);
		$actual = $this->trelloImportService->validateOwner();
		$this->assertNull($actual);
	}
}
