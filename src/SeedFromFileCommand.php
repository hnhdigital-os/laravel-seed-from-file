<?php

namespace Bluora\LaravelSeedFomFile;

use DB;
use File;
use Illuminate\Console\Command;

class SeedFromFileCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:seed-from-file {dir} {force_import?} {connection?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed database from a file or files in a directory.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $directory = $this->argument('dir');
        $forceImport = !empty($this->argument('force_import'));

        try {
            $type = File::type($directory);
        } catch (\Exception $exception) {
            $this->error($directory.' does not exist.');

            return 1;
        }

        $this->info('Processing '.$directory);
        $this->info('');

        if ($type === 'dir') {
            if ($directory[strlen($directory) - 1] === '/') {
                $directory = substr($directory, 0, -1);
            }

            $files = File::files($directory);
        } else {
            $files = [$directory];
        }

        $progressBar = $this->output->createProgressBar(count($files));
        $filesOrder = [];
        $noOrder = count($files);

        foreach ($files as $filePath) {
            $tableName = File::name($filePath);
            $tableNameArray = explode('_', $tableName, 2);

            if (is_numeric($tableNameArray[0])) {
                $tableOrder = $tableNameArray[0];
                $filesOrder[$tableOrder] = $filePath;
            } else {
                $filesOrder[$noOrder] = $filePath;
                $noOrder++;
            }
        }

        ksort($filesOrder);

        foreach ($filesOrder as $filePath) {
            $tableName = File::name($filePath);
            $tableNameArray = explode('_', $tableName, 2);

            if (is_numeric($tableNameArray[0])) {
                $tableName = $tableNameArray[1];
            }

            $totalRecords = DB::table($tableName)
                ->select(DB::raw('count(*) as total_records'))->value('total_records');

            if ($totalRecords === 0 || $forceImport) {
                try {
                    DB::unprepared(File::get($filePath));
                } catch (\Exception $exception) {
                    $this->info('');
                    $this->error('SQL error occured on importing '.$tableName);
                    $this->info('');
                }
            }

            $progressBar->advance();
        }

        $this->info('');
        $this->info('');
        $this->info('Done.');
    }
}
