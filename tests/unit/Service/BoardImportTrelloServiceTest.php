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

use OCA\Deck\Db\Board;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;

class BoardImportTrelloServiceTest extends \Test\TestCase {
	/** @var BoardImportTrelloService */
	private $service;
	/** @var IUserManager */
	private $userManager;
	/** @var IL10N */
	private $l10n;
	public function setUp(): void {
		$this->userManager = $this->createMock(IUserManager::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->service = new BoardImportTrelloService(
			$this->userManager,
			$this->l10n
		);
	}

	public function testValidateUsersWithoutUsers() {
		$importService = $this->createMock(BoardImportService::class);
		$this->service->setImportService($importService);
		$actual = $this->service->validateUsers();
		$this->assertNull($actual);
	}

	public function testValidateUsersWithInvalidUser() {
		$this->expectErrorMessage('Trello user trello_user not found in property "members" of json data');
		$importService = $this->createMock(BoardImportService::class);
		$importService
			->method('getConfig')
			->willReturn([
				'trello_user' => 'nextcloud_user'
			]);
		$importService
			->method('getData')
			->willReturn(json_decode('{"members": [{"username": "othre_trello_user"}]}'));
		$this->service->setImportService($importService);
		$actual = $this->service->validateUsers();
		$this->assertInstanceOf(BoardImportTrelloService::class, $actual);
	}

	public function testValidateUsersWithNotStringNextcloud() {
		$this->expectErrorMessage('User on setting uidRelation is invalid');
		$importService = $this->createMock(BoardImportService::class);
		$importService
			->method('getConfig')
			->willReturn([
				'trello_user' => []
			]);
		$importService
			->method('getData')
			->willReturn(json_decode('{"members": [{"username": "trello_user"}]}'));
		$this->service->setImportService($importService);
		$actual = $this->service->validateUsers();
		$this->assertInstanceOf(BoardImportTrelloService::class, $actual);
	}

	public function testValidateUsersWithNotFoundUser() {
		$this->expectErrorMessage('User on setting uidRelation not found: nextcloud_user');
		$importService = $this->createMock(BoardImportService::class);
		$importService
			->method('getConfig')
			->willReturn(json_decode('{"trello_user": "nextcloud_user"}'));
		$importService
			->method('getData')
			->willReturn(json_decode('{"members": [{"username": "trello_user"}]}'));
		$this->service->setImportService($importService);
		$actual = $this->service->validateUsers();
		$this->assertInstanceOf(BoardImportTrelloService::class, $actual);
	}

	public function testValidateUsersWithValidUsers() {
		$importService = $this->createMock(BoardImportService::class);
		$importService
			->method('getConfig')
			->willReturn(json_decode('{"trello_user": "nextcloud_user"}'));
		$importService
			->method('getData')
			->willReturn(json_decode('{"members": [{"id": "fakeid", "username": "trello_user"}]}'));
		$fakeUser = $this->createMock(IUser::class);
		$this->userManager
			->method('get')
			->with('nextcloud_user')
			->willReturn($fakeUser);
		$this->service->setImportService($importService);
		$actual = $this->service->validateUsers();
		$this->assertNull($actual);
	}

	public function testGetBoardWithSuccess() {
		$importService = $this->createMock(BoardImportService::class);
		$owner = $this->createMock(IUser::class);
		$owner
			->method('getUID')
			->willReturn('owner');
		$importService
			->method('getConfig')
			->withConsecutive(
				['owner'],
				['color']
			)->willReturnonConsecutiveCalls(
				$owner,
				'000000'
			);
		$importService
			->method('getData')
			->willReturn(json_decode('{"name": "test"}'));
		$this->service->setImportService($importService);
		$actual = $this->service->getBoard();
		$this->assertInstanceOf(Board::class, $actual);
		$this->assertEquals('test', $actual->getTitle());
		$this->assertEquals('owner', $actual->getOwner());
		$this->assertEquals('000000', $actual->getColor());
	}

	public function testGetBoardWithInvalidName() {
		$this->expectErrorMessage('Invalid name of board');
		$importService = $this->createMock(BoardImportService::class);
		$importService
			->method('getData')
			->willReturn(new \stdClass);
		$this->service->setImportService($importService);
		$this->service->getBoard();
	}
}
