<?php

namespace HnhDigital\LaravelSeedFomFile;

use Config;
use DB;
use File;
use Illuminate\Console\Command;
use League\Csv\Reader;

class SeedFromCsvCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:seed-from-csv {dir} {--connection=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed database from a given CSV data in a given file or files in a directory.';

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

        $this->info(' This process will replace any existing data.');
        $forceImport = $this->confirm('Are you sure?');

        foreach ($filesOrder as $filePath) {
            $tableName = File::name($filePath);
            $tableNameArray = explode('_', $tableName, 2);

            if (is_numeric($tableNameArray[0])) {
                $tableName = $tableNameArray[1];
            }

            if ($forceImport) {
                try {
                    $this->line('');
                    $this->line('');
                    $this->info('Processing '.$tableName);
                    $this->line('');

                    $csv = Reader::createFromPath($filePath);
                    $csv->setHeaderOffset(0);

                    DB::connection($connection)
                        ->statement('SET FOREIGN_KEY_CHECKS=0;');

                    DB::connection($connection)
                        ->table($tableName)
                        ->truncate();

                    DB::connection($connection)
                        ->statement('SET FOREIGN_KEY_CHECKS=1;');

                    foreach ($csv as $record) {
                        foreach ($record as $key => &$value) {
                            if ($value === 'NULL') {
                                $value = null;
                            }
                        }

                        DB::connection($connection)
                            ->table($tableName)
                            ->insert($record);
                    }
                } catch (\Exception $exception) {
                    $this->line('');
                    $this->error('SQL error occurred on importing '.$tableName);
                    $this->line($exception->getMessage());
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
