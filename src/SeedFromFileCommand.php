<?php

namespace Bluora\LaravelSeedFomFile;

use Config;
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
    protected $signature = 'db:seed-from-file {dir} {--connection=}';

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
        $connection = !empty($this->option('connection'))
            ? $this->option('connection')
            : Config::get('database.default');

        try {
            $type = File::type($directory);
        } catch (\Exception $exception) {
            $this->error($directory.' does not exist.');

            return 1;
        }

        if ($type === 'dir') {
            if ($directory[strlen($directory) - 1] === '/') {
                $directory = substr($directory, 0, -1);
            }

            $files = File::files($directory);
        } else {
            $files = [$directory];
        }

        $noOrder = count($files);
        $progressBar = $this->output->createProgressBar($noOrder);
        $filesOrder = [];

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

        $forceImport = $this->confirm('This will replace your current data in database. Are you sure? [y|N]');

        foreach ($filesOrder as $filePath) {
            $tableName = File::name($filePath);
            $tableNameArray = explode('_', $tableName, 2);

            if (is_numeric($tableNameArray[0])) {
                $tableName = $tableNameArray[1];
            }

            if ($forceImport) {
                try {
                    DB::connection($connection)->unprepared(File::get($filePath));

                    $this->line('');
                    $this->line('');
                    $this->info('Processing '.$tableName);
                    $this->line('');
                } catch (\Exception $exception) {
                    $this->line('');
                    $this->error('SQL error occurred on importing '.$tableName);
                    $this->line('');
                }

                $progressBar->advance();
            }
        }

        $this->line('');
        $this->line('');
        $this->info('Done.');
    }
}
