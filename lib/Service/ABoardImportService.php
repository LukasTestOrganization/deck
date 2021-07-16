<?php

namespace OCA\Deck\Service;

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\Label;
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
	abstract public function getStacks(): array;

	/**
	 * @return Card[]
	 */
	abstract public function getCards(): array;

	abstract public function updateStack(string $id, Stack $stack): self;

	abstract public function updateCard(string $id, Card $card): self;

	abstract public function importParticipants(): self;

	abstract public function importComments();

	/** @return Label[] */
	abstract public function importLabels(): array;

	abstract public function assignCardsToLabels(): self;

	abstract public function validateUsers(): self;

	public function setImportService($service): self {
		$this->boardImportService = $service;
		return $this;
	}

	public function getImportService(): BoardImportService {
		return $this->boardImportService;
	}
}
