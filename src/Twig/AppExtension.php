<?php

namespace Ecode\CRUDBundle\Twig;

use Twig\Extension\AbstractExtension;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Serializer\Normalizer\DataUriNormalizer;

class AppExtension extends AbstractExtension
{
    public function getFilters() {
        return [
            new \Twig\TwigFilter('unescape', function($value) {
                return html_entity_decode($value);
            }),
            new \Twig\TwigFilter('base64_encode', function($value) {
                return base64_encode($value);
            }),
            new \Twig\TwigFilter('base64_decode', function($value) {
                return base64_decode($value);
            }),
            new \Twig\TwigFilter('filename', function($value) {
                return pathinfo($value, PATHINFO_FILENAME);
            }),
            new \Twig\TwigFilter('image_base64', function($value) {
                if ($value instanceof File and $value->getRealPath() and file_exists($value->getRealPath())) {
                    $mime = $value->getMimeType();
                    if (explode('/',$mime)[0] === 'image') {
                        $normalizer = new DataUriNormalizer();
                        return $normalizer->normalize($value);
                    }
                }
                return '';
            }),
        ];
    }
}
