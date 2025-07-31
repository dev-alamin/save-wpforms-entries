<?php

namespace App\AdvancedEntryManager\Scheduler\Actions;

use App\AdvancedEntryManager\Api\Callback\Migrate;

class Migrate_Batch {

    protected $migrate;

	public function __construct() {
        $this->migrate = new Migrate();

		add_action( 'swpfe_migrate_batch', [ $this->migrate, 'migrate_from_wpformsdb_plugin' ], 10, 1 );
	}
}
