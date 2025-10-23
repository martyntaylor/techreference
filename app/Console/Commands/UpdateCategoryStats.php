<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateCategoryStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'categories:update-stats';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh materialized views for category and software statistics';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (config('database.default') !== 'pgsql') {
            $this->error('This command only works with PostgreSQL databases.');
            return Command::FAILURE;
        }

        $this->info('Refreshing materialized views...');

        // Refresh category_port_stats view
        $this->info('Refreshing category_port_stats view...');
        DB::statement('REFRESH MATERIALIZED VIEW CONCURRENTLY category_port_stats');
        $this->info('✓ category_port_stats refreshed');

        // Refresh software_port_stats view
        $this->info('Refreshing software_port_stats view...');
        DB::statement('REFRESH MATERIALIZED VIEW CONCURRENTLY software_port_stats');
        $this->info('✓ software_port_stats refreshed');

        // Refresh popular_ports view (no CONCURRENTLY - no unique index)
        $this->info('Refreshing popular_ports view...');
        DB::statement('REFRESH MATERIALIZED VIEW popular_ports');
        $this->info('✓ popular_ports refreshed');

        // Refresh port_statistics view (no CONCURRENTLY - no unique index)
        $this->info('Refreshing port_statistics view...');
        DB::statement('REFRESH MATERIALIZED VIEW port_statistics');
        $this->info('✓ port_statistics refreshed');

        $this->newLine();
        $this->info('All materialized views refreshed successfully!');

        // Display category stats
        $stats = DB::select('SELECT name, port_count FROM category_port_stats ORDER BY port_count DESC');
        $this->table(
            ['Category', 'Ports'],
            array_map(fn($stat) => [$stat->name, $stat->port_count], $stats)
        );

        return Command::SUCCESS;
    }
}
