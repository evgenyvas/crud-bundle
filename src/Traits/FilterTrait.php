<?php

namespace Ecode\CRUDBundle\Traits;

use Ecode\CRUDBundle\Service\ObjectManager;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

trait FilterTrait {

    /**
     * get fields for filter - backend for 'filter' vuejs component
     *
     * @param $entity string Doctrine entity name
     * @param $route string Route for filter objects
     * @param $list_route string Route for objects list
     *
     * @return html or json response for ajax
     */
    public function filter(Request $request, ObjectManager $om, EntityManagerInterface $em,
        $entity, $layout_id, $template, $template_prefix, $route, $list_route, $default_filter=[]) {

        $perm = method_exists($this, 'getPermissions') ? $this->getPermissions() : [];
        $permRes = array_key_exists('filter', $perm) ? $perm['filter'] : ($perm['all'] ?? true);
        if (!$permRes) {
            return $this->render('@CRUD/access_denied.html.twig');
        }

        $grid_ref = $request->get('grid_ref');
        $field = $request->query->get('field'); // if set - render only this field
        $entityRepo = $em->getRepository($entity);
        $attrs = (isset($this->fields) and $this->fields) ? $this->fields->getAttSettings() : $entityRepo->attSettings();
        $isAjax = $request->isXmlHttpRequest();
        $form = $this->createForm(\Ecode\CRUDBundle\Filter\Object\ObjectType::class, null, [
            'att_settings' => $attrs,
            'action' => $this->generateUrl($route),
            'method' => 'POST',
        ]);
        $form->handleRequest($request);
        $is_submitted = $form->isSubmitted();
        if ($request->isMethod('POST') and !$is_submitted) {
            return $this->redirectToRoute($route);
        }
        if ($is_submitted) {
            if (!$form->isValid()) {
                $errors = $om->getFormErrors($form);
                $fields_err = [];
                // get fields label
                foreach ($errors as $err) {
                    foreach ($err as $k=>$v) {
                        if (isset($attrs[$k]) and isset($attrs[$k]['filter_params']['required'])) {
                            $fields_err[] = $attrs[$k]['label'];
                        }
                    }
                }
                if ($fields_err) {
                    $msg = $this->translator->trans(
                        'num_of_filter_options',
                        ['num' => count($fields_err)],
                        'crud_filter'
                    ).' '.implode(', ', array_map(function($l){return "'$l'";}, $fields_err));
                    $result = ['status'=>'error', 'message'=>$msg];
                    return $this->fmt->jsonResponse($result);
                }
            }
        }
        if (!$is_submitted) {
            $qb = $em->getRepository('CRUDBundle:FiltersData')
                ->createQueryBuilder('u');
            $query = $qb->select("u.id, u.entity, u.title, u.data")
                ->where("u.entity = '".$layout_id."'")->getQuery();
            $saved_filters_data = $query->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);
            $saved_filters = [];
            foreach ($saved_filters_data as $flt) {
                $saved_filters[$flt['entity'].$flt['id']] = [
                    'id' => $flt['id'],
                    'title' => $flt['title'],
                    'data' => json_decode($flt['data'], 1)
                ];
            }
            return $this->render($template, [
                'crud_template_prefix' => $template_prefix,
                'form' => $form->createView(),
                'grid_ref'=>$grid_ref,
                'field' => $field,
                'saved_filters' => $saved_filters,
                'layout' => $entity,
            ]);
        }
        // submitted
        $result = [];
        foreach ($form as $f_name=>$f_par) {
            if ($f_name === 'search') {
                $result[] = [
                    'ftype' => 'search',
                    'val' => $f_par->getData(),
                ];
            }
            if ($f_name === 'filtermain' or $f_name === 'filteradd') {
                $filter_data = $f_par->getData();
                if ($filter_data) {
                    $result = array_merge($result,
                        $om->dataFormat(new $entity, [
                            'is_filter' => true,
                            'format_data' => $filter_data,
                            'attrs' => $attrs,
                        ]
                    ));
                }
            }
        }
        foreach ($default_filter as $df) {
            $result[] = $df;
        }
        if (!$isAjax) {
            $result = $om->getList($entity, 1, 10, 'id', 'true', $result, [
                'attrs' => $attrs,
            ]);
        }
        return $this->fmt->jsonResponse($result);
    }

    public function saveFiltersData(
        Request $request,
        ObjectManager $om,
        EntityManagerInterface $em,
        UserInterface $user
    ) {
        $layout_id = $request->get('layout_id');
        $name = $request->get('name');
        $data = $request->get('data');
        $result = ['status'=>'error', 'message'=>$this->translator->trans('The filter is empty', [], 'crud_filter')];
        if ($data) {
            $filtersObj = new \Ecode\CRUDBundle\Entity\FiltersData;
            $filtersObj->setEntity($layout_id);
            $filtersObj->setTitle($name);
            $filtersObj->setData($data);
            $filtersObj->setUserId($user->getId());
            $em->persist($filtersObj);
            $em->flush();
            $result = ['status'=>'success', 'message'=>$this->translator->trans('Filter saved successfully', [], 'crud_filter'), 'new_id'=>$filtersObj->getId()];
        }
        return $this->fmt->jsonResponse($result);
    }

    public function deleteFiltersData(
        Request $request,
        ObjectManager $om,
        EntityManagerInterface $em) {
        $id = $request->get('id');
        $result = ['status'=>'error', 'message'=>$this->translator->trans('Filter not found', [], 'crud_filter')];
        if ($id) {
            $filtersObj = $em->getRepository('CRUDBundle:FiltersData')->find($id);
            if ($filtersObj) {
                $em->remove($filtersObj);
                $em->flush($filtersObj);
                $result = ['status'=>'success', 'message'=>$this->translator->trans('Filter successfully removed', [], 'crud_filter')];
            }
        }
        return $this->fmt->jsonResponse($result);
    }
}
