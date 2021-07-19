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

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\Label;
use OCA\Deck\Db\Stack;
use OCP\AppFramework\Db\Entity;

abstract class ABoardImportService {
	/** @var BoardImportService */
	private $boardImportService;
	/**
	 * Array of stacks
	 *
	 * @var Stack[]
	 */
	protected $stacks = [];
	/**
	 * Array of Labels
	 *
	 * @var Label[]|Entity[]
	 */
	protected $labels = [];
	/** @var Card[] */
	protected $cards = [];
	/** @var Acl[] */
	protected $acls = [];

	abstract public function getBoard(): ?Board;

	/**
	 * @return Acl[]
	 */
	abstract public function getAclList(): array;

	/**
	 * @return Stack[]
	 */
	abstract public function getStacks(): array;

	/**
	 * @return Card[]
	 */
	abstract public function getCards(): array;

	abstract public function importParticipants(): void;

	abstract public function importComments(): void;

	/** @return Label[] */
	abstract public function getLabels(): array;

	abstract public function assignCardsToLabels(): void;

	/**
	 * Configure import service
	 *
	 * @return void
	 */
	abstract public function bootstrap(): void;

	public function updateStack(string $id, Stack $stack): void {
		$this->stacks[$id] = $stack;
	}

	public function updateCard(string $id, Card $card): void {
		$this->cards[$id] = $card;
	}

	public function updateLabel(string $code, Label $label): void {
		$this->labels[$code] = $label;
	}

	public function updateAcl(string $code, Acl $acl): void {
		$this->acls[$code] = $acl;
	}

	public function setImportService(BoardImportService $service): void {
		$this->boardImportService = $service;
	}

	public function getImportService(): BoardImportService {
		return $this->boardImportService;
	}
}
