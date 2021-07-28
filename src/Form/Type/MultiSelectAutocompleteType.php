<?php

namespace Ecode\CRUDBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;

class MultiSelectAutocompleteType extends AbstractType
{
    private $accessor;
    private $em;
    private $router;

    public function __construct(
        PropertyAccessorInterface $accessor,
        EntityManagerInterface $em,
        RouterInterface $router
    ) {
        $this->accessor = $accessor;
        $this->em = $em;
        $this->router = $router;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options) {
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($options) {
            // submitted data is array of entities
            $ids = json_decode($event->getData(), true);
            $repo = $this->em->getRepository($options['class']);
            $event->setData($repo->findBy(['id'=>$ids]));
        });
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options) {
        $route = $options['route'];
        $routeObj = $this->router->getRouteCollection()->get($route);
        $data = $form->getData();
        if ($data and count($data)) {
            $opt = [];
            if ($routeObj) {
                $label_att = $routeObj->getDefault('title');
                $value_att = $routeObj->getDefault('value');
            } else {
                $value_att = $options['value_field'];
                $label_att = $options['label_field'];
            }
            foreach ($data as $elem) {
                $opt[] = [
                    'label' => $this->accessor->getValue($elem, $label_att),
                    'value' => $this->accessor->getValue($elem, $value_att),
                ];
            }
            $view->vars['init_data'] = $opt;
            $view->vars['init_data_val'] = json_encode(array_column($opt, 'value'));
        } else {
            $view->vars['init_data'] = [];
            $view->vars['init_data_val'] = '';
        }
        if (isset($options['route'])) {
            $view->vars['route'] = $options['route'];
        }
        if (isset($options['opt_init'])) {
            $view->vars['opt_init'] = $options['opt_init'];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver) {
        $resolver->setDefaults([
            'route' => '',
            'class' => '',
            'opt_init' => '',
            'value_field' => 'id',
            'label_field' => '',
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix() {
        return 'multiselectautocomplete';
    }

    /**
     * {@inheritdoc}
     */
    public function getParent() {
        return TextType::class;
    }
}
