<?php

declare(strict_types=1);

namespace OCA\Deck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010400Date20210305 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		// Add cover image database field
		$table = $schema->getTable('deck_boards');
		if (!$table->hasColumn('cover_images')) {
			$table->addColumn('cover_images', 'boolean', [
				'notnull' => false,
				'default' => true,
			]);
			return $schema;
		}
		return null;
	}
}