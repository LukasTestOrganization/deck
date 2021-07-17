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

use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use OC\Comments\Comment;
use OCA\Deck\AppInfo\Application;
use OCA\Deck\BadRequestException;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\Assignment;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\Label;
use OCA\Deck\Db\LabelMapper;
use OCA\Deck\Db\Stack;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\Exceptions\ConflictException;
use OCA\Deck\NotFoundException;
use OCP\AppFramework\Db\Entity;
use OCP\Comments\ICommentsManager;
use OCP\Comments\NotFoundException as CommentNotFoundException;
use OCP\IDBConnection;
use OCP\IUserManager;

class BoardImportService {
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
	/** @var string */
	private $system = '';
	/** @var null|ABoardImportService */
	private $systemInstance;
	/** @var string[] */
	private $allowedSystems = [];
	/**
	 * Data object created from config JSON
	 *
	 * @var \stdClass
	 * @psalm-suppress PropertyNotSetInConstructor
	 */
	public $config;
	/**
	 * Data object created from JSON of origin system
	 *
	 * @var \stdClass
	 * @psalm-suppress PropertyNotSetInConstructor
	 */
	private $data;
	/**
	 * @var Board
	 * @psalm-suppress PropertyNotSetInConstructor
	 */
	private $board;

	public function __construct(
		IDBConnection $dbConn,
		IUserManager $userManager,
		BoardMapper $boardMapper,
		AclMapper $aclMapper,
		LabelMapper $labelMapper,
		StackMapper $stackMapper,
		AssignmentMapper $assignmentMapper,
		CardMapper $cardMapper,
		ICommentsManager $commentsManager
	) {
		$this->dbConn = $dbConn;
		$this->userManager = $userManager;
		$this->boardMapper = $boardMapper;
		$this->aclMapper = $aclMapper;
		$this->labelMapper = $labelMapper;
		$this->stackMapper = $stackMapper;
		$this->cardMapper = $cardMapper;
		$this->assignmentMapper = $assignmentMapper;
		$this->commentsManager = $commentsManager;
		$this->setData(new \stdClass());
	}

	public function import(): void {
		try {
			$this->importBoard();
			$this->importAcl();
			$this->importLabels();
			$this->importStacks();
			$this->importCards();
			$this->assignCardsToLabels();
			$this->importComments();
			$this->importParticipants();
		} catch (\Throwable $th) {
			throw new BadRequestException($th->getMessage());
		}
	}

	public function validate(): void {
		$this->validateSystem();
		$this->validateConfig();
		$this->validateUsers();
	}

	public function validateSystem(): void {
		if (!in_array($this->getSystem(), $this->getAllowedImportSystems())) {
			throw new NotFoundException('Invalid system');
		}
	}

	/**
	 * @param mixed $system
	 * @return self
	 */
	public function setSystem($system): self {
		$this->system = $system;
		return $this;
	}

	public function getSystem(): string {
		return $this->system;
	}

	public function getAllowedImportSystems(): array {
		if (!$this->allowedSystems) {
			$allowedSystems = glob(__DIR__ . '/BoardImport*Service.php');
			$allowedSystems = array_filter($allowedSystems, function (string $name) {
				$name = basename($name);
				switch ($name) {
					case 'ABoardImportService.php':
					case 'BoardImportService.php':
					case 'BoardImportCommandService.php':
						return false;
				}
				return true;
			});
			$allowedSystems = array_map(function ($name) {
				preg_match('/\/BoardImport(?<system>\w+)Service\.php$/', $name, $matches);
				return lcfirst($matches['system']);
			}, $allowedSystems);
			$this->allowedSystems = array_values($allowedSystems);
		}
		return $this->allowedSystems;
	}

	public function getImportSystem(): ABoardImportService {
		$systemClass = 'OCA\\Deck\\Service\\BoardImport' . ucfirst($this->getSystem()) . 'Service';
		if (!$this->getSystem()) {
			throw new NotFoundException('System to import not found');
		}
		if (!is_object($this->systemInstance)) {
			$this->systemInstance = \OC::$server->get($systemClass);
			$this->systemInstance->setImportService($this);
		}

		return $this->systemInstance;
	}

	public function setImportSystem(ABoardImportService $instance): void {
		$this->systemInstance = $instance;
	}

	public function insertAssignment(Assignment $assignment): self {
		$this->assignmentMapper->insert($assignment);
		return $this;
	}

	public function importBoard(): void {
		$board = $this->getImportSystem()->getBoard();
		if ($board) {
			$this->boardMapper->insert($board);
			$this->board = $board;
		}
	}

	public function getBoard(): Board {
		return $this->board;
	}

	public function importAcl(): self {
		$aclList = $this->getImportSystem()->getAclList();
		foreach ($aclList as $acl) {
			$this->aclMapper->insert($acl);
		}
		$this->getBoard()->setAcl($aclList);
		return $this;
	}

	public function importLabels(): array {
		$labels = $this->getImportSystem()->importLabels();
		$this->getBoard()->setLabels($labels);
		return $labels;
	}

	public function createLabel(string $title, string $color, int $boardId): Entity {
		$label = new Label();
		$label->setTitle($title);
		$label->setColor($color);
		$label->setBoardId($boardId);
		return $this->labelMapper->insert($label);
	}

	/**
	 * @return Stack[]
	 */
	public function importStacks(): array {
		$stacks = $this->getImportSystem()->getStacks();
		foreach ($stacks as $code => $stack) {
			$this->stackMapper->insert($stack);
			$this->getImportSystem()->updateStack($code, $stack);
		}
		$this->getBoard()->setStacks(array_values($stacks));
		return $stacks;
	}

	public function importCards(): self {
		$cards = $this->getImportSystem()->getCards();
		foreach ($cards as $code => $card) {
			$this->cardMapper->insert($card);
			$this->getImportSystem()->updateCard($code, $card);
		}
		return $this;
	}

	/**
	 * @param mixed $cardId
	 * @param mixed $labelId
	 * @return self
	 */
	public function assignCardToLabel($cardId, $labelId): self {
		$this->cardMapper->assignLabel(
			$cardId,
			$labelId
		);
		return $this;
	}

	public function assignCardsToLabels(): void {
		$this->getImportSystem()->assignCardsToLabels();
	}

	public function importComments(): void {
		$this->getImportSystem()->importComments();
	}

	public function insertComment(string $cardId, Comment $comment): void {
		$comment->setObject('deckCard', $cardId);
		$comment->setVerb('comment');
		// Check if parent is a comment on the same card
		if ($comment->getParentId() !== '0') {
			try {
				$comment = $this->commentsManager->get($comment->getParentId());
				if ($comment->getObjectType() !== Application::COMMENT_ENTITY_TYPE || $comment->getObjectId() !== $cardId) {
					throw new CommentNotFoundException();
				}
			} catch (CommentNotFoundException $e) {
				throw new BadRequestException('Invalid parent id: The parent comment was not found or belongs to a different card');
			}
		}

		try {
			$qb = $this->dbConn->getQueryBuilder();

			$values = [
				'parent_id' => $qb->createNamedParameter($comment->getParentId()),
				'topmost_parent_id' => $qb->createNamedParameter($comment->getTopmostParentId()),
				'children_count' => $qb->createNamedParameter($comment->getChildrenCount()),
				'actor_type' => $qb->createNamedParameter($comment->getActorType()),
				'actor_id' => $qb->createNamedParameter($comment->getActorId()),
				'message' => $qb->createNamedParameter($comment->getMessage()),
				'verb' => $qb->createNamedParameter($comment->getVerb()),
				'creation_timestamp' => $qb->createNamedParameter($comment->getCreationDateTime(), 'datetime'),
				'latest_child_timestamp' => $qb->createNamedParameter($comment->getLatestChildDateTime(), 'datetime'),
				'object_type' => $qb->createNamedParameter($comment->getObjectType()),
				'object_id' => $qb->createNamedParameter($comment->getObjectId()),
				'reference_id' => $qb->createNamedParameter($comment->getReferenceId())
			];
	
			$affectedRows = $qb->insert('comments')
				->values($values)
				->execute();
	
			if ($affectedRows > 0) {
				$comment->setId((string)$qb->getLastInsertId());
			}
		} catch (\InvalidArgumentException $e) {
			throw new BadRequestException('Invalid input values');
		} catch (CommentNotFoundException $e) {
			throw new NotFoundException('Could not create comment.');
		}
	}

	public function importParticipants(): void {
		$this->getImportSystem()->importParticipants();
	}

	final public function setData(\stdClass $data): void {
		$this->data = $data;
	}

	public function getData(): \stdClass {
		return $this->data;
	}

	/**
	 * Define a config
	 *
	 * @param string $configName
	 * @param mixed $value
	 * @return void
	 */
	public function setConfig(string $configName, $value): void {
		if (empty((array) $this->config)) {
			$this->setConfigInstance(new \stdClass);
		}
		$this->config->$configName = $value;
	}

	/**
	 * Get a config
	 *
	 * @param string $configName config name
	 * @return mixed
	 */
	public function getConfig(string $configName = null) {
		if (!property_exists($this->config, $configName)) {
			return;
		}
		return $this->config->$configName;
	}

	/**
	 * @param mixed $config
	 * @return self
	 */
	public function setConfigInstance($config): self {
		if (is_string($config)) {
			if (!is_file($config)) {
				throw new NotFoundException('Please inform a valid config json file');
			}
			$config = json_decode(file_get_contents($config));
			if (!is_object($config)) {
				throw new NotFoundException('Please inform a valid config json file');
			}
		}
		$this->config = $config;
		return $this;
	}

	public function getConfigInstance(): \stdClass {
		return $this->config;
	}

	protected function validateConfig(): void {
		$config = $this->getConfigInstance();
		$schemaPath = $this->getJsonSchemaPath();
		$validator = new Validator();
		$newConfig = clone $config;
		$validator->validate(
			$newConfig,
			(object)['$ref' => 'file://' . realpath($schemaPath)],
			Constraint::CHECK_MODE_APPLY_DEFAULTS
		);
		if (!$validator->isValid()) {
			throw new ConflictException('Invalid config file', $validator->getErrors());
		}
		$this->setConfigInstance($newConfig);
		$this->validateOwner();
	}

	public function getJsonSchemaPath(): string {
		return __DIR__ . '/fixtures/config-' . $this->getSystem() . '-schema.json';
	}

	public function validateOwner(): void {
		$owner = $this->userManager->get($this->getConfig('owner'));
		if (!$owner) {
			throw new \LogicException('Owner "' . $this->getConfig('owner')->getUID() . '" not found on Nextcloud. Check setting json.');
		}
		$this->setConfig('owner', $owner);
	}

	public function validateUsers(): void {
		$this->getImportSystem()->validateUsers();
	}
}
