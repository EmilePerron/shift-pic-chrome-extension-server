<?php

namespace App\Controller;

use App\Lib\Controller;
use App\Lib\Convert;
use App\Lib\Resize;
use App\Lib\Optimize;
use App\Lib\ColorProfile;
use App\Lib\Unsplash;
use App\Lib\Usage;
use App\Lib\License;
use App\Lib\Image;

class ProcessController extends Controller {

    protected $resolutionsData;
    protected $settings;
    protected $baseImagePath;
    protected $baseImageMimeType;
    protected $licenseType;

    public function __construct() {
        $this->validateLicense();
        $this->validatePayload();
        $this->preconvertRaw();
        $this->prepareSettings();

        $images = $this->prepareImageResolutions();
        $this->processImages($images);
        $response = $this->buildPublicResponse($images);

        Usage::increment($_POST['license']);

        return $this->jsonResponse($response);
    }

    protected function validateLicense()
    {
        $license = $_POST['license'] ?? null;

        if (!$license) {
            return $this->jsonResponse(['error' => 'no_license']);
        }

        $type = License::getType($license, true);
        if (!$type) {
            return $this->jsonResponse(['error' => 'unknown_license']);
        }

        if (Usage::isMaxed($license)) {
            return $this->jsonResponse(['error' => 'usage_maxed']);
        }

        $this->licenseType = $type;
    }

    protected function validatePayload()
    {
        $imageUrl = $_POST['url'] ?? null;

        if ($imageUrl) {
            $this->baseImagePath = tempnam('/tmp', 'img');
            file_put_contents($this->baseImagePath, file_get_contents($imageUrl));

            if ($unsplashId = ($_POST['imageUnsplashId'] ?? null)) {
                try {
                    Unsplash::triggerDownload($unsplashId);
                } catch (\Exception $e) {}
            }
        } else {
            $uploadedFile = $_FILES['image'] ?? null;
            $this->baseImagePath = $uploadedFile ? $uploadedFile['tmp_name'] : null;
        }

        $this->resolutionsData = json_decode($_POST['devices'] ?? 'null', true);

        if (!$this->baseImagePath || !file_get_contents($this->baseImagePath) || !$this->resolutionsData) {
            return $this->jsonResponse(['error' => 'invalid_payload']);
        }

        $this->baseImageMimeType = mime_content_type($this->baseImagePath);

        if ($this->baseImageMimeType == 'image/heic' && $this->licenseType == 'free') {
            return $this->jsonResponse(['error' => 'image_format_upgrade_required']);
        }

        if ($this->baseImageMimeType == 'image/gif' && Image::isAnimatedGif($this->baseImagePath) && in_array($this->licenseType, ['free', 'starter'])) {
            return $this->jsonResponse(['error' => 'image_format_upgrade_required']);
        }

        if (!exif_imagetype($this->baseImagePath) && !in_array($this->baseImageMimeType, ['image/heic'])) {
            return $this->jsonResponse(['error' => 'not_optimizable_file']);
        }
    }

    protected function preconvertRaw()
    {
        # RAW images from .NEF or .DNG files are often interpreted as .tiff due to their built-in image preview
        # Check if dcraw is able to decode it as a raw image; if it is, it's a raw image, and dcraw's decoded image should be used instead
        if ($this->baseImageMimeType == 'image/tiff') {
            $imagePath = $this->baseImagePath;
            $dcrawOutput = `dcraw -w $imagePath`;

            # No responses means no errors: the raw image has been decoded!
            if (!trim($dcrawOutput)) {
                if ($this->licenseType == 'free') {
                    unlink($imagePath);
                    unlink($this->baseImagePath . '.ppm');
                    return $this->jsonResponse(['error' => 'image_format_upgrade_required']);
                }

                unlink($imagePath);
                $this->baseImagePath .= '.ppm';
            }
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
        $baseImageInfo = getimagesize($this->baseImagePath);
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

                if ($baseImageInfo && ($baseImageInfo[0] < $sizeData['width'] || $baseImageInfo[1] < $sizeData['height'])) {
                    $images[$key]['public']['error'] = 'base_image_too_small';
                }
            }
        }

        return $images;
    }

    protected function processImages(&$images)
    {
        $baseImagePath = Convert::process($this->baseImagePath);

        foreach ($images as $key => $image) {
            if (($image['public']['error'] ?? null) && $image['public']['error'] != 'base_image_too_small') {
                continue;
            }

            $settings = array_merge($this->settings, $image['settings']);

            # Resize
            if (($image['public']['error'] ?? null) == 'base_image_too_small') {
                # For images that are smaller than the desired resolution, skip resizing
                $imagePath = tempnam('/tmp', 'img');
                file_put_contents($imagePath, file_get_contents($baseImagePath));
                unset($images[$key]['public']['error']);
            } else {
                $imagePath = Resize::process($baseImagePath, $settings, false);
            }

            # Optimize
            if ($settings['quality'] == 'auto') {
                $imagePath = Optimize::processAutoQuality($imagePath, $settings, false);
            } else {
                Optimize::process($imagePath, $settings, true);
            }

            # Fix color profile
            try {
                ColorProfile::ApplyFromTo($this->baseImagePath, $imagePath);
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
