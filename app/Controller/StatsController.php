<?php

namespace App\Controller;

use App\Lib\Controller;
use App\Lib\Stats;
use App\Lib\Cache;

class StatsController extends Controller {

    public function processed()
    {
        #Cache::set(Stats::PROCESSED_KEY, 52);
        return $this->jsonResponse(['count' => Stats::processedCount()]);
    }
}
