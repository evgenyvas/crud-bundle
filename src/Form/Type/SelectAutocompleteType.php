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

class SelectAutocompleteType extends AbstractType
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
            // submitted data is entity
            $id = $event->getData();
            $repo = $this->em->getRepository($options['class']);
            $event->setData($repo->find($id));
        });
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options) {
        $route = $options['route'];
        $routeObj = $this->router->getRouteCollection()->get($route);
        $data = $form->getData();
        if ($data and is_object($data)) {
            $view->vars['init_data'] = [
                'label' => $this->accessor->getValue($data, $routeObj->getDefault('title')),
                'value' => $this->accessor->getValue($data, $routeObj->getDefault('value')),
            ];
        } else {
            $view->vars['init_data'] = [];
        }
        $view->vars['route'] = $options['route'];
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver) {
        $resolver->setDefaults([
            'route' => '',
            'class' => '',
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix() {
        return 'selectautocomplete';
    }

    /**
     * {@inheritdoc}
     */
    public function getParent() {
        return TextType::class;
    }
}
