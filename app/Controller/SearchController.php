<?php

namespace App\Controller;

use App\Lib\Controller;
use App\Lib\Unsplash;
use App\Lib\Cache;

class SearchController extends Controller {

    public function __construct() {
    }

    public function photos()
    {
        $response = [];
        $query = $_POST['query'] ?? null;
        $page = $_POST['page'] ?? 1;

        if (strlen($query)) {
            $unsplashResults = Unsplash::search($query, $page);
            $response = $this->formatPhotosResponse($unsplashResults);
        } else {
            return $this->recent();
        }

        return $this->jsonResponse($response);
    }

    public function recent()
    {
        $unsplashResults = Unsplash::recent();
        $response = $this->formatPhotosResponse($unsplashResults);

        return $this->jsonResponse($response);
    }

    public function download()
    {
        Unsplash::triggerDownload($_GET['id'] ?? null);
    }

    protected function formatPhotosResponse($unsplashResults)
    {
        $response = [];

        foreach ($unsplashResults as $result) {
            try {
                $response[] = [
                    'id' => $result['id'],
                    'width' => $result['width'],
                    'height' => $result['height'],
                    'color' => $result['color'],
                    'description' => $result['description'],
                    'alt_description' => $result['alt_description'],
                    'url_preview' => Unsplash::addUtmParams($result['urls']['thumb']),
                    'url_small' => Unsplash::addUtmParams($result['urls']['small']),
                    'url_raw' => Unsplash::addUtmParams($result['urls']['raw']),
                    'unsplash_url' => Unsplash::addUtmParams($result['links']['html']),
                    'user_name' => $result['user']['name'],
                    'user_url' => Unsplash::addUtmParams($result['user']['links']['html']),
                ];
            } catch (\Exception $e) {}
        }

        return $response;
    }

}
