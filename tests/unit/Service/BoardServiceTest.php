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

namespace OCA\Deck\Service;

use OC\L10N\L10N;
use OCA\Deck\Activity\ActivityManager;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\Assignment;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\ChangeHelper;
use OCA\Deck\Db\LabelMapper;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\NoPermissionException;
use OCA\Deck\Notification\NotificationHelper;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IGroupManager;
use \Test\TestCase;

class BoardServiceTest extends TestCase {

	/** @var BoardService */
	private $service;
	/** @var L10N */
	private $l10n;
	/** @var LabelMapper */
	private $labelMapper;
	/** @var AclMapper */
	private $aclMapper;
	/** @var BoardMapper */
	private $boardMapper;
	/** @var StackMapper */
	private $stackMapper;
	/** @var PermissionService */
	private $permissionService;
	/** @var NotificationHelper */
	private $notificationHelper;
	/** @var AssignmentMapper */
	private $assignedUsersMapper;
	/** @var IUserManager */
	private $userManager;
	/** @var IUserManager */
	private $groupManager;
	/** @var ActivityManager */
	private $activityManager;
	/** @var ChangeHelper */
	private $changeHelper;
	/** @var IEventDispatcher */
	private $eventDispatcher;
	private $userId = 'admin';

	public function setUp(): void {
		parent::setUp();
		$this->l10n = $this->createMock(L10N::class);
		$this->aclMapper = $this->createMock(AclMapper::class);
		$this->boardMapper = $this->createMock(BoardMapper::class);
		$this->stackMapper = $this->createMock(StackMapper::class);
		$this->config = $this->createMock(IConfig::class);
		$this->labelMapper = $this->createMock(LabelMapper::class);
		$this->permissionService = $this->createMock(PermissionService::class);
		$this->notificationHelper = $this->createMock(NotificationHelper::class);
		$this->assignedUsersMapper = $this->createMock(AssignmentMapper::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->activityManager = $this->createMock(ActivityManager::class);
		$this->changeHelper = $this->createMock(ChangeHelper::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);

		$this->service = new BoardService(
			$this->boardMapper,
			$this->stackMapper,
			$this->config,
			$this->l10n,
			$this->labelMapper,
			$this->aclMapper,
			$this->permissionService,
			$this->notificationHelper,
			$this->assignedUsersMapper,
			$this->userManager,
			$this->groupManager,
			$this->activityManager,
			$this->eventDispatcher,
			$this->changeHelper,
			$this->userId
		);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('admin');
	}

	public function testFindAll() {
		$b1 = new Board();
		$b1->setId(1);
		$b2 = new Board();
		$b2->setId(2);
		$b3 = new Board();
		$b3->setId(3);
		$this->boardMapper->expects($this->once())
			->method('findAllForUser')
			->with('admin')
			->willReturn([$b1, $b2, $b3]);
		$user = $this->createMock(IUser::class);
		$this->groupManager->method('getUserGroupIds')
			->willReturn(['a', 'b', 'c']);
		$this->userManager->method('get')
			->with($this->userId)
			->willReturn($user);

		$result = $this->service->findAll();
		sort($result);
		$this->assertEquals([$b1, $b2, $b3], $result);
	}

	public function testFind() {
		$b1 = new Board();
		$b1->setId(1);
		$this->boardMapper->expects($this->once())
			->method('find')
			->with(1)
			->willReturn($b1);
		$this->permissionService->expects($this->once())
			->method('findUsers')
			->willReturn([
				'admin' => 'admin',
			]);
		$this->assertEquals($b1, $this->service->find(1));
	}

	public function testCreate() {
		$board = new Board();
		$board->setTitle('MyBoard');
		$board->setOwner('admin');
		$board->setColor('00ff00');
		$this->boardMapper->expects($this->once())
			->method('insert')
			->willReturn($board);
		$this->permissionService->expects($this->once())
			->method('canCreate')
			->willReturn(true);
		$b = $this->service->create('MyBoard', 'admin', '00ff00');

		$this->assertEquals($b->getTitle(), 'MyBoard');
		$this->assertEquals($b->getOwner(), 'admin');
		$this->assertEquals($b->getColor(), '00ff00');
		$this->assertCount(4, $b->getLabels());
	}

	public function testCreateDenied() {
		$this->expectException(\OCA\Deck\NoPermissionException::class);
		$board = new Board();
		$board->setTitle('MyBoard');
		$board->setOwner('admin');
		$board->setColor('00ff00');
		$this->permissionService->expects($this->once())
			->method('canCreate')
			->willReturn(false);
		$b = $this->service->create('MyBoard', 'admin', '00ff00');
	}

	public function testUpdate() {
		$board = new Board();
		$board->setTitle('MyBoard');
		$board->setOwner('admin');
		$board->setColor('00ff00');
		$this->boardMapper->expects($this->once())
			->method('find')
			->with(123)
			->willReturn($board);
		$this->boardMapper->expects($this->once())
			->method('update')
			->with($board)
			->willReturn($board);
		$this->permissionService->expects($this->once())
			->method('findUsers')
			->willReturn([
				'admin' => 'admin',
			]);
		$b = $this->service->update(123, 'MyNewNameBoard', 'ffffff', false);

		$this->assertEquals($b->getTitle(), 'MyNewNameBoard');
		$this->assertEquals($b->getOwner(), 'admin');
		$this->assertEquals($b->getColor(), 'ffffff');
		$this->assertEquals($b->getArchived(), false);
	}

	public function testDelete() {
		$board = new Board();
		$board->setOwner('admin');
		$board->setDeletedAt(0);
		$this->boardMapper->expects($this->once())
			->method('find')
			->willReturn($board);
		$this->permissionService->expects($this->once())
			->method('findUsers')
			->willReturn([
				'admin' => 'admin',
			]);
		$boardDeleted = clone $board;
		$boardDeleted->setDeletedAt(1);
		$this->boardMapper->expects($this->once())
			->method('update')
			->willReturn($boardDeleted);
		$this->assertEquals($boardDeleted, $this->service->delete(123));
	}

	public function testAddAcl() {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('admin');
		$acl = new Acl();
		$acl->setBoardId(123);
		$acl->setType('user');
		$acl->setParticipant('admin');
		$acl->setPermissionEdit(true);
		$acl->setPermissionShare(true);
		$acl->setPermissionManage(true);
		$acl->resolveRelation('participant', function ($participant) use (&$user) {
			return null;
		});
		$this->notificationHelper->expects($this->once())
			->method('sendBoardShared');
		$this->aclMapper->expects($this->once())
			->method('insert')
			->with($acl)
			->willReturn($acl);
		$this->assertEquals($acl, $this->service->addAcl(
			123, 'user', 'admin', true, true, true
		));
	}

	public function dataAddAclExtendPermission() {
		return [
			[[false, false, false], [false, false, false], [false, false, false]],
			[[false, false, false], [true, true, true], [false, false, false]],

			// user has share permissions -> can only reshare with those
			[[false, true, false], [false, false, false], [false, false, false]],
			[[false, true, false], [false, true, false], [false, true, false]],
			[[false, true, false], [true, true, true], [false, true, false]],

			// user has write permissions -> can only reshare with those
			[[true, true, false], [false, false, false], [false, false, false]],
			[[true, true, false], [false, true, false], [false, true, false]],
			[[true, true, false], [true, true, true], [true, true, false]],

			// user has manage permissions -> can upgrade acl permissions
			[[false, false, true], [true, true, true], [true, true, true]],
			[[true, true, true], [false, false, true], [false, false, true]],
		];
	}

	/**
	 * @dataProvider dataAddAclExtendPermission
	 * @param $currentUserAcl
	 * @param $providedAcl
	 * @param $resultingAcl
	 * @throws NoPermissionException
	 * @throws \OCA\Deck\BadRequestException
	 */
	public function testAddAclExtendPermission($currentUserAcl, $providedAcl, $resultingAcl) {
		$existingAcl = new Acl();
		$existingAcl->setBoardId(123);
		$existingAcl->setType('user');
		$existingAcl->setParticipant('admin');
		$existingAcl->setPermissionEdit($currentUserAcl[0]);
		$existingAcl->setPermissionShare($currentUserAcl[1]);
		$existingAcl->setPermissionManage($currentUserAcl[2]);
		$this->permissionService->expects($this->at(0))
			->method('checkPermission')
			->with($this->boardMapper, 123, Acl::PERMISSION_SHARE, null);
		if ($currentUserAcl[2]) {
			$this->permissionService->expects($this->at(1))
				->method('checkPermission')
				->with($this->boardMapper, 123, Acl::PERMISSION_MANAGE, null);
		} else {
			$this->aclMapper->expects($this->once())
				->method('findAll')
				->willReturn([$existingAcl]);
			$this->permissionService->expects($this->at(1))
				->method('checkPermission')
				->with($this->boardMapper, 123, Acl::PERMISSION_MANAGE, null)
				->willThrowException(new NoPermissionException('No permission'));
			$this->permissionService->expects($this->at(2))
				->method('userCan')
				->willReturn($currentUserAcl[0]);
			$this->permissionService->expects($this->at(3))
				->method('userCan')
				->willReturn($currentUserAcl[1]);
			$this->permissionService->expects($this->at(4))
				->method('userCan')
				->willReturn($currentUserAcl[2]);
		}

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('admin');
		$acl = new Acl();
		$acl->setBoardId(123);
		$acl->setType('user');
		$acl->setParticipant('admin');
		$acl->setPermissionEdit($resultingAcl[0]);
		$acl->setPermissionShare($resultingAcl[1]);
		$acl->setPermissionManage($resultingAcl[2]);
		$acl->resolveRelation('participant', function ($participant) use (&$user) {
			return null;
		});
		$this->notificationHelper->expects($this->once())
			->method('sendBoardShared');
		$expected = clone $acl;
		$this->aclMapper->expects($this->once())
			->method('insert')
			->with($acl)
			->willReturn($acl);
		$this->assertEquals($expected, $this->service->addAcl(
			123, 'user', 'admin', $providedAcl[0], $providedAcl[1], $providedAcl[2]
		));
	}

	public function testUpdateAcl() {
		$acl = new Acl();
		$acl->setBoardId(123);
		$acl->setType('user');
		$acl->setParticipant('admin');
		$acl->setPermissionEdit(true);
		$acl->setPermissionShare(true);
		$acl->setPermissionManage(true);

		$this->aclMapper->expects($this->once())
			->method('find')
			->with(123)
			->willReturn($acl);
		$this->aclMapper->expects($this->once())
			->method('update')
			->with($acl)
			->willReturn($acl);

		$result = $this->service->updateAcl(
			123, false, false, false
		);

		$this->assertFalse($result->getPermissionEdit());
		$this->assertFalse($result->getPermissionShare());
		$this->assertFalse($result->getPermissionManage());
	}

	public function testDeleteAcl() {
		$acl = new Acl();
		$acl->setBoardId(123);
		$acl->setType(Acl::PERMISSION_TYPE_USER);
		$acl->setParticipant('admin');
		$acl->setPermissionEdit(true);
		$acl->setPermissionShare(true);
		$acl->setPermissionManage(true);
		$this->aclMapper->expects($this->once())
			->method('find')
			->with(123)
			->willReturn($acl);
		$assignment = new Assignment();
		$assignment->setParticipant('admin');
		$this->assignedUsersMapper->expects($this->once())
			->method('findByParticipant')
			->with('admin')
			->willReturn([$assignment]);
		$this->assignedUsersMapper->expects($this->once())
			->method('delete')
			->with($assignment);
		$this->aclMapper->expects($this->once())
			->method('delete')
			->with($acl)
			->willReturn(true);
		$this->assertTrue($this->service->deleteAcl(123));
	}
}
