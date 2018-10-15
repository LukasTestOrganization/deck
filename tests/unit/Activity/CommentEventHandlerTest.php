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

namespace OCA\Deck\Activity;

use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\AssignedUsers;
use OCA\Deck\Db\Attachment;
use OCA\Deck\Db\AttachmentMapper;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\Label;
use OCA\Deck\Db\Stack;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\Notification\NotificationHelper;
use OCA\Deck\Service\PermissionService;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\Comments\CommentsEvent;
use OCP\Comments\IComment;
use OCP\IL10N;
use OCP\IUser;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

class CommentEventHandlerTest extends TestCase {

	/** @var CommentEventHandler */
	private $commentEventHandler;
	/** @var ActivityManager */
	private $activityManager;
	/** @var NotificationHelper */
	private $notificationHelper;

	public function setUp() {
		$this->activityManager = $this->createMock(ActivityManager::class);
		$this->notificationHelper = $this->createMock(NotificationHelper::class);
		$this->commentEventHandler = new CommentEventHandler(
			$this->activityManager,
			$this->notificationHelper
		);
	}

	public function testHandle() {
		$comment = $this->createMock(IComment::class);
		$comment->expects($this->any())->method('getId')->willReturn(1);
		$comment->expects($this->any())->method('getObjectType')->willReturn('deckCard');
		$commentsEvent = new CommentsEvent(CommentsEvent::EVENT_ADD, $comment);
		$this->activityManager->expects($this->once())
			->method('triggerEvent')
			->with(ActivityManager::DECK_OBJECT_CARD, $comment, ActivityManager::SUBJECT_CARD_COMMENT_CREATE, ['comment' => 1]);
		$this->notificationHelper->expects($this->once())
			->method('sendMention')
			->with($comment);
		$this->commentEventHandler->handle($commentsEvent);
	}

	public function testHandleUpdate() {
		$comment = $this->createMock(IComment::class);
		$comment->expects($this->any())->method('getId')->willReturn(1);
		$comment->expects($this->any())->method('getObjectType')->willReturn('deckCard');
		$commentsEvent = new CommentsEvent(CommentsEvent::EVENT_UPDATE, $comment);
		$this->activityManager->expects($this->never())
			->method('triggerEvent');
		$this->notificationHelper->expects($this->once())
			->method('sendMention')
			->with($comment);
		$this->commentEventHandler->handle($commentsEvent);
	}

	public function testHandleInvalid() {
		$comment = $this->createMock(IComment::class);
		$comment->expects($this->any())->method('getId')->willReturn(1);
		$comment->expects($this->any())->method('getObjectType')->willReturn('other');
		$commentsEvent = new CommentsEvent(CommentsEvent::EVENT_ADD, $comment);
		$this->activityManager->expects($this->never())
			->method('triggerEvent');
		$this->commentEventHandler->handle($commentsEvent);
	}

	public function invokePrivate(&$object, $methodName, array $parameters = array())
	{
		$reflection = new \ReflectionClass(get_class($object));
		$method = $reflection->getMethod($methodName);
		$method->setAccessible(true);
		return $method->invokeArgs($object, $parameters);
	}

}
