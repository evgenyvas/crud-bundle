<?php

namespace Ecode\CRUDBundle\Service;

class FilterTypeManager
{
    private $services = [];

    public function __construct(iterable $filterServices) {
        foreach ($filterServices as $service) {
            if (!isset($this->services[$service::FILTER_TYPE])) {
                $this->services[$service::FILTER_TYPE] = [];
            }
            if (!array_key_exists($service::FILTER_WIDGET, $this->services[$service::FILTER_TYPE])) {
                $this->services[$service::FILTER_TYPE][$service::FILTER_WIDGET] = $service;
            }
        }
    }

    public function getService($filterType, $filterWidget) {
        return $this->services[$filterType][$filterWidget] ?? null;
    }
}
