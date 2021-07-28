<?php

namespace Ecode\CRUDBundle\Controller;

use Ecode\CRUDBundle\Service\ObjectFormatter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Ecode\CRUDBundle\Service\AutocompleteManager;

class AutocompleteController extends AbstractController
{
    private $fmt;

    public function __construct(
        ObjectFormatter $fmt
    ) {
        $this->fmt = $fmt;
    }

    public function getAutocompleteData(Request $request, AutocompleteManager $model,
        $entity, $search, $title=null, $value=null, $res=false, $max_results=10, $label_delimeter=' ',
        $label_key='label', $value_key='value', $fields=[], $filter=[], $fields_join=[]) {

        $q = $request->get('q');
        $result = $model->getAutocompleteData($q, $entity, $search, $title, $value, $res,
            $max_results, $label_delimeter, $label_key, $value_key, $fields, $filter, $fields_join);

        return $this->fmt->jsonResponse($result);
    }
}
