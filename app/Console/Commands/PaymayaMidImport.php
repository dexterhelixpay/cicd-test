<?php

namespace App\Console\Commands;

use App\Imports\PaymayaMids\Workbook;
use Illuminate\Console\Command;

class PaymayaMidImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'paymaya:mid-import
        {--path=mids.xlsx : The local path to the worksheet}
        {--disk=local : The filesystem to be accessed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import the MIDs on the given worksheet';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $path = $this->option('path');
        $disk = $this->option('disk');

        $workbook = new Workbook;
        $workbook->onlySheets('Sheet2')->import($path, $disk);
        $workbook->onlySheets('Sheet1')->import($path, $disk);

        return Command::SUCCESS;
    }
}
