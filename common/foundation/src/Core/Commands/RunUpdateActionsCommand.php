<?php

namespace Common\Core\Commands;

use Common\Database\UpdateActions;
use Illuminate\Console\Command;

class RunUpdateActionsCommand extends Command
{
    protected $signature = 'update:run';

    public function handle(): int
    {
        (new UpdateActions())->execute();

        $this->info('Update complete');

        return Command::SUCCESS;
    }
}
