<?php

namespace Ecode\CRUDBundle\Service;

use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\HttpFoundation\JsonResponse;

class ObjectFormatter {

    private $accessor;

    public function __construct(
        PropertyAccessorInterface $accessor
    ) {
        $this->accessor = $accessor;
    }

    public function format($type, $val, $params=[]) {
        $newval = null;
        if ($type === 'date') {
            $newval = $this->formatDate($val, $params);
        } elseif ($type === 'datetime') {
            $newval = $this->formatDateTime($val, $params);
        } elseif ($type === 'datetimesec') {
            $newval = $this->formatDateTimeSec($val, $params);
        } else {
            $newval = $val;
        }
        return $newval;
    }

    private function formatDate($val, $params=[]) {
        return is_null($val) ? '' : $val->format($params['value_format'] ?? 'Y-m-d');
    }

    private function formatDateTime($val, $params=[]) {
        return is_null($val) ? '' : $val->format($params['value_format'] ?? 'Y-m-d H:i');
    }

    private function formatDateTimeSec($val, $params=[]) {
        return is_null($val) ? '' : $val->format($params['value_format'] ?? 'Y-m-d H:i:s');
    }

    public function jsonResponse($result) {
        $response = new JsonResponse($result);
        $response->setEncodingOptions($response->getEncodingOptions()
            | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        return $response;
    }
}
