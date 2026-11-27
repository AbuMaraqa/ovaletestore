<?php

namespace App\Console\Commands;

use App\Support\SiteIcons;
use Illuminate\Console\Command;

/** Regenerates the favicon, home-screen icons and manifest icons from the current logo/favicon. */
class GenerateSiteIcons extends Command
{
    protected $signature = 'branding:icons';

    protected $description = 'Generate favicon.ico, PNG favicons and home-screen icons from the logo';

    public function handle(): int
    {
        if (! SiteIcons::generate()) {
            $this->error('Settings are not available yet (run the migrations first).');

            return self::FAILURE;
        }
        $this->info('Icons written to storage/app/public/'.SiteIcons::DIR);

        return self::SUCCESS;
    }
}
