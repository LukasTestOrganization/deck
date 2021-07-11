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

namespace OCA\Deck\Command\Helper;

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
use OCA\Deck\Service\BoardService;
use OCA\Deck\Service\LabelService;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class TrelloHelper extends ImportAbstract implements ImportInterface {
	/** @var BoardService */
	private $boardService;
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
	/** @var TrelloActions */
	private $trelloActions;
	/** @var Board */
	private $board;
	/** @var LabelService */
	private $labelService;
	/**
	 * Data object created from JSON of origin system
	 *
	 * @var \StdClass
	 */
	private $data;
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
	/** @var IUser */
	private $members = [];

	public function __construct(
		BoardService $boardService,
		LabelService $labelService,
		StackMapper $stackMapper,
		CardMapper $cardMapper,
		AssignmentMapper $assignmentMapper,
		AclMapper $aclMapper,
		IDBConnection $connection,
		IUserManager $userManager
	) {
		$this->boardService = $boardService;
		$this->labelService = $labelService;
		$this->stackMapper = $stackMapper;
		$this->cardMapper = $cardMapper;
		$this->assignmentMapper = $assignmentMapper;
		$this->aclMapper = $aclMapper;
		$this->connection = $connection;
		$this->userManager = $userManager;
	}

	public function validate(InputInterface $input, OutputInterface $output): void {
		$this->validateData($input, $output);
		$this->validateSettings($input, $output);
		$this->validateUsers();
		$this->validateOwner();
	}

	public function import(InputInterface $input, OutputInterface $output): void {
		$this->setUserId();
		$output->writeln('Importing board...');
		$this->importBoard();
		$output->writeln('Assign users to board...');
		$this->assignUsersToBoard();
		$output->writeln('Importing labels...');
		$this->importLabels();
		$output->writeln('Importing stacks...');
		$this->importStacks();
		$output->writeln('Importing cards...');
		$this->importCards();
	}

	private function assignUsersToBoard() {
		foreach ($this->members as $member) {
			$acl = new Acl();
			$acl->setBoardId($this->board->getId());
			$acl->setType(Acl::PERMISSION_TYPE_USER);
			$acl->setParticipant($member->getUid());
			$acl->setPermissionEdit(true);
			$acl->setPermissionShare($member->getUID() === $this->getSetting('owner')->getUID());
			$acl->setPermissionManage($member->getUID() === $this->getSetting('owner')->getUID());
			$this->aclMapper->insert($acl);
		}
	}

	private function validateData(InputInterface $input, OutputInterface $output): void {
		$filename = $input->getOption('data');
		if (!is_file($filename)) {
			$helper = $this->getCommand()->getHelper('question');
			$question = new Question(
				'Please inform a valid data json file: ',
				'data.json'
			);
			$question->setValidator(function ($answer) {
				if (!is_file($answer)) {
					throw new \RuntimeException(
						'Data file not found'
					);
				}
				return $answer;
			});
			$data = $helper->ask($input, $output, $question);
			$input->setOption('data', $data);
		}
		$this->data = json_decode(file_get_contents($filename));
		if (!$this->data) {
			$output->writeln('<error>Is not a json file: ' . $filename . '</error>');
			$this->validateData($input, $output);
		}
	}

	private function validateOwner(): void {
		$owner = $this->userManager->get($this->getSetting('owner'));
		if (!$owner) {
			throw new \LogicException('Owner "' . $this->getSetting('owner') . '" not found on Nextcloud. Check setting json.');
		}
		$this->setSetting('owner', $owner);
	}

	/**
	 * @return void
	 */
	private function validateUsers() {
		if (empty($this->getSetting('uidRelation'))) {
			return;
		}
		foreach ($this->getSetting('uidRelation') as $trelloUid => $nextcloudUid) {
			$user = array_filter($this->data->members, function ($u) use ($trelloUid) {
				return $u->username === $trelloUid;
			});
			if (!$user) {
				throw new \LogicException('Trello user ' . $trelloUid . ' not found in property "members" of json data');
			}
			if (!is_string($nextcloudUid)) {
				throw new \LogicException('User on setting uidRelation must be a string');
			}
			$this->getSetting('uidRelation')->$trelloUid = $this->userManager->get($nextcloudUid);
			if (!$this->getSetting('uidRelation')->$trelloUid) {
				throw new \LogicException('User on setting uidRelation not found: ' . $nextcloudUid);
			}
			$user = current($user);
			$this->members[$user->id] = $this->getSetting('uidRelation')->$trelloUid;
		}
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

	private function importCards(): void {
		$checklists = [];
		foreach ($this->data->checklists as $checklist) {
			$checklists[$checklist->idCard][$checklist->id] = $this->formulateChecklistText($checklist);
		}
		$this->data->checklists = $checklists;

		foreach ($this->data->cards as $trelloCard) {
			$card = new Card();
			$lastModified = \DateTime::createFromFormat('Y-m-d\TH:i:s.v\Z', $trelloCard->dateLastActivity);
			$card->setLastModified($lastModified->format('Y-m-d H:i:s'));
			if ($trelloCard->closed) {
				$card->setDeletedAt($lastModified->format('U'));
			}
			if ((count($trelloCard->idChecklists) !== 0)) {
				foreach ($this->data->checklists[$trelloCard->id] as $checklist) {
					$trelloCard->desc .= "\n" . $checklist;
				}
			}
			$this->appendAttachmentsToDescription($trelloCard);

			$card->setTitle($trelloCard->name);
			$card->setStackId($this->stacks[$trelloCard->idList]->getId());
			$card->setType('plain');
			$card->setOrder($trelloCard->idShort);
			$card->setOwner($this->getSetting('owner')->getUID());
			$card->setDescription($trelloCard->desc);
			if ($trelloCard->due) {
				$duedate = \DateTime::createFromFormat('Y-m-d\TH:i:s.v\Z', $trelloCard->due)
					->format('Y-m-d H:i:s');
				$card->setDuedate($duedate);
			}
			$card = $this->cardMapper->insert($card);
			$this->cards[$trelloCard->id] = $card;

			$this->associateCardToLabels($card, $trelloCard);
			$this->importComments($card, $trelloCard);
			$this->assignToMember($card, $trelloCard);
		}
	}

	private function appendAttachmentsToDescription($trelloCard) {
		if (empty($trelloCard->attachments)) {
			return;
		}
		$translations = $this->getSetting('translations');
		$attachmentsLabel = empty($translations->{'Attachments'}) ? 'Attachments' : $translations->{'Attachments'};
		$URLLabel = empty($translations->{'URL'}) ? 'URL' : $translations->{'URL'};
		$nameLabel = empty($translations->{'Name'}) ? 'Name' : $translations->{'Name'};
		$dateLabel = empty($translations->{'Date'}) ? 'Date' : $translations->{'Date'};
		$trelloCard->desc .= "\n\n## {$attachmentsLabel}\n";
		$trelloCard->desc .= "| $URLLabel | $nameLabel | $dateLabel |\n";
		$trelloCard->desc .= "|---|---|---|\n";
		foreach ($trelloCard->attachments as $attachment) {
			$name = $attachment->name === $attachment->url ? null : $attachment->name;
			$trelloCard->desc .= "| {$attachment->url} | {$name} | {$attachment->date} |\n";
		}
	}

	private function assignToMember(Card $card, $trelloCard) {
		foreach ($trelloCard->idMembers as $idMember) {
			$assignment = new Assignment();
			$assignment->setCardId($card->getId());
			$assignment->setParticipant($this->members[$idMember]->getUID());
			$assignment->setType(Assignment::TYPE_USER);
			$assignment = $this->assignmentMapper->insert($assignment);
		}
	}

	private function importComments(\OCP\AppFramework\Db\Entity $card, $trelloCard): void {
		$comments = array_filter(
			$this->data->actions,
			function ($a) use ($trelloCard) {
				return $a->type === 'commentCard' && $a->data->card->id === $trelloCard->id;
			}
		);
		foreach ($comments as $trelloComment) {
			if (!empty($this->getSetting('uidRelation')->{$trelloComment->memberCreator->username})) {
				$actor = $this->getSetting('uidRelation')->{$trelloComment->memberCreator->username}->getUID();
			} else {
				$actor = $this->getSetting('owner')->getUID();
			}
			$message = $this->replaceUsernames($trelloComment->data->text);
			$qb = $this->connection->getQueryBuilder();

			$values = [
				'parent_id' => $qb->createNamedParameter(0),
				'topmost_parent_id' => $qb->createNamedParameter(0),
				'children_count' => $qb->createNamedParameter(0),
				'actor_type' => $qb->createNamedParameter('users'),
				'actor_id' => $qb->createNamedParameter($actor),
				'message' => $qb->createNamedParameter($message),
				'verb' => $qb->createNamedParameter('comment'),
				'creation_timestamp' => $qb->createNamedParameter(
					\DateTime::createFromFormat('Y-m-d\TH:i:s.v\Z', $trelloComment->date)
					->format('Y-m-d H:i:s')
				),
				'latest_child_timestamp' => $qb->createNamedParameter(null),
				'object_type' => $qb->createNamedParameter('deckCard'),
				'object_id' => $qb->createNamedParameter($card->getId()),
			];

			$qb->insert('comments')
				->values($values)
				->execute();
		}
	}

	private function replaceUsernames($text) {
		foreach ($this->getSetting('uidRelation') as $trello => $nextcloud) {
			$text = str_replace($trello, $nextcloud->getUID(), $text);
		}
		return $text;
	}

	private function associateCardToLabels(\OCP\AppFramework\Db\Entity $card, $trelloCard): void {
		foreach ($trelloCard->labels as $label) {
			$this->cardMapper->assignLabel(
				$card->getId(),
				$this->labels[$label->id]->getId()
			);
		}
	}

	private function importStacks(): void {
		$this->stacks = [];
		foreach ($this->data->lists as $order => $list) {
			$stack = new Stack();
			if ($list->closed) {
				$stack->setDeletedAt(time());
			}
			$stack->setTitle($list->name);
			$stack->setBoardId($this->board->getId());
			$stack->setOrder($order + 1);
			$stack = $this->stackMapper->insert($stack);
			$this->stacks[$list->id] = $stack;
		}
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

	private function importBoard(): void {
		$this->board = $this->boardService->create(
			$this->data->name,
			$this->getSetting('owner')->getUID(),
			$this->getSetting('color')
		);
	}

	private function importLabels(): void {
		$this->labels = [];
		foreach ($this->data->labels as $label) {
			if (empty($label->name)) {
				$labelTitle = 'Unnamed ' . $label->color . ' label';
			} else {
				$labelTitle = $label->name;
			}
			$newLabel = $this->labelService->create(
				$labelTitle,
				$this->translateColor($label->color),
				$this->board->getId()
			);
			$this->labels[$label->id] = $newLabel;
		}
	}

	private function setUserId(): void {
		if (!property_exists($this->labelService, 'permissionService')) {
			return;
		}
		$propertyPermissionService = new \ReflectionProperty($this->labelService, 'permissionService');
		$propertyPermissionService->setAccessible(true);
		$permissionService = $propertyPermissionService->getValue($this->labelService);

		if (!property_exists($permissionService, 'userId')) {
			return;
		}

		$propertyUserId = new \ReflectionProperty($permissionService, 'userId');
		$propertyUserId->setAccessible(true);
		$propertyUserId->setValue($permissionService, $this->getSetting('owner')->getUID());
	}
}
