<?php

namespace OCA\Deck\Service;

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AssignedUsers;
use OCA\Deck\Db\AssignedUsersMapper;
use OCA\Deck\Db\Board;

/**
 * @group DB
 * @coversDefaultClass OCA\Deck\Service\BoardService
 */
class TransferOwnershipTest extends \Test\TestCase {
	private const TEST_USER_1 = 'test-share-user1';
	private const TEST_USER_2 = 'test-user2';
	private const TEST_USER_3 = 'test-user3';
	private const TEST_GROUP = 'test-share-user1';

	/** @var BoardService */
	protected $boardService;
	/** @var CardService */
	protected $cardService;
	/** @var StackService */
	protected $stackService;
	/** @var AssignedUsersMapper */
	protected $assignedUsersMapper;
	/** @var AssignmentService */
	private $assignmentService;
	/** @var Board */
	private $board;
	private $cards;
	private $stacks;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		$backend = new \Test\Util\User\Dummy();
		\OC_User::useBackend($backend);
		\OC::$server->getUserManager()->registerBackend($backend);
		$backend->createUser(self::TEST_USER_1, self::TEST_USER_1);
		$backend->createUser(self::TEST_USER_2, self::TEST_USER_2);
		$backend->createUser(self::TEST_USER_3, self::TEST_USER_3);
		// create group
		$groupBackend = new \Test\Util\Group\Dummy();
		$groupBackend->createGroup(self::TEST_GROUP);
		$groupBackend->addToGroup(self::TEST_USER_1, self::TEST_GROUP);
		\OC::$server->getGroupManager()->addBackend($groupBackend);
	}

	public function setUp(): void {
		parent::setUp();
		\OC::$server->getUserSession()->login(self::TEST_USER_1, self::TEST_USER_1);
		$this->boardService = \OC::$server->query(BoardService::class);
		$this->stackService = \OC::$server->query(StackService::class);
		$this->cardService = \OC::$server->query(CardService::class);
		$this->assignmentService = \OC::$server->query(AssignmentService::class);
		$this->assignedUsersMapper = \OC::$server->query(AssignedUsersMapper::class);
		$this->createBoardWithExampleData();
	}

	public function createBoardWithExampleData() {
		$stacks = [];
		$board = $this->boardService->create('Test', self::TEST_USER_1, '000000');
		$id = $board->getId();
		$this->boardService->addAcl($id, Acl::PERMISSION_TYPE_USER, self::TEST_USER_1, true, true, true);
		$this->boardService->addAcl($id, Acl::PERMISSION_TYPE_GROUP, self::TEST_GROUP, true, true, true);
		$this->boardService->addAcl($id, Acl::PERMISSION_TYPE_USER, self::TEST_USER_3, false, true, false);
        $stacks[] = $this->stackService->create('Stack A', $id, 1);
		$stacks[] = $this->stackService->create('Stack B', $id, 1);
		$stacks[] = $this->stackService->create('Stack C', $id, 1);
		$cards[] = $this->cardService->create('Card 1', $stacks[0]->getId(), 'text', 0, self::TEST_USER_1);
		$cards[] = $this->cardService->create('Card 2', $stacks[0]->getId(), 'text', 0, self::TEST_USER_1);
		$this->assignmentService->assignUser($cards[0]->getId(), self::TEST_USER_1);
		$this->board = $board;
		$this->cards = $cards;
		$this->stacks = $stacks;
	}

	/**
	 * @covers ::transferOwnership
	 */
	public function testTransferBoardOwnership() {
		$this->boardService->transferOwnership(self::TEST_USER_1, self::TEST_USER_2);
		$board = $this->boardService->find($this->board->getId());
		$boardOwner = $board->getOwner();
		$this->assertEquals(self::TEST_USER_2, $boardOwner);
	}

	/**
	 * @covers ::transferOwnership
	 */
	public function testTransferACLOwnership() {
		$this->boardService->transferOwnership(self::TEST_USER_1, self::TEST_USER_2);
		$board = $this->boardService->find($this->board->getId());
		$acl = $board->getAcl();
		$isTargetInAcl = (bool)array_filter($acl, function ($item) {
			return $item->getParticipant() === self::TEST_USER_2 && $item->getType() === Acl::PERMISSION_TYPE_USER;
		});
		$this->assertTrue($isTargetInAcl);
	}

	/**
	 * @covers ::transferOwnership
	 */
	public function testNoTransferAclOwnershipIfGroupType() {
		$this->boardService->transferOwnership(self::TEST_USER_1, self::TEST_USER_2);
		$board = $this->boardService->find($this->board->getId());
		$acl = $board->getAcl();
		$isGroupInAcl = (bool)array_filter($acl, function ($item) {
			return $item->getParticipant() === self::TEST_GROUP && $item->getType() === Acl::PERMISSION_TYPE_GROUP;
		});
		$this->assertTrue($isGroupInAcl);
	}
	/**
	 * @covers ::transferOwnership
	 */
	public function testTransferCardOwnership() {
		$this->boardService->transferOwnership(self::TEST_USER_1, self::TEST_USER_2);
		$card = $this->cardService->find($this->cards[0]->getId());
		$cardOwner = $card->getOwner();
		$this->assertEquals(self::TEST_USER_2, $cardOwner);
	}

	/**
	 * @covers ::transferOwnership
	 */
	public function testReassignCardToNewOwner() {
		$this->boardService->transferOwnership(self::TEST_USER_1, self::TEST_USER_2);
		$assignedUsers = $this->assignedUsersMapper->find($this->cards[0]->getId());
		$participantsUIDs = [];
		foreach ($assignedUsers as $user) {
			$participantsUIDs[] = $user->getParticipant();
		}
		$this->assertContains(self::TEST_USER_2, $participantsUIDs);
		$this->assertNotContains(self::TEST_USER_1, $participantsUIDs);
	}

    /**
     * @covers ::transferOwnership
     */
    public function testReassignCardToNewParticipantOnlyIfParticipantHasUserType() {
        $this->assignmentService->assignUser($this->cards[1]->getId(), self::TEST_USER_1, AssignedUsers::TYPE_GROUP);
        $this->boardService->transferOwnership(self::TEST_USER_1, self::TEST_USER_2);
        $assignedUsers = $this->assignedUsersMapper->find($this->cards[1]->getId());
        $participantsUIDs = [];
        foreach ($assignedUsers as $user) {
            $participantsUIDs[] = $user->getParticipant();
        }
        $this->assertContains(self::TEST_USER_1, $participantsUIDs);
        $this->assertNotContains(self::TEST_USER_2, $participantsUIDs);
    }

    /**
     * @covers ::transferOwnership
     */
    public function testTargetAlreadyParticipantOfBoard() {
        $this->expectNotToPerformAssertions();
        $this->boardService->transferOwnership(self::TEST_USER_1, self::TEST_USER_3);
    }

    /**
     * @covers ::transferOwnership
     */
    public function testDontRemoveTargetFromAcl() {
        $this->boardService->transferOwnership(self::TEST_USER_2, self::TEST_USER_3);
        $board = $this->boardService->find($this->board->getId());
        $acl = $board->getAcl();
        $isOwnerInAcl = (bool)array_filter($acl, function ($item) {
            return $item->getParticipant() === self::TEST_USER_3 && $item->getType() === Acl::PERMISSION_TYPE_USER;
        });
        $this->assertTrue($isOwnerInAcl);
    }

    /**
     * @covers ::transferOwnership
     */
    public function testMergePermissions() {
        $this->boardService->addAcl($this->board->getId(), Acl::PERMISSION_TYPE_USER, self::TEST_USER_2, true, false, true);
        $this->boardService->transferOwnership(self::TEST_USER_2, self::TEST_USER_3);
        $board = $this->boardService->find($this->board->getId());
        $acl = $board->getAcl();
        $isMerged = (bool)array_filter($acl, function ($item) {
            return $item->getParticipant() === self::TEST_USER_1
                && $item->getType() === Acl::PERMISSION_TYPE_USER
                && $item->getPermission(Acl::PERMISSION_EDIT)
                && $item->getPermission(Acl::PERMISSION_SHARE)
                && $item->getPermission(Acl::PERMISSION_MANAGE);
        });
        $this->assertTrue($isMerged);
    }

    /**
     * @covers ::transferOwnership
     */
    public function testTargetAlreadyParticipantOfCard() {
        $this->expectNotToPerformAssertions();
        $this->assignmentService->assignUser($this->cards[0]->getId(), self::TEST_USER_3, AssignedUsers::TYPE_USER);
        $this->boardService->transferOwnership(self::TEST_USER_1, self::TEST_USER_3);
    }

	public function tearDown(): void {
		$this->boardService->deleteForce($this->board->getId());
		parent::tearDown();
	}
}
