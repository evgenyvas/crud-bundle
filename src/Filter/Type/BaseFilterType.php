<?php

namespace Ecode\CRUDBundle\Filter\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BaseFilterType extends AbstractType
{
    protected function getModVal() {
        return [];
    }

    /**
     * delete null mod for not required
     */
    protected function getModOptions($is_required) {
        $mod = [];
        foreach ($this->getModVal() as $k=>$v) {
            if ($is_required and $v==='null') {
                continue;
            }
            $mod[$k] = $v;
        }
        return $mod;
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options) {
        $view->vars['width'] = $options['width'];
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver) {
        $resolver->setDefaults([
            'is_required' => false,
            'widget_params' => [],
            'mod' => true,
            'width' => 3,
            'class' => 'relational',
        ]);
    }
}
