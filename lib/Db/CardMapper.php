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

use DateTime;
use Exception;
use OC\Cache\CappedMemoryCache;
use OCA\Deck\AppInfo\Application;
use OCA\Deck\Search\Query\SearchQuery;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Notification\IManager;

class CardMapper extends QBMapper implements IPermissionMapper {

	/** @var LabelMapper */
	private $labelMapper;
	/** @var IUserManager */
	private $userManager;
	/** @var IGroupManager */
	private $groupManager;
	/** @var IManager */
	private $notificationManager;
	private $databaseType;
	private $database4ByteSupport;

	private $cardBoardCache;

	public function __construct(
		IDBConnection $db,
		LabelMapper $labelMapper,
		IUserManager $userManager,
		IGroupManager $groupManager,
		IManager $notificationManager,
		$databaseType = 'sqlite3',
		$database4ByteSupport = true
	) {
		parent::__construct($db, 'deck_cards', Card::class);
		$this->labelMapper = $labelMapper;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->notificationManager = $notificationManager;
		$this->databaseType = $databaseType;
		$this->database4ByteSupport = $database4ByteSupport;
		$this->cardBoardCache = new CappedMemoryCache();
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
		$labels = $this->labelMapper->findAssignedLabelsForCard($card->getId());
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
			->andWhere($qb->expr()->eq('s.deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('b.archived', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->eq('b.deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)));
		return $this->findEntities($qb);
	}

	public function findToMeOrNotAssignedCards($boardId, $username) {
		$qb = $this->db->getQueryBuilder();
		$qb->select('c.*')
			->from('deck_cards', 'c')
			->innerJoin('c', 'deck_stacks', 's', 's.id = c.stack_id')
			->innerJoin('s', 'deck_boards', 'b', 'b.id = s.board_id')
			->leftJoin('c', 'deck_assigned_users', 'u', 'c.id = u.card_id')
			->where($qb->expr()->eq('s.board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('u.participant', $qb->createNamedParameter($username, IQueryBuilder::PARAM_STR)),
				$qb->expr()->isNull('u.participant'))
			)
			// Filter out archived/deleted cards and board
			->andWhere($qb->expr()->eq('c.archived', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->eq('c.deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('s.deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('b.archived', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->eq('b.deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)));
		return $this->findEntities($qb);
	}

	public function findOverdue() {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id','title','duedate','notified')
			->from('deck_cards')
			->where($qb->expr()->lt('duedate', $qb->createFunction('NOW()')))
			->andWhere($qb->expr()->eq('notified', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
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

	public function search(array $boardIds, SearchQuery $query, int $limit = null, int $offset = null): array {
		$qb = $this->queryCardsByBoards($boardIds);
		$this->extendQueryByFilter($qb, $query);

		if (count($query->getTextTokens()) > 0) {
			$tokenMatching = $qb->expr()->andX(
				...array_map(function (string $token) use ($qb) {
					return $qb->expr()->orX(
						$qb->expr()->iLike(
							'c.title',
							$qb->createNamedParameter('%' . $this->db->escapeLikeParameter($token) . '%', IQueryBuilder::PARAM_STR),
							IQueryBuilder::PARAM_STR
						),
						$qb->expr()->iLike(
							'c.description',
							$qb->createNamedParameter('%' . $this->db->escapeLikeParameter($token) . '%', IQueryBuilder::PARAM_STR),
							IQueryBuilder::PARAM_STR
						)
					);
				}, $query->getTextTokens())
			);
			$qb->andWhere(
				$tokenMatching
			);
		}

		$qb->groupBy('c.id');
		$qb->orderBy('c.last_modified', 'DESC');
		if ($limit !== null) {
			$qb->setMaxResults($limit);
		}
		if ($offset !== null) {
			$qb->andWhere($qb->expr()->lt('c.last_modified', $qb->createNamedParameter($offset, IQueryBuilder::PARAM_INT)));
		}

		$result = $qb->execute();
		$entities = [];
		while ($row = $result->fetch()) {
			$entities[] = Card::fromRow($row);
		}
		$result->closeCursor();
		return $entities;
	}

	public function searchComments(array $boardIds, SearchQuery $query, int $limit = null, int $offset = null): array {
		if (count($query->getTextTokens()) === 0) {
			return [];
		}
		$qb = $this->queryCardsByBoards($boardIds);
		$this->extendQueryByFilter($qb, $query);

		$qb->innerJoin('c', 'comments', 'comments', $qb->expr()->andX(
			$qb->expr()->eq('comments.object_id', $qb->expr()->castColumn('c.id', IQueryBuilder::PARAM_STR)),
			$qb->expr()->eq('comments.object_type', $qb->createNamedParameter(Application::COMMENT_ENTITY_TYPE, IQueryBuilder::PARAM_STR))
		));
		$qb->selectAlias('comments.id', 'comment_id');

		$tokenMatching = $qb->expr()->andX(
			...array_map(function (string $token) use ($qb) {
				return $qb->expr()->iLike(
					'comments.message',
					$qb->createNamedParameter('%' . $this->db->escapeLikeParameter($token) . '%', IQueryBuilder::PARAM_STR),
					IQueryBuilder::PARAM_STR
				);
			}, $query->getTextTokens())
		);
		$qb->andWhere(
			$tokenMatching
		);

		$qb->groupBy('comments.id', 'c.id');
		$qb->orderBy('comments.id', 'DESC');
		if ($limit !== null) {
			$qb->setMaxResults($limit);
		}
		if ($offset !== null) {
			$qb->andWhere($qb->expr()->lt('comments.id', $qb->createNamedParameter($offset, IQueryBuilder::PARAM_INT)));
		}

		$result = $qb->execute();
		$entities = $result->fetchAll();
		$result->closeCursor();
		return $entities;
	}

	private function extendQueryByFilter(IQueryBuilder $qb, SearchQuery $query) {
		$qb->andWhere($qb->expr()->eq('c.deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)));
		$qb->andWhere($qb->expr()->eq('s.deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)));
		$qb->innerJoin('s', 'deck_boards', 'b', $qb->expr()->eq('b.id', 's.board_id'));
		$qb->andWhere($qb->expr()->eq('b.deleted_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)));

		foreach ($query->getTitle() as $title) {
			$qb->andWhere($qb->expr()->iLike('c.title', $qb->createNamedParameter('%' . $this->db->escapeLikeParameter($title->getValue()) . '%', IQueryBuilder::PARAM_STR)));
		}

		foreach ($query->getDescription() as $description) {
			$qb->andWhere($qb->expr()->iLike('c.description', $qb->createNamedParameter('%' . $this->db->escapeLikeParameter($description->getValue()) . '%', IQueryBuilder::PARAM_STR)));
		}

		foreach ($query->getStack() as $stack) {
			$qb->andWhere($qb->expr()->iLike('s.title', $qb->createNamedParameter('%' . $this->db->escapeLikeParameter($stack->getValue()) . '%', IQueryBuilder::PARAM_STR)));
		}

		if (count($query->getTag())) {
			foreach ($query->getTag() as $index => $tag) {
				$qb->innerJoin('c', 'deck_assigned_labels', 'al' . $index, $qb->expr()->eq('c.id', 'al' . $index . '.card_id'));
				$qb->innerJoin('al'. $index, 'deck_labels', 'l' . $index, $qb->expr()->eq('al' . $index . '.label_id', 'l' . $index . '.id'));
				$qb->andWhere($qb->expr()->iLike('l' . $index . '.title', $qb->createNamedParameter('%' . $this->db->escapeLikeParameter($tag->getValue()) . '%', IQueryBuilder::PARAM_STR)));
			}
		}

		foreach ($query->getDuedate() as $duedate) {
			$dueDateColumn = $this->databaseType === 'sqlite3' ? $qb->createFunction('DATETIME(`c`.`duedate`)') : 'c.duedate';
			$date = $duedate->getValue();
			if ($date === "") {
				$qb->andWhere($qb->expr()->isNotNull('c.duedate'));
				continue;
			}
			$supportedFilters = ['overdue', 'today', 'week', 'month', 'none'];
			if (in_array($date, $supportedFilters, true)) {
				$currentDate = new DateTime();
				$rangeDate = new DateTime();
				if ($date === 'overdue') {
					$qb->andWhere($qb->expr()->lt($dueDateColumn, $this->dateTimeParameter($qb, $currentDate)));
				} elseif ($date === 'today') {
					$rangeDate = $rangeDate->add(new \DateInterval('P1D'));
					$qb->andWhere($qb->expr()->gte($dueDateColumn, $this->dateTimeParameter($qb, $currentDate)));
					$qb->andWhere($qb->expr()->lte($dueDateColumn, $this->dateTimeParameter($qb, $rangeDate)));
				} elseif ($date === 'week') {
					$rangeDate = $rangeDate->add(new \DateInterval('P7D'));
					$qb->andWhere($qb->expr()->gte($dueDateColumn, $this->dateTimeParameter($qb, $currentDate)));
					$qb->andWhere($qb->expr()->lte($dueDateColumn, $this->dateTimeParameter($qb, $rangeDate)));
				} elseif ($date === 'month') {
					$rangeDate = $rangeDate->add(new \DateInterval('P1M'));
					$qb->andWhere($qb->expr()->gte($dueDateColumn, $this->dateTimeParameter($qb, $currentDate)));
					$qb->andWhere($qb->expr()->lte($dueDateColumn, $this->dateTimeParameter($qb, $rangeDate)));
				} else {
					$qb->andWhere($qb->expr()->isNull('c.duedate'));
				}
			} else {
				try {
					$date = new DateTime($date);
					if ($duedate->getComparator() === SearchQuery::COMPARATOR_LESS) {
						$qb->andWhere($qb->expr()->lt($dueDateColumn, $this->dateTimeParameter($qb, $date)));
					} elseif ($duedate->getComparator() === SearchQuery::COMPARATOR_LESS_EQUAL) {
						// take the end of the day to include due dates at the same day (as datetime does't allow just setting the day)
						$date->setTime(23, 59, 59);
						$qb->andWhere($qb->expr()->lte($dueDateColumn, $this->dateTimeParameter($qb, $date)));
					} elseif ($duedate->getComparator() === SearchQuery::COMPARATOR_MORE) {
						// take the end of the day to exclude due dates at the same day (as datetime does't allow just setting the day)
						$date->setTime(23, 59, 59);
						$qb->andWhere($qb->expr()->gt($dueDateColumn, $this->dateTimeParameter($qb, $date)));
					} elseif ($duedate->getComparator() === SearchQuery::COMPARATOR_MORE_EQUAL) {
						$qb->andWhere($qb->expr()->gte($dueDateColumn, $this->dateTimeParameter($qb, $date)));
					}
				} catch (Exception $e) {
					// Invalid date, ignoring
				}
			}
		}

		if (count($query->getAssigned()) > 0) {
			foreach ($query->getAssigned() as $index => $assignment) {
				$qb->innerJoin('c', 'deck_assigned_users', 'au' . $index, $qb->expr()->eq('c.id', 'au' . $index . '.card_id'));
				$assignedQueryValue = $assignment->getValue();
				if ($assignedQueryValue === "") {
					$qb->andWhere($qb->expr()->isNotNull('au' . $index . '.participant'));
					continue;
				}
				$searchUsers = $this->userManager->searchDisplayName($assignment->getValue());
				$users = array_filter($searchUsers, function (IUser $user) use ($assignedQueryValue) {
					return (mb_strtolower($user->getDisplayName()) === mb_strtolower($assignedQueryValue) || $user->getUID() === $assignedQueryValue);
				});
				$groups = $this->groupManager->search($assignment->getValue());
				foreach ($searchUsers as $user) {
					$groups = array_merge($groups, $this->groupManager->getUserIdGroups($user->getUID()));
				}

				$assignmentSearches = [];
				$hasAssignedMatches = false;
				foreach ($users as $user) {
					$hasAssignedMatches = true;
					$assignmentSearches[] = $qb->expr()->andX(
						$qb->expr()->eq('au' . $index . '.participant', $qb->createNamedParameter($user->getUID(), IQueryBuilder::PARAM_STR)),
						$qb->expr()->eq('au' . $index . '.type', $qb->createNamedParameter(Assignment::TYPE_USER, IQueryBuilder::PARAM_INT))
					);
				}
				foreach ($groups as $group) {
					$hasAssignedMatches = true;
					$assignmentSearches[] = $qb->expr()->andX(
						$qb->expr()->eq('au' . $index . '.participant', $qb->createNamedParameter($group->getGID(), IQueryBuilder::PARAM_STR)),
						$qb->expr()->eq('au' . $index . '.type', $qb->createNamedParameter(Assignment::TYPE_GROUP, IQueryBuilder::PARAM_INT))
					);
				}
				if (!$hasAssignedMatches) {
					return [];
				}
				$qb->andWhere($qb->expr()->orX(...$assignmentSearches));
			}
		}
	}

	private function dateTimeParameter(IQueryBuilder $qb, DateTime $dateTime) {
		if ($this->databaseType === 'sqlite3') {
			return $qb->createFunction('DATETIME("' . $dateTime->format('Y-m-d\TH:i:s') . '")');
		}
		return $qb->createNamedParameter($dateTime, IQueryBuilder::PARAM_DATE);
	}
	
	

	public function searchRaw($boardIds, $term, $limit = null, $offset = null) {
		$qb = $this->queryCardsByBoards($boardIds)
			->select('s.board_id', 'board_id')
			->selectAlias('s.title', 'stack_title');
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
		$result = $qb->execute();
		$all = $result->fetchAll();
		$result->closeCursor();
		return $all;
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

	public function isOwner($userId, $cardId): bool {
		$sql = 'SELECT owner FROM `*PREFIX*deck_boards` WHERE `id` IN (SELECT board_id FROM `*PREFIX*deck_stacks` WHERE id IN (SELECT stack_id FROM `*PREFIX*deck_cards` WHERE id = ?))';
		$stmt = $this->db->prepare($sql);
		$stmt->bindParam(1, $cardId, \PDO::PARAM_INT);
		$stmt->execute();
		$row = $stmt->fetch();
		return ($row['owner'] === $userId);
	}

	public function findBoardId($id): ?int {
		if (!isset($this->cardBoardCache[$id])) {
			$sql = 'SELECT id FROM `*PREFIX*deck_boards` WHERE `id` IN (SELECT board_id FROM `*PREFIX*deck_stacks` WHERE id IN (SELECT stack_id FROM `*PREFIX*deck_cards` WHERE id = ?))';
			$stmt = $this->db->prepare($sql);
			$stmt->bindParam(1, $id, \PDO::PARAM_INT);
			$stmt->execute();
			$this->cardBoardCache[$id] = $stmt->fetchColumn() ?? null;
		}
		return $this->cardBoardCache[$id];
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
}
