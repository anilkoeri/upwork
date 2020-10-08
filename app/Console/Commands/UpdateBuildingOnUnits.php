<?php

namespace App\Console\Commands;

use App\Models\Unit;
use Illuminate\Console\Command;

class UpdateBuildingOnUnits extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'script:populate_building_on_units';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update building_id in units table with building_id is NULL';

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
     * @return mixed
     */
    public function handle()
    {
        $units = Unit::with('floor')->whereNull('building_id')->get();

        foreach($units as $uk => $uv){
            $uv->building_id = $uv->floor->building_id;
            $uv->save();
        }
    }
}
