<?php

namespace App\AdvancedEntryManager\Scheduler\Actions;

use App\AdvancedEntryManager\GoogleSheet\Send_Data;

class Sync_Google_Sheet_Action
{

    protected $send_data;

    public function __construct(Send_Data $send_data)
    {
        $this->send_data = $send_data;

        // Corrected: Direct hook to the class method is the cleaner and intended way.
        // add_action('aemfw_process_gsheet_entry', [$this->send_data, 'process_single_entry']);
        add_action('aemfw_process_gsheet_entry', function ($entry_id) {
            $this->send_data->process_single_entry(['entry_id' => $entry_id]);
        });

        // Hook the task.
        add_action( 'aemfw_enqueue_unsynced_entries', [ $this->send_data, 'enqueue_unsynced_entries' ] );
    }
}
