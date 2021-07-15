<?php
/**
 * @copyright Copyright (c) 2016 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Ryan Fletcher <ryan.fletcher@codepassion.ca>
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

return [
	'routes' => [
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

		// boards
		['name' => 'board#index', 'url' => '/boards', 'verb' => 'GET'],
		['name' => 'board#create', 'url' => '/boards', 'verb' => 'POST'],
		['name' => 'board#read', 'url' => '/boards/{boardId}', 'verb' => 'GET'],
		['name' => 'board#update', 'url' => '/boards/{boardId}', 'verb' => 'PUT'],
		['name' => 'board#delete', 'url' => '/boards/{boardId}', 'verb' => 'DELETE'],
		['name' => 'board#deleteUndo', 'url' => '/boards/{boardId}/deleteUndo', 'verb' => 'POST'],
		['name' => 'board#getUserPermissions', 'url' => '/boards/{boardId}/permissions', 'verb' => 'GET'],
		['name' => 'board#addAcl', 'url' => '/boards/{boardId}/acl', 'verb' => 'POST'],
		['name' => 'board#updateAcl', 'url' => '/boards/{boardId}/acl/{aclId}', 'verb' => 'PUT'],
		['name' => 'board#deleteAcl', 'url' => '/boards/{boardId}/acl/{aclId}', 'verb' => 'DELETE'],
		['name' => 'board#clone', 'url' => '/boards/{boardId}/clone', 'verb' => 'POST'],

		// stacks
		['name' => 'stack#index', 'url' => '/stacks/{boardId}', 'verb' => 'GET'],
		['name' => 'stack#create', 'url' => '/stacks', 'verb' => 'POST'],
		['name' => 'stack#update', 'url' => '/stacks/{stackId}', 'verb' => 'PUT'],
		['name' => 'stack#reorder', 'url' => '/stacks/{stackId}/reorder', 'verb' => 'PUT'],
		['name' => 'stack#delete', 'url' => '/stacks/{stackId}', 'verb' => 'DELETE'],
		['name' => 'stack#deleted', 'url' => '/{boardId}/stacks/deleted', 'verb' => 'GET'],
		['name' => 'stack#archived', 'url' => '/stacks/{boardId}/archived', 'verb' => 'GET'],

		// cards
		['name' => 'card#read', 'url' => '/cards/{cardId}', 'verb' => 'GET'],
		['name' => 'card#create', 'url' => '/cards', 'verb' => 'POST'],
		['name' => 'card#update', 'url' => '/cards/{cardId}', 'verb' => 'PUT'],
		['name' => 'card#delete', 'url' => '/cards/{cardId}', 'verb' => 'DELETE'],
		['name' => 'card#deleted', 'url' => '/{boardId}/cards/deleted', 'verb' => 'GET'],
		['name' => 'card#rename', 'url' => '/cards/{cardId}/rename', 'verb' => 'PUT'],
		['name' => 'card#reorder', 'url' => '/cards/{cardId}/reorder', 'verb' => 'PUT'],
		['name' => 'card#archive', 'url' => '/cards/{cardId}/archive', 'verb' => 'PUT'],
		['name' => 'card#unarchive', 'url' => '/cards/{cardId}/unarchive', 'verb' => 'PUT'],
		['name' => 'card#assignLabel', 'url' => '/cards/{cardId}/label/{labelId}', 'verb' => 'POST'],
		['name' => 'card#removeLabel', 'url' => '/cards/{cardId}/label/{labelId}', 'verb' => 'DELETE'],
		['name' => 'card#assignUser', 'url' => '/cards/{cardId}/assign', 'verb' => 'POST'],
		['name' => 'card#unassignUser', 'url' => '/cards/{cardId}/unassign', 'verb' => 'PUT'],

		// attachments
		['name' => 'attachment#getAll', 'url' => '/cards/{cardId}/attachments', 'verb' => 'GET'],
		['name' => 'attachment#create', 'url' => '/cards/{cardId}/attachment', 'verb' => 'POST'],
		['name' => 'attachment#display', 'url' => '/cards/{cardId}/attachment/{attachmentId}', 'verb' => 'GET'],
		['name' => 'attachment#update', 'url' => '/cards/{cardId}/attachment/{attachmentId}', 'verb' => 'PUT'],
		// also allow to use POST for updates so we can properly access files when using application/x-www-form-urlencoded
		['name' => 'attachment#update', 'url' => '/cards/{cardId}/attachment/{attachmentId}', 'verb' => 'POST'],
		['name' => 'attachment#delete', 'url' => '/cards/{cardId}/attachment/{attachmentId}', 'verb' => 'DELETE'],
		['name' => 'attachment#restore', 'url' => '/cards/{cardId}/attachment/{attachmentId}/restore', 'verb' => 'GET'],


		// labels
		['name' => 'label#create', 'url' => '/labels', 'verb' => 'POST'],
		['name' => 'label#update', 'url' => '/labels/{labelId}', 'verb' => 'PUT'],
		['name' => 'label#delete', 'url' => '/labels/{labelId}', 'verb' => 'DELETE'],

		// api
		['name' => 'board_api#index', 'url' => '/api/v{apiVersion}/boards', 'verb' => 'GET'],
		['name' => 'board_api#get', 'url' => '/api/v{apiVersion}/boards/{boardId}', 'verb' => 'GET'],
		['name' => 'board_api#create', 'url' => '/api/v{apiVersion}/boards', 'verb' => 'POST'],
		['name' => 'board_api#delete', 'url' => '/api/v{apiVersion}/boards/{boardId}', 'verb' => 'DELETE'],
		['name' => 'board_api#update', 'url' => '/api/v{apiVersion}/boards/{boardId}', 'verb' => 'PUT'],
		['name' => 'board_api#undo_delete', 'url' => '/api/v{apiVersion}/boards/{boardId}/undo_delete', 'verb' => 'POST'],
		['name' => 'board_api#addAcl', 'url' => '/api/v{apiVersion}/boards/{boardId}/acl', 'verb' => 'POST'],
		['name' => 'board_api#deleteAcl', 'url' => '/api/v{apiVersion}/boards/{boardId}/acl/{aclId}', 'verb' => 'DELETE'],
		['name' => 'board_api#updateAcl', 'url' => '/api/v{apiVersion}/boards/{boardId}/acl/{aclId}', 'verb' => 'PUT'],

		['name' => 'board_import_api#import', 'url' => '/api/v{apiVersion}/boards/import','verb' => 'POST', 'requirements' => ['apiVersion' => '1.1']],


		['name' => 'stack_api#index', 'url' => '/api/v{apiVersion}/boards/{boardId}/stacks', 'verb' => 'GET'],
		['name' => 'stack_api#getArchived', 'url' => '/api/v{apiVersion}/boards/{boardId}/stacks/archived', 'verb' => 'GET'],
		['name' => 'stack_api#get', 'url' => '/api/v{apiVersion}/boards/{boardId}/stacks/{stackId}', 'verb' => 'GET'],
		['name' => 'stack_api#create', 'url' => '/api/v{apiVersion}/boards/{boardId}/stacks', 'verb' => 'POST'],
		['name' => 'stack_api#update', 'url' => '/api/v{apiVersion}/boards/{boardId}/stacks/{stackId}', 'verb' => 'PUT'],
		['name' => 'stack_api#delete', 'url' => '/api/v{apiVersion}/boards/{boardId}/stacks/{stackId}', 'verb' => 'DELETE'],

		['name' => 'card_api#get', 'url' => '/api/v{apiVersion}/boards/{boardId}/stacks/{stackId}/cards/{cardId}', 'verb' => 'GET'],
		['name' => 'card_api#create', 'url' => '/api/v{apiVersion}/boards/{boardId}/stacks/{stackId}/cards', 'verb' => 'POST'],
		['name' => 'card_api#update', 'url' => '/api/v{apiVersion}/boards/{boardId}/stacks/{stackId}/cards/{cardId}', 'verb' => 'PUT'],
		['name' => 'card_api#assignLabel', 'url' => '/api/v{apiVersion}/boards/{boardId}/stacks/{stackId}/cards/{cardId}/assignLabel', 'verb' => 'PUT'],
		['name' => 'card_api#removeLabel', 'url' => '/api/v{apiVersion}/boards/{boardId}/stacks/{stackId}/cards/{cardId}/removeLabel', 'verb' => 'PUT'],
		['name' => 'card_api#assignUser', 'url' => '/api/v{apiVersion}/boards/{boardId}/stacks/{stackId}/cards/{cardId}/assignUser', 'verb' => 'PUT'],
		['name' => 'card_api#unassignUser', 'url' => '/api/v{apiVersion}/boards/{boardId}/stacks/{stackId}/cards/{cardId}/unassignUser', 'verb' => 'PUT'],
		['name' => 'card_api#reorder', 'url' => '/api/v{apiVersion}/boards/{boardId}/stacks/{stackId}/cards/{cardId}/reorder', 'verb' => 'PUT'],
		['name' => 'card_api#delete', 'url' => '/api/v{apiVersion}/boards/{boardId}/stacks/{stackId}/cards/{cardId}', 'verb' => 'DELETE'],

		['name' => 'card_api#findAllWithDue', 'url' => '/api/v{apiVersion}/dashboard/due', 'verb' => 'GET'],

		['name' => 'label_api#get', 'url' => '/api/v{apiVersion}/boards/{boardId}/labels/{labelId}', 'verb' => 'GET'],
		['name' => 'label_api#create', 'url' => '/api/v{apiVersion}/boards/{boardId}/labels', 'verb' => 'POST'],
		['name' => 'label_api#update', 'url' => '/api/v{apiVersion}/boards/{boardId}/labels/{labelId}', 'verb' => 'PUT'],
		['name' => 'label_api#delete', 'url' => '/api/v{apiVersion}/boards/{boardId}/labels/{labelId}', 'verb' => 'DELETE'],

		['name' => 'attachment_api#getAll', 'url' => '/api/v{apiVersion}/boards/{boardId}/stacks/{stackId}/cards/{cardId}/attachments', 'verb' => 'GET', 'requirements' => ['apiVersion' => '1.0']],
		['name' => 'attachment_api#display', 'url' => '/api/v{apiVersion}/boards/{boardId}/stacks/{stackId}/cards/{cardId}/attachments/{attachmentId}', 'verb' => 'GET', 'requirements' => ['apiVersion' => '1.0']],
		['name' => 'attachment_api#create', 'url' => '/api/v{apiVersion}/boards/{boardId}/stacks/{stackId}/cards/{cardId}/attachments', 'verb' => 'POST', 'requirements' => ['apiVersion' => '1.0']],
		['name' => 'attachment_api#update', 'url' => '/api/v{apiVersion}/boards/{boardId}/stacks/{stackId}/cards/{cardId}/attachments/{attachmentId}', 'verb' => 'PUT', 'requirements' => ['apiVersion' => '1.0']],
		['name' => 'attachment_api#delete', 'url' => '/api/v{apiVersion}/boards/{boardId}/stacks/{stackId}/cards/{cardId}/attachments/{attachmentId}', 'verb' => 'DELETE', 'requirements' => ['apiVersion' => '1.0']],
		['name' => 'attachment_api#restore', 'url' => '/api/v{apiVersion}/boards/{boardId}/stacks/{stackId}/cards/{cardId}/attachments/{attachmentId}/restore', 'verb' => 'PUT', 'requirements' => ['apiVersion' => '1.0']],

		['name' => 'attachment_api_v11#getAll', 'url' => '/api/v{apiVersion}/boards/{boardId}/stacks/{stackId}/cards/{cardId}/attachments', 'verb' => 'GET', 'requirements' => ['apiVersion' => '1.1']],
		['name' => 'attachment_api_v11#display', 'url' => '/api/v{apiVersion}/boards/{boardId}/stacks/{stackId}/cards/{cardId}/attachments/{type}/{attachmentId}', 'verb' => 'GET', 'requirements' => ['apiVersion' => '1.1']],
		['name' => 'attachment_api_v11#create', 'url' => '/api/v{apiVersion}/boards/{boardId}/stacks/{stackId}/cards/{cardId}/attachments', 'verb' => 'POST', 'requirements' => ['apiVersion' => '1.1']],
		['name' => 'attachment_api_v11#update', 'url' => '/api/v{apiVersion}/boards/{boardId}/stacks/{stackId}/cards/{cardId}/attachments/{type}/{attachmentId}', 'verb' => 'PUT', 'requirements' => ['apiVersion' => '1.1']],
		['name' => 'attachment_api_v11#delete', 'url' => '/api/v{apiVersion}/boards/{boardId}/stacks/{stackId}/cards/{cardId}/attachments/{type}/{attachmentId}', 'verb' => 'DELETE', 'requirements' => ['apiVersion' => '1.1']],
		['name' => 'attachment_api_v11#restore', 'url' => '/api/v{apiVersion}/boards/{boardId}/stacks/{stackId}/cards/{cardId}/attachments/{type}/{attachmentId}/restore', 'verb' => 'PUT', 'requirements' => ['apiVersion' => '1.1']],

		['name' => 'board_api#preflighted_cors', 'url' => '/api/v{apiVersion}/{path}','verb' => 'OPTIONS', 'requirements' => ['path' => '.+']],
	],
	'ocs' => [
		['name' => 'Config#get', 'url' => '/api/v{apiVersion}/config', 'verb' => 'GET'],
		['name' => 'Config#setValue', 'url' => '/api/v{apiVersion}/config/{key}', 'verb' => 'POST'],

		['name' => 'comments_api#list', 'url' => '/api/v{apiVersion}/cards/{cardId}/comments', 'verb' => 'GET'],
		['name' => 'comments_api#create', 'url' => '/api/v{apiVersion}/cards/{cardId}/comments', 'verb' => 'POST'],
		['name' => 'comments_api#update', 'url' => '/api/v{apiVersion}/cards/{cardId}/comments/{commentId}', 'verb' => 'PUT'],
		['name' => 'comments_api#delete', 'url' => '/api/v{apiVersion}/cards/{cardId}/comments/{commentId}', 'verb' => 'DELETE'],

		['name' => 'overview_api#upcomingCards', 'url' => '/api/v{apiVersion}/overview/upcoming', 'verb' => 'GET'],

		['name' => 'search#search', 'url' => '/api/v{apiVersion}/search', 'verb' => 'GET'],
	]
];
