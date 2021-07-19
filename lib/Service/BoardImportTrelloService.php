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
use OCA\Deck\BadRequestException;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\Assignment;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\Label;
use OCA\Deck\Db\Stack;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;

class BoardImportTrelloService extends ABoardImportService {
	/** @var IUserManager */
	private $userManager;
	/** @var IL10N */
	private $l10n;
	/** @var IUser[] */
	private $members = [];

	public function __construct(
		IUserManager $userManager,
		IL10N $l10n
	) {
		$this->userManager = $userManager;
		$this->l10n = $l10n;
	}

	public function bootstrap(): void {
		$this->validateUsers();
	}

	public function validateUsers(): void {
		if (empty($this->getImportService()->getConfig('uidRelation'))) {
			return;
		}
		foreach ($this->getImportService()->getConfig('uidRelation') as $trelloUid => $nextcloudUid) {
			$user = array_filter($this->getImportService()->getData()->members, function (\stdClass $u) use ($trelloUid) {
				return $u->username === $trelloUid;
			});
			if (!$user) {
				throw new \LogicException('Trello user ' . $trelloUid . ' not found in property "members" of json data');
			}
			if (!is_string($nextcloudUid) && !is_numeric($nextcloudUid)) {
				throw new \LogicException('User on setting uidRelation is invalid');
			}
			$nextcloudUid = (string) $nextcloudUid;
			$this->getImportService()->getConfig('uidRelation')->$trelloUid = $this->userManager->get($nextcloudUid);
			if (!$this->getImportService()->getConfig('uidRelation')->$trelloUid) {
				throw new \LogicException('User on setting uidRelation not found: ' . $nextcloudUid);
			}
			$user = current($user);
			$this->members[$user->id] = $this->getImportService()->getConfig('uidRelation')->$trelloUid;
		}
	}

	public function getCardAssignments(): array {
		$assignments = [];
		foreach ($this->getImportService()->getData()->cards as $trelloCard) {
			foreach ($trelloCard->idMembers as $idMember) {
				if (empty($this->members[$idMember])) {
					continue;
				}
				$assignment = new Assignment();
				$assignment->setCardId($this->cards[$trelloCard->id]->getId());
				$assignment->setParticipant($this->members[$idMember]->getUID());
				$assignment->setType(Assignment::TYPE_USER);
				$assignments[$trelloCard->id][] = $assignment;
			}
		}
		return $assignments;
	}

	public function getComments(): array {
		$comments = [];
		foreach ($this->getImportService()->getData()->cards as $trelloCard) {
			$values = array_filter(
				$this->getImportService()->getData()->actions,
				function (\stdClass $a) use ($trelloCard) {
					return $a->type === 'commentCard' && $a->data->card->id === $trelloCard->id;
				}
			);
			$keys = array_map(function (\stdClass $c): string {
				return $c->id;
			}, $values);
			$trelloComments = array_combine($keys, $values);
			foreach ($trelloComments as $commentId => $trelloComment) {
				$comment = new Comment();
				if (!empty($this->getImportService()->getConfig('uidRelation')->{$trelloComment->memberCreator->username})) {
					$actor = $this->getImportService()->getConfig('uidRelation')->{$trelloComment->memberCreator->username}->getUID();
				} else {
					$actor = $this->getImportService()->getConfig('owner')->getUID();
				}
				$comment
					->setActor('users', $actor)
					->setMessage($this->replaceUsernames($trelloComment->data->text), 0)
					->setCreationDateTime(
						\DateTime::createFromFormat('Y-m-d\TH:i:s.v\Z', $trelloComment->date)
					);
				$cardId = $this->cards[$trelloCard->id]->getId();
				$comments[$cardId][$commentId] = $comment;
			}
		}
		return $comments;
	}

	public function getCardLabelAssignment(): array {
		$cardsLabels = [];
		foreach ($this->getImportService()->getData()->cards as $trelloCard) {
			foreach ($trelloCard->labels as $label) {
				$cardId = $this->cards[$trelloCard->id]->getId();
				$labelId = $this->labels[$label->id]->getId();
				$cardsLabels[$cardId][] = $labelId;
			}
		}
		return $cardsLabels;
	}

	public function getBoard(): Board {
		$board = $this->getImportService()->getBoard();
		if (empty($this->getImportService()->getData()->name)) {
			throw new BadRequestException('Invalid name of board');
		}
		$board->setTitle($this->getImportService()->getData()->name);
		$board->setOwner($this->getImportService()->getConfig('owner')->getUID());
		$board->setColor($this->getImportService()->getConfig('color'));
		return $board;
	}

	/**
	 * @return Label[]
	 */
	public function getLabels(): array {
		foreach ($this->getImportService()->getData()->labels as $trelloLabel) {
			$label = new Label();
			if (empty($trelloLabel->name)) {
				$label->setTitle('Unnamed ' . $trelloLabel->color . ' label');
			} else {
				$label->setTitle($trelloLabel->name);
			}
			$label->setColor($this->translateColor($trelloLabel->color));
			$label->setBoardId($this->getImportService()->getBoard()->getId());
			$this->labels[$trelloLabel->id] = $label;
		}
		return $this->labels;
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

	/**
	 * @return Card[]
	 */
	public function getCards(): array {
		$checklists = [];
		foreach ($this->getImportService()->getData()->checklists as $checklist) {
			$checklists[$checklist->idCard][$checklist->id] = $this->formulateChecklistText($checklist);
		}
		$this->getImportService()->getData()->checklists = $checklists;

		$cards = [];
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
			$cardsOnStack = $this->stacks[$trelloCard->idList]->getCards();
			$cardsOnStack[] = $card;
			$this->stacks[$trelloCard->idList]->setCards($cardsOnStack);
			$card->setType('plain');
			$card->setOrder($trelloCard->idShort);
			$card->setOwner($this->getImportService()->getConfig('owner')->getUID());
			$card->setDescription($trelloCard->desc);
			if ($trelloCard->due) {
				$duedate = \DateTime::createFromFormat('Y-m-d\TH:i:s.v\Z', $trelloCard->due)
					->format('Y-m-d H:i:s');
				$card->setDuedate($duedate);
			}
			$cards[$trelloCard->id] = $card;
		}
		return $cards;
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

	private function translateColor(string $color): string {
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

	private function replaceUsernames(string $text): string {
		foreach ($this->getImportService()->getConfig('uidRelation') as $trello => $nextcloud) {
			$text = str_replace($trello, $nextcloud->getUID(), $text);
		}
		return $text;
	}

	private function checklistItem(\stdClass $item): string {
		if (($item->state == 'incomplete')) {
			$string_start = '- [ ]';
		} else {
			$string_start = '- [x]';
		}
		$check_item_string = $string_start . ' ' . $item->name . "\n";
		return $check_item_string;
	}

	private function formulateChecklistText(\stdClass $checklist): string {
		$checklist_string = "\n\n## {$checklist->name}\n";
		foreach ($checklist->checkItems as $item) {
			$checklist_item_string = $this->checklistItem($item);
			$checklist_string = $checklist_string . "\n" . $checklist_item_string;
		}
		return $checklist_string;
	}

	private function appendAttachmentsToDescription(\stdClass $trelloCard): void {
		if (empty($trelloCard->attachments)) {
			return;
		}
		$trelloCard->desc .= "\n\n## {$this->l10n->t('Attachments')}\n";
		$trelloCard->desc .= "| {$this->l10n->t('File')} | {$this->l10n->t('date')} |\n";
		$trelloCard->desc .= "|---|---\n";
		foreach ($trelloCard->attachments as $attachment) {
			$name = $attachment->name === $attachment->url ? null : $attachment->name;
			$trelloCard->desc .= "| [{$name}]({$attachment->url}) | {$attachment->date} |\n";
		}
	}
}
