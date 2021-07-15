<?php

namespace OCA\Deck\Service;

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\Stack;

abstract class ABoardImportService {
	/** @var BoardImportService */
	private $boardImportService;

	abstract public function getBoard(): ?Board;

	/**
	 * @return Acl[]
	 */
	abstract public function getAclList(): array;

	/**
	 * @return Stack[]
	 */
	abstract function getStacks(): array;

	/**
	 * @return Card[]
	 */
	abstract function getCards(): array;

	abstract function updateStack(string $id, Stack $stack): self;

	abstract function updateCard(string $id, Card $card): self;

	abstract function assignCardsToLabels(): self;

	abstract function importParticipants(): self;

	abstract function importComments(): self;

	abstract public function importLabels(): self;

	abstract public function validateUsers(): self;

	public function setImportService($service): self {
		$this->boardImportService = $service;
		return $this;
	}

	public function getImportService(): BoardImportService {
		return $this->boardImportService;
	}
}
