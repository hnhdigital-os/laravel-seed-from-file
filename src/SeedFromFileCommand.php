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
    protected $signature = 'db:seed-from-file {dir} {force_import?}';

    /**
     * The console command description.
     *
     * @var strings
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
        $force_import = !empty($this->argument('force_import'));

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

        $progress_bar = $this->output->createProgressBar(count($files));

        $files_order = [];

        $no_order = count($files);

        foreach ($files as $file_path) {
            $table_name = File::name($file_path);

            $table_name_array = explode('_', $table_name, 2);
            if (is_numeric($table_name_array[0])) {
                $table_order = $table_name_array[0];
                $files_order[$table_order] = $file_path;
            } else {
                $files_order[$no_order] = $file_path;
                $no_order++;
            }
        }

        ksort($files_order);

        foreach ($files_order as $file_path) {
            $table_name = File::name($file_path);

            $table_name_array = explode('_', $table_name, 2);
            if (is_numeric($table_name_array[0])) {
                $table_name = $table_name_array[1];
            }

            $total_records = DB::table($table_name)->select(DB::raw('count(*) as total_records'))->value('total_records');

            if ($total_records === 0 || $force_import) {
                try {
                    DB::unprepared(File::get($file_path));
                } catch (\Exception $exception) {
                    $this->info('');
                    $this->error('SQL error occured on importing '.$table_name);
                    $this->info('');
                }
            }
            $progress_bar->advance();
        }

        $this->info('');
        $this->info('');
        $this->info('Done.');
    }
}
