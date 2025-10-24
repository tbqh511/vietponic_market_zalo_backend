<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class ClearRedisCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:clear-redis';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear Redis cache';

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
        try {
            $redis = Redis::connection();
            $redis->flushdb();
            $this->info('Redis cache cleared successfully.');
        } catch (\Exception $e) {
            $this->error('Error clearing Redis cache: ' . $e->getMessage());
        }
    }
}
