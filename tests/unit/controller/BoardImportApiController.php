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
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class BoardImportApiController extends ApiController {
	/** @var BoardImportService */
	private $boardImportService;
	/** @var string */
	private $userId;

	public function __construct(
		$appName,
		IRequest $request,
		BoardImportService $boardImportService,
		$userId
	) {
		parent::__construct($appName, $request);
		$this->boardImportService = $boardImportService;
		$this->userId = $userId;
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function import($system, $config, $data) {
		$this->boardImportService->setSystem($system);
		$config = json_decode(json_encode($config));
		$config->owner = $this->userId;
		$this->boardImportService->setConfigInstance($config);
		$this->boardImportService->setData(json_decode(json_encode($data)));
		$this->boardImportService->validate();
		$this->boardImportService->import();
		return new DataResponse($this->boardImportService->getBoard(), Http::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @CORS
	 * @NoCSRFRequired
	 */
	public function getAllowedSystems() {
		$allowedSystems = $this->boardImportService->getAllowedImportSystems();
		return new DataResponse($allowedSystems, Http::STATUS_OK);
	}
}
