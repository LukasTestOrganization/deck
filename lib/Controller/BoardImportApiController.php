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

namespace OCA\Deck\Controller;

use OCA\Deck\Service\BoardImportService;
use OCA\Files\Controller\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;

class BoardImportApiController extends ApiController {
	/** @var BoardImportService */
	private $boardImportService;

	public function __construct(
		BoardImportService $boardImportService
	) {
		$this->boardImportService = $boardImportService;
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function import($system, $config, $data) {
		$board = $this->boardImportService->import($system, $config, $data);
		return new DataResponse($board, Http::STATUS_OK);
	}
}
