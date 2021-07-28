<?php

namespace Ecode\CRUDBundle\Service;

class FormTypeManager
{
    private $services = [];

    public function __construct(iterable $formServices) {
        foreach ($formServices as $service) {
            if (!isset($this->services[$service::FORM_TYPE])) {
                $this->services[$service::FORM_TYPE] = [];
            }
            if (!array_key_exists($service::FORM_WIDGET, $this->services[$service::FORM_TYPE])) {
                $this->services[$service::FORM_TYPE][$service::FORM_WIDGET] = $service;
            }
        }
    }

    public function getService($formType, $formWidget) {
        return $this->services[$formType][$formWidget] ?? null;
    }
}
