<?php

namespace Ecode\CRUDBundle\Service;

use Symfony\Component\Form\Form;

abstract class BaseFormManager {

    /**
     * List all errors of a given bound form.
     *
     * @param \Symfony\Component\Form\Form $form
     *
     * @return array
     */
    public function getFormErrors(Form $form) {
        $errors = [];

        foreach ($form->getErrors() as $error) {
            $errors[] = $error->getMessage();
        }

        foreach ($form->all() as $child /** @var Form $child */) {
            if ($child->isSubmitted() and !$child->isValid()) {
                $errors[$child->getName()] = $this->getFormErrors($child);
            }
        }

        return $errors;
    }
}
