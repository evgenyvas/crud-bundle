<?php

namespace Ecode\CRUDBundle\Service;

class FilterManager
{
    private $services = [];

    public function __construct(iterable $filterServices) {
        foreach ($filterServices as $service) {
            if (!array_key_exists($service::FILTER_TYPE, $this->services)) {
                $this->services[$service::FILTER_TYPE] = $service;
            }
        }
    }

    public function getService($filterService) {
        return $this->services[$filterService] ?? null;
    }
}
