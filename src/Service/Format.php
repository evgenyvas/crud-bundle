<?php

namespace Ecode\CRUDBundle\Service;

use Symfony\Component\HttpFoundation\JsonResponse;

class Format
{
    /**
     * format json response
     *
     * @param $result string|array data for response
     * @return json response
     */
    public static function asJsonResponse($result) {
        $response = new JsonResponse($result);
        $response->setEncodingOptions($response->getEncodingOptions()
            | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        return $response;
    }
}
