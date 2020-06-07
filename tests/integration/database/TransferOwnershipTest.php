<?php

namespace OCA\Deck\Service;

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AssignedUsersMapper;
use OCA\Deck\Db\Board;

/**
 * @group DB
 * @coversDefaultClass OCA\Deck\Service\BoardService
 */
class AssignedUsersMapperTest extends \Test\TestCase {
	private const TEST_OWNER = 'test-share-user1';
	private const TEST_NEW_OWNER = 'target';
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
		$backend->createUser(self::TEST_OWNER, self::TEST_OWNER);
		$backend->createUser(self::TEST_NEW_OWNER, self::TEST_NEW_OWNER);
		// create group
		$groupBackend = new \Test\Util\Group\Dummy();
		$groupBackend->createGroup(self::TEST_GROUP);
		$groupBackend->addToGroup(self::TEST_OWNER, self::TEST_GROUP);
		\OC::$server->getGroupManager()->addBackend($groupBackend);
	}

	public function setUp(): void {
		parent::setUp();
		\OC::$server->getUserSession()->login(self::TEST_OWNER, self::TEST_OWNER);
		$this->boardService = \OC::$server->query(BoardService::class);
		$this->stackService = \OC::$server->query(StackService::class);
		$this->cardService = \OC::$server->query(CardService::class);
		$this->assignmentService = \OC::$server->query(AssignmentService::class);
		$this->assignedUsersMapper = \OC::$server->query(AssignedUsersMapper::class);
		$this->createBoardWithExampleData();
	}

	public function createBoardWithExampleData() {
		$stacks = [];
		$board = $this->boardService->create('Test', self::TEST_OWNER, '000000');
		$id = $board->getId();
        $this->boardService->addAcl($id, Acl::PERMISSION_TYPE_USER, self::TEST_OWNER, true, true, true);
        $this->boardService->addAcl($id, Acl::PERMISSION_TYPE_GROUP, self::TEST_GROUP, true, true, true);
		$stacks[] = $this->stackService->create('Stack A', $id, 1);
		$stacks[] = $this->stackService->create('Stack B', $id, 1);
		$stacks[] = $this->stackService->create('Stack C', $id, 1);
		$cards[] = $this->cardService->create('Card 1', $stacks[0]->getId(), 'text', 0, self::TEST_OWNER);
		$cards[] = $this->cardService->create('Card 2', $stacks[0]->getId(), 'text', 0, self::TEST_OWNER);
        $this->assignmentService->assignUser($cards[0]->getId(), self::TEST_OWNER);
        $this->board = $board;
		$this->cards = $cards;
		$this->stacks = $stacks;
	}

    /**
     * @covers ::transferOwnership
     */
    public function testTransferBoardOwnership()
    {
        $this->boardService->transferOwnership(self::TEST_OWNER, self::TEST_NEW_OWNER);
        $board = $this->boardService->find($this->board->getId());
        $boardOwner = $board->getOwner();
        $this->assertEquals(self::TEST_NEW_OWNER, $boardOwner);
    }

    /**
     * @covers ::transferOwnership
     */
    public function testTransferACLOwnership()
    {
        $this->boardService->transferOwnership(self::TEST_OWNER, self::TEST_NEW_OWNER);
        $board = $this->boardService->find($this->board->getId());
        $acl = $board->getAcl();
        $isTargetInAcl = (bool)array_filter($acl, function ($item) {
            return $item->getParticipant() === self::TEST_NEW_OWNER && $item->getType() === Acl::PERMISSION_TYPE_USER;
        });
        $this->assertTrue($isTargetInAcl);
    }

    /**
     * @covers ::transferOwnership
     */
    public function testNoTransferAclOwnershipIfGroupType()
    {
        $this->boardService->transferOwnership(self::TEST_OWNER, self::TEST_NEW_OWNER);
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
    public function testTransferCardOwnership()
    {
        $this->boardService->transferOwnership(self::TEST_OWNER, self::TEST_NEW_OWNER);
        $card = $this->cardService->find($this->cards[0]->getId());
        $cardOwner = $card->getOwner();
        $this->assertEquals(self::TEST_NEW_OWNER, $cardOwner);
    }

    /**
     * @covers ::transferOwnership
     */
    public function testReassignCardToNewOwner()
    {
        $this->boardService->transferOwnership(self::TEST_OWNER, self::TEST_NEW_OWNER);
        $assignedUsers = $this->assignedUsersMapper->find($this->cards[0]->getId());
        $participantsUIDs = [];
        foreach ($assignedUsers as $user) {
            $participantsUIDs[] = $user->getParticipant();
        }
        $this->assertContains(self::TEST_NEW_OWNER, $participantsUIDs);
        $this->assertNotContains(self::TEST_OWNER, $participantsUIDs);
    }

	public function tearDown(): void {
		$this->boardService->deleteForce($this->board->getId());
		parent::tearDown();
	}
}
