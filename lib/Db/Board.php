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

namespace OCA\Deck\Db;

class Board extends RelationalEntity {
	protected $title;
	protected $owner;
	protected $color;
	protected $archived = false;
	protected $labels = [];
	protected $acl = [];
	protected $permissions = [];
	protected $users = [];
	protected $shared;
	protected $stacks = [];
	protected $deletedAt = 0;
	protected $lastModified = 0;
	protected $coverImages = true;

	protected $settings = [];

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('shared', 'integer');
		$this->addType('archived', 'boolean');
		$this->addType('deletedAt', 'integer');
		$this->addType('lastModified', 'integer');
		$this->addType('coverImages', 'boolean');
		$this->addRelation('labels');
		$this->addRelation('acl');
		$this->addRelation('shared');
		$this->addRelation('users');
		$this->addRelation('permissions');
		$this->addRelation('stacks');
		$this->addRelation('settings');
		$this->addResolvable('owner');
		$this->shared = -1;
	}

	public function jsonSerialize() {
		$json = parent::jsonSerialize();
		if ($this->shared === -1) {
			unset($json['shared']);
		}
		return $json;
	}

	/**
	 * @param Label[] $labels
	 */
	public function setLabels($labels) {
		foreach ($labels as $l) {
			$this->labels[] = $l;
		}
	}

	/**
	 * @param Acl[] $acl
	 */
	public function setAcl($acl) {
		foreach ($acl as $a) {
			$this->acl[] = $a;
		}
	}

	public function getETag() {
		return md5((string)$this->getLastModified());
	}
}
