<?php
/**
 * @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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

namespace OCA\Deck\Command;

use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\Service\BoardService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\IGroupManager;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UserExport extends Command {
	protected $boardService;
	protected $cardMapper;
	private $userManager;
	private $groupManager;
	private $assignedUsersMapper;

	public function __construct(BoardMapper $boardMapper,
								BoardService $boardService,
								StackMapper $stackMapper,
								CardMapper $cardMapper,
								AssignmentMapper $assignedUsersMapper,
								IUserManager $userManager,
								IGroupManager $groupManager) {
		parent::__construct();

		$this->cardMapper = $cardMapper;
		$this->boardService = $boardService;
		$this->stackMapper = $stackMapper;
		$this->assignedUsersMapper = $assignedUsersMapper;
		$this->boardMapper = $boardMapper;

		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
	}

	protected function configure() {
		$this
			->setName('deck:export')
			->setDescription('Export a JSON dump of user data')
			->addArgument(
				'user-id',
				InputArgument::REQUIRED,
				'User ID of the user'
			)
		;
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return void
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws \ReflectionException
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$userId = $input->getArgument('user-id');

		$this->boardService->setUserId($userId);
		$boards = $this->boardService->findAll();

		$data = [];
		foreach ($boards as $board) {
			$fullBoard = $this->boardMapper->find($board->getId(), true, true);
			$data[$board->getId()] = (array)$fullBoard->jsonSerialize();
			$stacks = $this->stackMapper->findAll($board->getId());
			foreach ($stacks as $stack) {
				$data[$board->getId()]['stacks'][] = (array)$stack->jsonSerialize();
				$cards = $this->cardMapper->findAllByStack($stack->getId());
				foreach ($cards as $card) {
					$fullCard = $this->cardMapper->find($card->getId());
					$assignedUsers = $this->assignedUsersMapper->findAll($card->getId());
					$fullCard->setAssignedUsers($assignedUsers);
					$data[$board->getId()]['stacks'][$stack->getId()]['cards'][] = (array)$fullCard->jsonSerialize();
				}
			}
		}
		$output->writeln(json_encode($data, JSON_PRETTY_PRINT));
	}
}
