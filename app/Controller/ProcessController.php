<?php

namespace App\Controller;

use App\Lib\Controller;
use App\Lib\Resize;
use App\Lib\Optimize;
use App\Lib\ColorProfile;

class ProcessController extends Controller {

    protected $uploadedFile;
    protected $resolutionsData;
    protected $settings;

    public function __construct() {
        $this->validatePayload();
        $this->prepareSettings();

        $images = $this->prepareImageResolutions();
        $this->processImages($images);
        $response = $this->buildPublicResponse($images);

        return $this->jsonResponse($response);
    }

    protected function validatePayload()
    {
        $this->uploadedFile = $_FILES['image'] ?? null;
        $this->resolutionsData = json_decode($_POST['devices'] ?? 'null', true);

        if (!$this->uploadedFile || !$this->resolutionsData) {
            return $this->jsonResponse(['error' => 'invalid_payload']);
        } else if (!exif_imagetype($this->uploadedFile['tmp_name'])) {
            return $this->jsonResponse(['error' => 'not_optimizable_file']);
        }
    }

    protected function prepareSettings()
    {
        $this->settings = [
            'quality' => $this->config('quality', $_POST['quality'] ?? 'auto', 'auto'),
            'reformat' => ($_POST['format'] ?? 'auto') == 'auto',
        ];
    }

    protected function prepareImageResolutions()
    {
        $baseImageInfo = getimagesize($this->uploadedFile['tmp_name']);
        $images = [];

        foreach ($this->resolutionsData as $device => $sizeData) {
            if ($sizeData['width'] === null) {
                continue;
            }

            $key = $sizeData['width'] . 'x' . $sizeData['height'];

            if (isset($images[$key])) {
                $images[$key]['public']['devices'][] = $device;
            } else {
                $images[$key] = [
                    'public' => [
                        'devices' => [$device],
                        'image' => null,
                        'extension' => null,
                    ],
                    'settings' => [
                        'width' => $sizeData['width'],
                        'height' => $sizeData['height'],
                    ],
                ];

                if ($baseImageInfo[0] < $sizeData['width'] || $baseImageInfo[1] < $sizeData['height']) {
                    $images[$key]['public']['error'] = 'base_image_too_small';
                }
            }
        }

        return $images;
    }

    protected function processImages(&$images)
    {
        foreach ($images as $key => $image) {
            if ($image['public']['error'] ?? null) {
                continue;
            }

            $settings = array_merge($this->settings, $image['settings']);
            $imagePath = Resize::process($this->uploadedFile['tmp_name'], $settings, false);

            if ($settings['quality'] == 'auto') {
                $imagePath = Optimize::processAutoQuality($imagePath, $settings, false);
            } else {
                Optimize::process($imagePath, $settings, true);
            }

            try {
                ColorProfile::ApplyFromTo($this->uploadedFile['tmp_name'], $imagePath);
            } catch (\Exception $e) { }

            $imageType = exif_imagetype($imagePath);
            $mimeType = image_type_to_mime_type($imageType);

            $images[$key]['public']['image'] = 'data:' . $mimeType . ';base64,' . base64_encode(file_get_contents($imagePath));
            $images[$key]['public']['extension'] = image_type_to_extension($imageType);

            unlink($imagePath);
        }
    }

    protected function buildPublicResponse($images)
    {
        return array_map(function($image) {
            return $image['public'];
        }, $images);
    }

}
