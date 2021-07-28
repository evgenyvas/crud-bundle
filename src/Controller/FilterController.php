<?php

namespace Ecode\CRUDBundle\Controller;

use Ecode\CRUDBundle\Service\ObjectFormatter;
use Ecode\CRUDBundle\Traits\FilterTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class FilterController extends AbstractController
{
    use FilterTrait;

    private $dispatcher;
    private $fmt;
    private $translator;

    public function __construct(
        EventDispatcherInterface $dispatcher,
        ObjectFormatter $fmt,
        TranslatorInterface $translator
    ) {
        $this->dispatcher = $dispatcher;
        $this->fmt = $fmt;
        $this->translator = $translator;
    }
}
