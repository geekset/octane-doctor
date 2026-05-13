<?php

namespace Gayansanjeewa\OctaneDoctor\Commands;

use Illuminate\Console\Command;

class OctaneDoctorCommand extends Command
{
    public $signature = 'octane-doctor';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
