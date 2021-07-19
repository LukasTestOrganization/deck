<?php
/**
 * @copyright Copyright (c) 2018 Ryan Fletcher <ryan.fletcher@codepassion.ca>
 *
 * @author Ryan Fletcher <ryan.fletcher@codepassion.ca>
 *
 * @license GNU AGPL version 3 or any later version
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\Deck\Controller;

use OCA\Deck\Db\Board;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCA\Deck\Service\BoardImportService;

class BoardImportApiControllerTest extends \Test\TestCase {
	private $appName = 'deck';
	private $userId = 'admin';
	/** @var BoardImportApiController */
	private $controller;
	/** @var BoardImportService */
	private $boardImportService;

	public function setUp(): void {
		parent::setUp();
		$this->request = $this->createMock(IRequest::class);
		$this->boardImportService = $this->createMock(BoardImportService::class);

		$this->controller = new BoardImportApiController(
			$this->appName,
			$this->request,
			$this->boardImportService,
			$this->userId
		);
	}

	public function testGetAllowedSystems() {
		$this->boardImportService
			->method('getAllowedImportSystems')
			->willReturn(['trello']);
		$actual = $this->controller->getAllowedSystems();
		$expected = new DataResponse(['trello'], HTTP::STATUS_OK);
		$this->assertEquals($expected, $actual);
	}

	public function testImport() {
		$system = 'trello';
		$config = [
			'owner' => 'test'
		];
		$data = [
			'name' => 'test'
		];
		$actual = $this->controller->import($system, $config, $data);
		$board = $this->createMock(Board::class);
		$this->assertInstanceOf(Board::class, $board);
		$this->assertEquals(HTTP::STATUS_OK, $actual->getStatus());
	}
}
