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

class TrelloActions {
	public $cards = [];
	public function __call($name, $arguments) {
	}

	public function addAttachmentToCard($action) {
		unset($this->cards[$action->data->card->id]['attachment'][$action->data->attachment->id]);
	}

	public function deleteAttachmentFromCard($action) {
		$this->cards[$action->data->card->id]['attachment'][$action->data->attachment->id] = $action;
	}

	public function removeMemberFromCard($action) {
		unset($this->cards[$action->data->card->id]['member'][$action->data->idMember]);
	}

	public function addMemberToCard($action) {
		$this->cards[$action->data->card->id]['member'][$action->data->idMember] = $action;
	}
}
