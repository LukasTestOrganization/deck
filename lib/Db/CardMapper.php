<?php
/**
 * @copyright Copyright (c) 2016 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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

namespace OCA\Deck\Db;

use Exception;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\Notification\IManager;

class CardMapper extends QBMapper implements IPermissionMapper {

	/** @var LabelMapper */
	private $labelMapper;
	/** @var IUserManager */
	private $userManager;
	/** @var IManager */
	private $notificationManager;
	private $databaseType;
	private $database4ByteSupport;

	public function __construct(
		IDBConnection $db,
		LabelMapper $labelMapper,
		IUserManager $userManager,
		IManager $notificationManager,
		$databaseType = 'sqlite',
		$database4ByteSupport = true
	) {
		parent::__construct($db, 'deck_cards', Card::class);
		$this->labelMapper = $labelMapper;
		$this->userManager = $userManager;
		$this->notificationManager = $notificationManager;
		$this->databaseType = $databaseType;
		$this->database4ByteSupport = $database4ByteSupport;
	}

	public function insert(Entity $entity): Entity {
		$entity->setDatabaseType($this->databaseType);
		$entity->setCreatedAt(time());
		$entity->setLastModified(time());
		if (!$this->database4ByteSupport) {
			$description = preg_replace('/[\x{10000}-\x{10FFFF}]/u', "\xEF\xBF\xBD", $entity->getDescription());
			$entity->setDescription($description);
		}
		return parent::insert($entity);
	}

	public function update(Entity $entity, $updateModified = true): Entity {
		if (!$this->database4ByteSupport) {
			$description = preg_replace('/[\x{10000}-\x{10FFFF}]/u', "\xEF\xBF\xBD", $entity->getDescription());
			$entity->setDescription($description);
		}
		$entity->setDatabaseType($this->databaseType);

		if ($updateModified) {
			$entity->setLastModified(time());
		}

		// make sure we only reset the notification flag if the duedate changes
		$updatedFields = $entity->getUpdatedFields();
		if (isset($updatedFields['duedate']) && $updatedFields['duedate']) {
			try {
				/** @var Card $existing */
				$existing = $this->find($entity->getId());
				if ($existing && $entity->getDuedate() !== $existing->getDuedate()) {
					$entity->setNotified(false);
				}
				// remove pending notifications
				$notification = $this->notificationManager->createNotification();
				$notification
					->setApp('deck')
					->setObject('card', $entity->getId());
				$this->notificationManager->markProcessed($notification);
			} catch (Exception $e) {
			}
		}
		return parent::update($entity);
	}

	public function markNotified(Card $card): Entity {
		$cardUpdate = new Card();
		$cardUpdate->setId($card->getId());
		$cardUpdate->setNotified(true);
		return parent::update($cardUpdate);
	}

	public function find($id): Card {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('deck_cards')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->orderBy('order')
			->addOrderBy('id');
		/** @var Card $card */
		$card = $this->findEntity($qb);
		$labels = $this->labelMapper->findAssignedLabelsForCard($card->id);
		$card->setLabels($labels);
		$this->mapOwner($card);
		return $card;
	}

	public function findAll($stackId, $limit = null, $offset = null, $since = -1) {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('deck_cards')
			->where($qb->expr()->eq('stack_id', $qb->createNamedParameter($stackId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('archived', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->eq('deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->gt('last_modified', $qb->createNamedParameter($since, IQueryBuilder::PARAM_INT)))
			->setMaxResults($limit)
			->setFirstResult($offset)
			->orderBy('order')
			->addOrderBy('id');
		return $this->findEntities($qb);
	}

	public function queryCardsByBoard(int $boardId): IQueryBuilder {
		$qb = $this->db->getQueryBuilder();
		$qb->select('c.*')
			->from('deck_cards', 'c')
			->innerJoin('c', 'deck_stacks', 's', 'c.stack_id = s.id')
			->where($qb->expr()->eq('s.board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)));
		return $qb;
	}

	public function queryCardsByBoards(array $boardIds): IQueryBuilder {
		$qb = $this->db->getQueryBuilder();
		$qb->select('c.*')
			->from('deck_cards', 'c')
			->innerJoin('c', 'deck_stacks', 's', $qb->expr()->eq('s.id', 'c.stack_id'))
			->andWhere($qb->expr()->in('s.board_id', $qb->createNamedParameter($boardIds, IQueryBuilder::PARAM_INT_ARRAY)));
		return $qb;
	}

	public function findDeleted($boardId, $limit = null, $offset = null) {
		$qb = $this->queryCardsByBoard($boardId);
		$qb->andWhere($qb->expr()->neq('c.deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
			->setMaxResults($limit)
			->setFirstResult($offset)
			->orderBy('order')
			->addOrderBy('id');
		return $this->findEntities($qb);
	}

	public function findCalendarEntries($boardId, $limit = null, $offset = null) {
		$qb = $this->db->getQueryBuilder();
		$qb->select('c.*')
			->from('deck_cards', 'c')
			->join('c', 'deck_stacks', 's', 's.id = c.stack_id')
			->where($qb->expr()->eq('s.board_id', $qb->createNamedParameter($boardId)))
			->andWhere($qb->expr()->eq('c.deleted_at', $qb->createNamedParameter('0')))
			->orderBy('c.duedate')
			->setMaxResults($limit)
			->setFirstResult($offset);
		return $this->findEntities($qb);
	}

	public function findAllArchived($stackId, $limit = null, $offset = null) {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('deck_cards')
			->where($qb->expr()->eq('stack_id', $qb->createNamedParameter($stackId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('archived', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->eq('deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
			->setMaxResults($limit)
			->setFirstResult($offset)
			->orderBy('last_modified');
		return $this->findEntities($qb);
	}

	public function findAllByStack($stackId, $limit = null, $offset = null) {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('deck_cards')
			->where($qb->expr()->eq('stack_id', $qb->createNamedParameter($stackId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('archived', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
			->setMaxResults($limit)
			->setFirstResult($offset)
			->orderBy('order')
			->addOrderBy('id');
		return $this->findEntities($qb);
	}

	public function findAllWithDue($boardId) {
		$qb = $this->db->getQueryBuilder();
		$qb->select('c.*')
			->from('deck_cards', 'c')
			->innerJoin('c', 'deck_stacks', 's', 's.id = c.stack_id')
			->innerJoin('s', 'deck_boards', 'b', 'b.id = s.board_id')
			->where($qb->expr()->eq('s.board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNotNull('c.duedate'))
			->andWhere($qb->expr()->eq('c.archived', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->eq('c.deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('b.archived', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->eq('b.deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)));
		return $this->findEntities($qb);
	}

	public function findAssignedCards($boardId, $username) {
		$qb = $this->db->getQueryBuilder();
		$qb->select('c.*')
			->from('deck_cards', 'c')
			->innerJoin('c', 'deck_stacks', 's', 's.id = c.stack_id')
			->innerJoin('s', 'deck_boards', 'b', 'b.id = s.board_id')
			->innerJoin('c', 'deck_assigned_users', 'u', 'c.id = u.card_id')
			->where($qb->expr()->eq('s.board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('u.participant', $qb->createNamedParameter($username, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('u.type', $qb->createNamedParameter(Acl::PERMISSION_TYPE_USER, IQueryBuilder::PARAM_INT)))
			// Filter out archived/deleted cards and board
			->andWhere($qb->expr()->eq('c.archived', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->eq('c.deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('b.archived', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->eq('b.deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)));
		return $this->findEntities($qb);
	}

	public function findOverdue() {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id','title','duedate','notified')
			->from('deck_cards')
			->where($qb->expr()->lt('duedate', $qb->createFunction('NOW()')))
			->andWhere($qb->expr()->eq('notified', $qb->createNamedParameter(false)))
			->andWhere($qb->expr()->eq('archived', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->eq('deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)));
		return $this->findEntities($qb);
	}

	public function findUnexposedDescriptionChances() {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id','title','duedate','notified','description_prev','last_editor','description')
			->from('deck_cards')
			->where($qb->expr()->isNotNull('last_editor'))
			->andWhere($qb->expr()->isNotNull('description_prev'));
		return $this->findEntities($qb);
	}

	public function search($boardIds, $term, $limit = null, $offset = null) {
		$qb = $this->queryCardsByBoards($boardIds);
		$qb->andWhere($qb->expr()->eq('c.deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)));
		$qb->andWhere(
			$qb->expr()->orX(
				$qb->expr()->iLike('c.title', $qb->createNamedParameter('%' . $this->db->escapeLikeParameter($term) . '%')),
				$qb->expr()->iLike('c.description', $qb->createNamedParameter('%' . $this->db->escapeLikeParameter($term) . '%'))
			)
		);
		if ($limit !== null) {
			$qb->setMaxResults($limit);
		}
		if ($offset !== null) {
			$qb->setFirstResult($offset);
		}
		return $this->findEntities($qb);
	}

	public function delete(Entity $entity): Entity {
		// delete assigned labels
		$this->labelMapper->deleteLabelAssignmentsForCard($entity->getId());
		// delete card
		return parent::delete($entity);
	}

	public function deleteByStack($stackId) {
		$cards = $this->findAllByStack($stackId);
		foreach ($cards as $card) {
			$this->delete($card);
		}
	}

	public function assignLabel($card, $label) {
		$qb = $this->db->getQueryBuilder();
		$qb->insert('deck_assigned_labels')
			->values([
				'label_id' => $qb->createNamedParameter($label, IQueryBuilder::PARAM_INT),
				'card_id' => $qb->createNamedParameter($card, IQueryBuilder::PARAM_INT),
			]);
		$qb->execute();
	}

	public function removeLabel($card, $label) {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('deck_assigned_labels')
			->where($qb->expr()->eq('card_id', $qb->createNamedParameter($card, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('label_id', $qb->createNamedParameter($label, IQueryBuilder::PARAM_INT)));
		$qb->execute();
	}

	public function isOwner($userId, $cardId) {
		$sql = 'SELECT owner FROM `*PREFIX*deck_boards` WHERE `id` IN (SELECT board_id FROM `*PREFIX*deck_stacks` WHERE id IN (SELECT stack_id FROM `*PREFIX*deck_cards` WHERE id = ?))';
		$stmt = $this->db->prepare($sql);
		$stmt->bindParam(1, $cardId, \PDO::PARAM_INT);
		$stmt->execute();
		$row = $stmt->fetch();
		return ($row['owner'] === $userId);
	}

	public function findBoardId($cardId) {
		$sql = 'SELECT id FROM `*PREFIX*deck_boards` WHERE `id` IN (SELECT board_id FROM `*PREFIX*deck_stacks` WHERE id IN (SELECT stack_id FROM `*PREFIX*deck_cards` WHERE id = ?))';
		$stmt = $this->db->prepare($sql);
		$stmt->bindParam(1, $cardId, \PDO::PARAM_INT);
		$stmt->execute();
		$row = $stmt->fetch();
		return $row['id'];
	}

	public function mapOwner(Card &$card) {
		$userManager = $this->userManager;
		$card->resolveRelation('owner', function ($owner) use (&$userManager) {
			$user = $userManager->get($owner);
			if ($user !== null) {
				return new User($user);
			}
			return null;
		});
	}

	/**
	 * @param $ownerId
	 * @param $newOwnerId
	 * @return void
	 */
	public function transferOwnership($ownerId, $newOwnerId)
	{
		$params = [
			'owner' => $ownerId,
			'newOwner' => $newOwnerId
		];
		$sql = "UPDATE `{$this->tableName}`  SET `owner` = :newOwner WHERE `owner` = :owner";
		$stmt = $this->execute($sql, $params);
		$stmt->closeCursor();
	}
}
