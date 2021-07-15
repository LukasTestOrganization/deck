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

use OC\Comments\Comment;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\Assignment;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\Label;
use OCA\Deck\Db\Stack;
use OCA\Deck\Db\StackMapper;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;

class BoardImportTrelloService extends ABoardImportService {
	/** @var LabelService */
	private $labelService;
	/** @var StackMapper */
	private $stackMapper;
	/** @var CardMapper */
	private $cardMapper;
	/** @var AssignmentMapper */
	private $assignmentMapper;
	/** @var AclMapper */
	private $aclMapper;
	/** @var IDBConnection */
	private $connection;
	/** @var IUserManager */
	private $userManager;
	/** @var IL10N */
	private $l10n;
	/**
	 * Array of stacks
	 *
	 * @var Stack[]
	 */
	private $stacks = [];
	/**
	 * Array of labels
	 *
	 * @var Label[]
	 */
	private $labels = [];
	/** @var Card[] */
	private $cards = [];
	/** @var IUser[] */
	private $members = [];

	public function __construct(
		BoardService $boardService,
		LabelService $labelService,
		StackMapper $stackMapper,
		CardMapper $cardMapper,
		AssignmentMapper $assignmentMapper,
		AclMapper $aclMapper,
		IDBConnection $connection,
		IUserManager $userManager,
		IL10N $l10n
	) {
		$this->boardService = $boardService;
		$this->labelService = $labelService;
		$this->stackMapper = $stackMapper;
		$this->cardMapper = $cardMapper;
		$this->assignmentMapper = $assignmentMapper;
		$this->aclMapper = $aclMapper;
		$this->connection = $connection;
		$this->userManager = $userManager;
		$this->l10n = $l10n;
	}

	public function validate(): ABoardImportService {
		$this->boardImportTrelloService->validateOwner();
		$this->boardImportTrelloService->validateUsers();
		return $this;
	}

	/**
	 * @return ABoardImportService
	 */
	public function validateUsers(): self {
		if (empty($this->getImportService()->getConfig('uidRelation'))) {
			return $this;
		}
		foreach ($this->getImportService()->getConfig('uidRelation') as $trelloUid => $nextcloudUid) {
			$user = array_filter($this->getImportService()->getData()->members, function ($u) use ($trelloUid) {
				return $u->username === $trelloUid;
			});
			if (!$user) {
				throw new \LogicException('Trello user ' . $trelloUid . ' not found in property "members" of json data');
			}
			if (!is_string($nextcloudUid)) {
				throw new \LogicException('User on setting uidRelation must be a string');
			}
			$this->getImportService()->getConfig('uidRelation')->$trelloUid = $this->userManager->get($nextcloudUid);
			if (!$this->getImportService()->getConfig('uidRelation')->$trelloUid) {
				throw new \LogicException('User on setting uidRelation not found: ' . $nextcloudUid);
			}
			$user = current($user);
			$this->members[$user->id] = $this->getImportService()->getConfig('uidRelation')->$trelloUid;
		}
		return $this;
	}

	/**
	 * @return Acl[]
	 */
	public function getAclList(): array {
		$return = [];
		foreach ($this->members as $member) {
			if ($member->getUID() === $this->getImportService()->getConfig('owner')->getUID()) {
				continue;
			}
			$acl = new Acl();
			$acl->setBoardId($this->getImportService()->getBoard()->getId());
			$acl->setType(Acl::PERMISSION_TYPE_USER);
			$acl->setParticipant($member->getUID());
			$acl->setPermissionEdit(false);
			$acl->setPermissionShare(false);
			$acl->setPermissionManage(false);
			$return[] = $acl;
		}
		return $return;
	}

	private function checklistItem($item): string {
		if (($item->state == 'incomplete')) {
			$string_start = '- [ ]';
		} else {
			$string_start = '- [x]';
		}
		$check_item_string = $string_start . ' ' . $item->name . "\n";
		return $check_item_string;
	}

	private function formulateChecklistText($checklist): string {
		$checklist_string = "\n\n## {$checklist->name}\n";
		foreach ($checklist->checkItems as $item) {
			$checklist_item_string = $this->checklistItem($item);
			$checklist_string = $checklist_string . "\n" . $checklist_item_string;
		}
		return $checklist_string;
	}

	/**
	 * @return Card[]
	 */
	public function getCards(): array {
		$checklists = [];
		foreach ($this->getImportService()->getData()->checklists as $checklist) {
			$checklists[$checklist->idCard][$checklist->id] = $this->formulateChecklistText($checklist);
		}
		$this->getImportService()->getData()->checklists = $checklists;

		foreach ($this->getImportService()->getData()->cards as $trelloCard) {
			$card = new Card();
			$lastModified = \DateTime::createFromFormat('Y-m-d\TH:i:s.v\Z', $trelloCard->dateLastActivity);
			$card->setLastModified($lastModified->format('Y-m-d H:i:s'));
			if ($trelloCard->closed) {
				$card->setDeletedAt($lastModified->format('U'));
			}
			if ((count($trelloCard->idChecklists) !== 0)) {
				foreach ($this->getImportService()->getData()->checklists[$trelloCard->id] as $checklist) {
					$trelloCard->desc .= "\n" . $checklist;
				}
			}
			$this->appendAttachmentsToDescription($trelloCard);

			$card->setTitle($trelloCard->name);
			$card->setStackId($this->stacks[$trelloCard->idList]->getId());
			$card->setType('plain');
			$card->setOrder($trelloCard->idShort);
			$card->setOwner($this->getImportService()->getConfig('owner')->getUID());
			$card->setDescription($trelloCard->desc);
			if ($trelloCard->due) {
				$duedate = \DateTime::createFromFormat('Y-m-d\TH:i:s.v\Z', $trelloCard->due)
					->format('Y-m-d H:i:s');
				$card->setDuedate($duedate);
			}
			$this->cards[$trelloCard->id] = $card;
			$this->cardsTrello[$trelloCard->id] = $trelloCard;
			// $this->assignToMember($trelloCard);
		}
		return $this->cards;
	}

	public function updateCard($cardTrelloId, Card $card): self {
		$this->cards[$cardTrelloId] = $card;
		return $this;
	}

	/**
	 * @return ABoardImportService
	 */
	private function appendAttachmentsToDescription($trelloCard) {
		if (empty($trelloCard->attachments)) {
			return;
		}
		$trelloCard->desc .= "\n\n## {$this->l10n->t('Attachments')}\n";
		$trelloCard->desc .= "| {$this->l10n->t('URL')} | {$this->l10n->t('Name')} | {$this->l10n->t('date')} |\n";
		$trelloCard->desc .= "|---|---|---|\n";
		foreach ($trelloCard->attachments as $attachment) {
			$name = $attachment->name === $attachment->url ? null : $attachment->name;
			$trelloCard->desc .= "| {$attachment->url} | {$name} | {$attachment->date} |\n";
		}
		return $this;
	}

	private function assignToMember(Card $card, $trelloCard): ABoardImportService {
		foreach ($trelloCard->idMembers as $idMember) {
			$assignment = new Assignment();
			$assignment->setCardId($card->getId());
			$assignment->setParticipant($this->members[$idMember]->getUID());
			$assignment->setType(Assignment::TYPE_USER);
			$assignment = $this->assignmentMapper->insert($assignment);
		}
		return $this;
	}

	public function importComments(): ABoardImportService {
		foreach ($this->getImportService()->getData()->cards as $trelloCard) {
			$comments = array_filter(
				$this->getImportService()->getData()->actions,
				function ($a) use ($trelloCard) {
					return $a->type === 'commentCard' && $a->data->card->id === $trelloCard->id;
				}
			);
			foreach ($comments as $trelloComment) {
				if (!empty($this->getImportService()->getConfig('uidRelation')->{$trelloComment->memberCreator->username})) {
					$actor = $this->getImportService()->getConfig('uidRelation')->{$trelloComment->memberCreator->username}->getUID();
				} else {
					$actor = $this->getImportService()->getConfig('owner')->getUID();
				}
				$comment = new Comment();
				$comment
					->setActor('users', $actor)
					->setMessage($this->replaceUsernames($trelloComment->data->text), 0)
					->setCreationDateTime(
						\DateTime::createFromFormat('Y-m-d\TH:i:s.v\Z', $trelloComment->date)
					);
				$this->getImportService()->insertComment(
					$this->cards[$trelloCard->id]->getId(),
					$comment
				);
			}
		}
		return $this;
	}

	private function replaceUsernames($text) {
		foreach ($this->getImportService()->getConfig('uidRelation') as $trello => $nextcloud) {
			$text = str_replace($trello, $nextcloud->getUID(), $text);
		}
		return $text;
	}

	public function assignCardsToLabels(): self {
		foreach ($this->getImportService()->getData()->cards as $trelloCard) {
			foreach ($trelloCard->labels as $label) {
				$this->getImportService()->assignCardToLabel(
					$this->cards[$trelloCard->id]->getId(),
					$this->labels[$label->id]->getId()
				);
			}
		}
		return $this;
	}

	/**
	 * @return Stack[]
	 */
	public function getStacks(): array {
		$return = [];
		foreach ($this->getImportService()->getData()->lists as $order => $list) {
			$stack = new Stack();
			if ($list->closed) {
				$stack->setDeletedAt(time());
			}
			$stack->setTitle($list->name);
			$stack->setBoardId($this->getImportService()->getBoard()->getId());
			$stack->setOrder($order + 1);
			$return[$list->id] = $stack;
		}
		return $return;
	}

	public function updateStack($id, $stack): self {
		$this->stacks[$id] = $stack;
		return $this;
	}

	private function translateColor($color): string {
		switch ($color) {
			case 'red':
				return 'ff0000';
			case 'yellow':
				return 'ffff00';
			case 'orange':
				return 'ff6600';
			case 'green':
				return '00ff00';
			case 'purple':
				return '9900ff';
			case 'blue':
				return '0000ff';
			case 'sky':
				return '00ccff';
			case 'lime':
				return '00ff99';
			case 'pink':
				return 'ff66cc';
			case 'black':
				return '000000';
			default:
				return 'ffffff';
		}
	}

	public function getBoard(): Board {
		$board = new Board();
		$board->setTitle($this->getImportService()->getData()->name);
		$board->setOwner($this->getImportService()->getConfig('owner')->getUID());
		$board->setColor($this->getImportService()->getConfig('color'));
		return $board;
	}

	public function importLabels(): self {
		foreach ($this->getImportService()->getData()->labels as $label) {
			if (empty($label->name)) {
				$labelTitle = 'Unnamed ' . $label->color . ' label';
			} else {
				$labelTitle = $label->name;
			}
			$newLabel = $this->getImportService()->createLabel(
				$labelTitle,
				$this->translateColor($label->color),
				$this->getImportService()->getBoard()->getId()
			);
			$this->labels[$label->id] = $newLabel;
		}
		return $this;
	}
}
