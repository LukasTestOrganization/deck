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

namespace OCA\Deck\AppInfo;

use Closure;
use Exception;
use OC\EventDispatcher\SymfonyAdapter;
use OCA\Deck\Activity\CommentEventHandler;
use OCA\Deck\Capabilities;
use OCA\Deck\Collaboration\Resources\ResourceProvider;
use OCA\Deck\Collaboration\Resources\ResourceProviderCard;
use OCA\Deck\Dashboard\DeckWidgetUpcoming;
use OCA\Deck\Dashboard\DeckWidgetToday;
use OCA\Deck\Dashboard\DeckWidgetTomorrow;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Listeners\BeforeTemplateRenderedListener;
use OCA\Deck\Middleware\DefaultBoardMiddleware;
use OCA\Deck\Middleware\ExceptionMiddleware;
use OCA\Deck\Notification\Notifier;
use OCA\Deck\Search\DeckProvider;
use OCA\Deck\Service\FullTextSearchService;
use OCA\Deck\Service\PermissionService;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\Collaboration\Resources\IProviderManager;
use OCP\Comments\CommentsEntityEvent;
use OCP\Comments\ICommentsManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\FullTextSearch\IFullTextSearchManager;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IServerContainer;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Notification\IManager as NotificationManager;
use OCP\Util;
use Psr\Container\ContainerInterface;

class Application20 extends App implements IBootstrap {
	public const APP_ID = 'deck';

	public const COMMENT_ENTITY_TYPE = 'deckCard';

	/** @var IServerContainer */
	private $server;

	/** @var FullTextSearchService */
	private $fullTextSearchService;

	/** @var IFullTextSearchManager */
	private $fullTextSearchManager;

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);

		$this->server = \OC::$server;
	}

	public function boot(IBootContext $context): void {
		$context->injectFn(Closure::fromCallable([$this, 'registerUserGroupHooks']));
		$context->injectFn(Closure::fromCallable([$this, 'registerCommentsEntity']));
		$context->injectFn(Closure::fromCallable([$this, 'registerCommentsEventHandler']));
		$context->injectFn(Closure::fromCallable([$this, 'registerNotifications']));
		$context->injectFn(Closure::fromCallable([$this, 'registerFullTextSearch']));
		$context->injectFn(Closure::fromCallable([$this, 'registerCollaborationResources']));
	}

	public function register(IRegistrationContext $context): void {
		if ((@include_once __DIR__ . '/../../vendor/autoload.php') === false) {
			throw new Exception('Cannot include autoload. Did you run install dependencies using composer?');
		}

		$context->registerCapability(Capabilities::class);
		$context->registerMiddleWare(ExceptionMiddleware::class);
		$context->registerMiddleWare(DefaultBoardMiddleware::class);

		$context->registerService('databaseType', static function (ContainerInterface $c) {
			return $c->get(IConfig::class)->getSystemValue('dbtype', 'sqlite');
		});
		$context->registerService('database4ByteSupport', static function (ContainerInterface $c) {
			return $c->get(IDBConnection::class)->supports4ByteText();
		});

		$context->registerSearchProvider(DeckProvider::class);
		$context->registerDashboardWidget(DeckWidgetUpcoming::class);
		$context->registerDashboardWidget(DeckWidgetToday::class);
		$context->registerDashboardWidget(DeckWidgetTomorrow::class);

		$context->registerEventListener(BeforeTemplateRenderedEvent::class, BeforeTemplateRenderedListener::class);
	}

	public function registerNotifications(NotificationManager $notificationManager): void {
		$notificationManager->registerNotifierService(Notifier::class);
	}

	private function registerUserGroupHooks(IUserManager $userManager, IGroupManager $groupManager): void {
		$container = $this->getContainer();
		// Delete user/group acl entries when they get deleted
		$userManager->listen('\OC\User', 'postDelete', static function (IUser $user) use ($container) {
			// delete existing acl entries for deleted user
			/** @var AclMapper $aclMapper */
			$aclMapper = $container->query(AclMapper::class);
			$acls = $aclMapper->findByParticipant(Acl::PERMISSION_TYPE_USER, $user->getUID());
			foreach ($acls as $acl) {
				$aclMapper->delete($acl);
			}
			// delete existing user assignments
			$assignmentMapper = $container->query(AssignmentMapper::class);
			$assignments = $assignmentMapper->findByParticipant($user->getUID());
			foreach ($assignments as $assignment) {
				$assignmentMapper->delete($assignment);
			}

			/** @var BoardMapper $boardMapper */
			$boardMapper = $container->query(BoardMapper::class);
			$boards = $boardMapper->findAllByOwner($user->getUID());
			foreach ($boards as $board) {
				$boardMapper->delete($board);
			}
		});

		$groupManager->listen('\OC\Group', 'postDelete', static function (IGroup $group) use ($container) {
			/** @var AclMapper $aclMapper */
			$aclMapper = $container->query(AclMapper::class);
			$aclMapper->findByParticipant(Acl::PERMISSION_TYPE_GROUP, $group->getGID());
			$acls = $aclMapper->findByParticipant(Acl::PERMISSION_TYPE_GROUP, $group->getGID());
			foreach ($acls as $acl) {
				$aclMapper->delete($acl);
			}
		});
	}

	public function registerCommentsEntity(IEventDispatcher $eventDispatcher): void {
		$eventDispatcher->addListener(CommentsEntityEvent::EVENT_ENTITY, function (CommentsEntityEvent $event) {
			$event->addEntityCollection(self::COMMENT_ENTITY_TYPE, function ($name) {
				/** @var CardMapper */
				$cardMapper = $this->getContainer()->get(CardMapper::class);
				$permissionService = $this->getContainer()->get(PermissionService::class);

				try {
					return $permissionService->checkPermission($cardMapper, (int) $name, Acl::PERMISSION_READ);
				} catch (\Exception $e) {
					return false;
				}
			});
		});
	}

	protected function registerCommentsEventHandler(ICommentsManager $commentsManager): void {
		$commentsManager->registerEventHandler(function () {
			return $this->getContainer()->query(CommentEventHandler::class);
		});
	}

	protected function registerCollaborationResources(IProviderManager $resourceManager, SymfonyAdapter $symfonyAdapter): void {
		$resourceManager->registerResourceProvider(ResourceProvider::class);
		$resourceManager->registerResourceProvider(ResourceProviderCard::class);

		$symfonyAdapter->addListener('\OCP\Collaboration\Resources::loadAdditionalScripts', static function () {
			Util::addScript('deck', 'collections');
		});
	}

	public function registerFullTextSearch(IFullTextSearchManager $fullTextSearchManager, IEventDispatcher $eventDispatcher): void {
		if (!$fullTextSearchManager->isAvailable()) {
			return;
		}

		// FIXME move to addServiceListener
		$server = $this->server;
		$eventDispatcher->addListener(
			'\OCA\Deck\Card::onCreate', function (Event $e) use ($server) {
				$fullTextSearchService = $server->get(FullTextSearchService::class);
				$fullTextSearchService->onCardCreated($e);
			}
		);
		$eventDispatcher->addListener(
			'\OCA\Deck\Card::onUpdate', function (Event $e) use ($server) {
				$fullTextSearchService = $server->get(FullTextSearchService::class);
				$fullTextSearchService->onCardUpdated($e);
			}
		);
		$eventDispatcher->addListener(
			'\OCA\Deck\Card::onDelete', function (Event $e) use ($server) {
				$fullTextSearchService = $server->get(FullTextSearchService::class);
				$fullTextSearchService->onCardDeleted($e);
			}
		);
		$eventDispatcher->addListener(
			'\OCA\Deck\Board::onShareNew', function (Event $e) {
				$fullTextSearchService = $server->get(FullTextSearchService::class);
				$fullTextSearchService->onBoardShares($e);
			}
		);
		$eventDispatcher->addListener(
			'\OCA\Deck\Board::onShareEdit', function (Event $e) use ($server) {
				$fullTextSearchService = $server->get(FullTextSearchService::class);
				$fullTextSearchService->onBoardShares($e);
			}
		);
		$eventDispatcher->addListener(
			'\OCA\Deck\Board::onShareDelete', function (Event $e) use ($server) {
				$fullTextSearchService = $server->get(FullTextSearchService::class);
				$fullTextSearchService->onBoardShares($e);
			}
		);
	}
}
