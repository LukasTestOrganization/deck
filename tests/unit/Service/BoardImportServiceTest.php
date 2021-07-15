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

class BoardImportServiceTest extends \Test\TestCase {
	/** @var TrelloImportService */
	private $trelloImportService;
	/** @var BoardImportService */
	private $boardImportService;
	public function setUp(): void {
		$this->trelloImportService = $this->createMock(TrelloImportService::class);
		$this->boardImportService = new BoardImportService(
			$this->trelloImportService
		);
	}

	public function testImportSuccess() {
		$config = json_decode(file_get_contents(__DIR__ . '/../../data/config-trello.json'));
		$data = json_decode(file_get_contents(__DIR__ . '/../../data/data-trello.json'));
		$actual = $this->boardImportService->import(
			'trello',
			$config,
			$data
		);
	}
}
