<?php

namespace App\AdvancedEntryManager\Scheduler\Actions;

defined('ABSPATH') || exit;

use App\AdvancedEntryManager\Api\Callback\Migrate;

class Migrate_Batch_Action {

    /**
     * Migrate Route
     * @var migrate
     */
    protected $migrate;

	public function __construct( Migrate $migrate ) {
        $this->migrate = $migrate;

		add_action( 'femmigrate_batch', [ $this->migrate, 'migrate_from_wpformsdb_plugin' ], 10, 1 );
	}
}
