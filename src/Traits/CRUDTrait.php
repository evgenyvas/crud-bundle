<?php

namespace Ecode\CRUDBundle\Traits;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Ecode\CRUDBundle\Utils\LayoutBuilder;
use Ecode\CRUDBundle\Service\ObjectManager;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Ecode\CRUDBundle\Event\Object\ObjectViewedEvent;
use Ecode\CRUDBundle\Event\Object\ObjectDeletedEvent;
use Symfony\Component\Form\FormFactoryInterface;

trait CRUDTrait {

    /**
     * get list of objects
     *
     * @param $entity string Doctrine entity name
     * @param $layout string layout name
     * @param $page int page number
     * @param $perPage int number of elements per page
     * @param $add_route string Route for add object
     * @param $edit_route string Route for edit object
     * @param $view_route string Route for view object
     * @param $delete_route string Route for delete object
     * @param $filter_route string Route for filter objects
     *
     * @return html response
     */
    public function list(Request $request, LayoutBuilder $builder, EntityManagerInterface $em, \Twig\Environment $twig,
        $entity, $layout_id, $layout, $page, $perPage, $template, $template_prefix, $add_route, $edit_route, $view_route,
        $delete_route, $filter_route, $history_route, $extraParams=[]) {

        $perm = method_exists($this, 'getPermissions') ? $this->getPermissions() : [];
        $permRes = array_key_exists('list', $perm) ? $perm['list'] : ($perm['all'] ?? true);
        if (!$permRes) {
            return $this->render('access_denied.html.twig');
        }

        $repo = class_exists($entity) ? $this->getDoctrine()->getRepository($entity) : null;

        $qb = $em->getRepository('CRUDBundle:ColumnsData')->createQueryBuilder('u');
        $query = $qb->select("u.id, u.layout, u.title, u.data")
            ->where("u.layout = '".$layout_id."'")->getQuery();
        $saved_columns_data = $query->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);
        $saved_columns = [];
        foreach ($saved_columns_data as $flt) {
            $saved_columns[$flt['layout'].$flt['id']] = [
                'id' => $flt['id'],
                'title' => $flt['title'],
                'data' => json_decode($flt['data'], 1)
            ];
        }
        $params = [
            'crud_template_prefix' => $template_prefix,
            'layout' => $entity,
            'title' => $layout['@header'],
            'page' => $page,
            'perPage' => $perPage ?? $this->per_page ?? 50,
            'perPageOpt' => $this->per_page_opt ?? [],
            'paginate_route' => $layout_id.'_paginate',
            'add_route' => $add_route,
            'edit_route' => $edit_route,
            'view_route' => $view_route,
            'delete_route' => $delete_route,
            'filter_route' => $filter_route,
            'history_route' => $history_route,
            'sort_desc' => $this->sort_desc ?? $repo->sort_desc ?? false,
            'sort_by' => $this->sort_by ?? $repo->sort_by ?? 'id',
            'grid_ref' => 'objects_'.random_int(100, 999),
            'saved_columns' => $saved_columns,
        ];
        foreach ($extraParams as $k=>$v) {
            $params[$k] = $v;
        }

        // render template inside action layout
        return new Response($twig->createTemplate($builder
            ->buildTwig($layout, $template))->render($params));
    }

    /**
     * paginate objects - ajax handler
     *
     * @param $entity string Doctrine entity name
     * @param $page int current page
     * @param $perPage int objects per page
     *
     * @return json response
     */
    public function paginate(Request $request, ObjectManager $om,
        $entity, $layout_id, $page, $perPage) {
        $sortBy = $request->get('sortBy');
        $sortDesc = $request->get('sortDesc') === 'true';
        $filter = json_decode($request->get('filter', ''), true);
        if (is_null($filter)) {
            $filter = [];
        }
        $options = [];
        if (isset($this->fields) and $this->fields) {
            $attrs = $this->fields->getAttSettings();
            $options['attrs'] = $attrs;
        }
        $list = $om->getList($entity, $page, $perPage, $sortBy, $sortDesc, $filter, $options,
            ['is_grid'=>true, 'show_list'=>true]
        );
        if (isset($this->fields) and $this->fields) {
            $fieldsParams['dataFull'] = $list['_dataFull'];
            $attrs = $this->fields->getAttSettings($fieldsParams);
            $list['export'] = $this->formatExportData($list['data'], $list['columns'], $attrs);
            $list['data'] = $this->formatData($list['data'], $list['columns'], $attrs);
        }
        unset($list['_dataFull']);
        return $this->fmt->jsonResponse($list);
    }

    private function formatData($data, $columns, $att) {
        $new_data = [];
        foreach ($data as $row) {
            $new_row = [];
            foreach ($columns as $col) {
                $val = $row[$col['key']] ?? '';
                $colAtt = $att[$col['key']] ?? [];
                if (isset($colAtt['format_func'])) {
                    $val = $colAtt['format_func']($row);
                }
                $new_row[$col['key']] = $val;
            }
            $new_data[] = $new_row;
        }
        return $new_data;
    }

    private function formatExportData($data, $columns, $att) {
        $new_data = [];
        foreach ($data as $row) {
            $new_row = [];
            foreach ($columns as $col) {
                $colAtt = $att[$col['key']] ?? [];
                if (isset($colAtt['format_export_func'])) {
                    $new_row[$col['key']] = $colAtt['format_export_func']($row);
                }
            }
            $new_data[] = $new_row;
        }
        return $new_data;
    }

    /**
     * add/update object
     *
     * @param $entity string Doctrine entity name
     * @param $action string action, can be 'add' or 'edit'
     * @param $id int object identificator - for edit
     * @param $layout string layout name
     * @param $route string Route for action
     *
     * @return html or json response for ajax
     */
    public function manage(
        Request $request,
        LayoutBuilder $builder,
        ObjectManager $om,
        PropertyAccessorInterface $accessor,
        FormFactoryInterface $formFactory,
        \Twig\Environment $twig,
        $entity, $layout_id, $template, $template_prefix, $action, $id, $layout, $route, $redirect_route, $route_default_params=[]) {

        $perm = method_exists($this, 'getPermissions') ? $this->getPermissions() : [];
        $permRes = array_key_exists($action, $perm) ? $perm[$action] : ($perm['all'] ?? true);
        if (!$permRes) {
            return $this->render('access_denied.html.twig');
        }

        $default = $request->query->get('default');
        $defaultValues = $default ? json_decode($default, true) : null;

        $is_clone = $request->query->get('clone') ? $request->query->get('clone') === 'true' : false;
        if ($action === 'add') {
            $is_clone = true;
        }
        //dump($request->query->all());
        //dump($request->request->all());

        $isAjax = $request->isXmlHttpRequest();

        $repo = $this->getDoctrine()->getRepository($entity);
        $att = (isset($this->fields) and $this->fields) ? $this->fields->getAttSettings() : $repo->attSettings();

        if ($action === 'add') {
            $obj = new $entity;
        } else {
            if (property_exists($repo, 'is_versioning') and $repo->is_versioning) {
                $max_version = $repo->getMaxVersion($id);
                $obj = $repo->findOneBy([
                    'id' => $id,
                    'version' => $max_version,
                ]);
            } else {
                $obj = $repo->find($id);
            }

            if (!$obj) {
                $notfoundmsg = $this->translator->trans('No object found for id= %objid%', [
                    '%objid%' => $id,
                ], 'crud_form');
                if ($isAjax) {
                    return $this->fmt->jsonResponse(['status'=>'error', 'message'=>$notfoundmsg]);
                } else {
                    throw $this->createNotFoundException($notfoundmsg);
                }
            }
        }
        if ($defaultValues) {
            foreach ($defaultValues as $defaultField=>$defaultVal) {
                if ($att[$defaultField]['type'] === 'entity') {
                    $defaultVal = $this->getDoctrine()->getRepository($att[$defaultField]['class'])->find($defaultVal);
                }
                if ($accessor->isWritable($obj, $defaultField) and !is_null($defaultVal)) {
                    $accessor->setValue($obj, $defaultField, $defaultVal);
                }
            }
        }
        $cloneid = $request->query->get('cloneid') ?? null;
        if ($cloneid) { // clone object
            if (property_exists($repo, 'is_versioning') and $repo->is_versioning) {
                $max_version = $repo->getMaxVersion($cloneid);
                $objClone = $repo->findOneBy([
                    'id' => $cloneid,
                    'version' => $max_version,
                ]);
            } else {
                $objClone = $repo->find($cloneid);
            }
            $clone_att = (isset($this->fields) and $this->fields) ? $this->fields->getAttSettings() : $repo->attSettings();
            foreach ($clone_att as $c_att=>$c_att_conf) {
                $att_is_clone = $c_att_conf['clone'] ?? false;
                if ($att_is_clone) {
                    $clone_data = $accessor->getValue($objClone, $c_att);
                    if ($accessor->isWritable($obj, $c_att) and !is_null($clone_data)) {
                        $accessor->setValue($obj, $c_att, $clone_data);
                    }
                }
            }
        } elseif (method_exists($repo, 'dataPreload')) {
            $repo->dataPreload($obj, $request);
        }
        $format_params = [];
        if (isset($this->fields) and $this->fields) {
            $format_params['attrs'] = $att;
        }
        $origData = $om->dataFormat($obj, $format_params);
        $dataClass = $om->getDataClass($entity);
        $objOrig = clone $obj;
        $dataObjDefault = [];
        if (method_exists($this, 'dataObjectPreload')) {
            $dataObjDefault = $this->dataObjectPreload($obj);
        } elseif (method_exists($repo, 'dataObjectPreload')) {
            $dataObjDefault = $repo->dataObjectPreload($obj);
        }
        $validation_groups = ['Default', $action];
        if (isset($this->fields) and $this->fields and method_exists($this->fields, 'getValidationGroups')) {
            $validation_groups = $this->fields->getValidationGroups($validation_groups);
        }
        $form_data = $om->getDataObject($dataClass, $obj, $att, $dataObjDefault);
        $form = $formFactory->createNamed('object', \Ecode\CRUDBundle\Form\ObjectType::class, $form_data, [
            'data_class' => $dataClass,
            'att_settings' => $att,
            'id_prefix' => substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 10),
            'entity' => $obj,
            'form_type' => $action,
            'action' => ($action === 'add')
                ? $this->generateUrl($route, $route_default_params)
                : $this->generateUrl($route, array_merge($route_default_params, ['id'=>$id])),
            'method' => 'POST',
            'validation_groups' => $validation_groups,
        ]);

        $form->handleRequest($request);
        $is_submitted = $form->isSubmitted();
        if ($request->isMethod('POST') and !$is_submitted) {
            return ($action === 'add')
                ? $this->redirectToRoute($route, $route_default_params)
                : $this->redirectToRoute($route, array_merge($route_default_params, ['id'=>$id]));
        }
        if ($is_submitted) {
            if ($form->isValid()) {
                $formData = $form->getData();
                //dump($formData); die;
                // apply changes into entity object and save results
                $form_params = [];
                if (isset($this->fields) and $this->fields) {
                    $form_params['attrs'] = $att;
                }
                if (method_exists($this, 'saveObjCustom')) {
                    $result = $this->saveObjCustom($obj, $action, $att, $form);
                } else {
                    $joinData = $om->getFormJoinData($form, $obj, $form_params);
                    $obj = $om->applyForm($form, $obj, $att, $action);
                    if (method_exists($this, 'beforeSave')) {
                        $this->beforeSave($obj, $request, $formData);
                    }
                    if (method_exists($this, 'saveObj')) {
                        $result = $this->saveObj($obj, $action, $origData, $joinData, $att, $form);
                    } elseif (method_exists($repo, 'saveObj')) {
                        $result = $repo->saveObj($obj, $action, $origData, $joinData, $att, $form);
                    } else {
                        $result = $om->saveObj($obj, $action, $origData, $joinData, $att);
                    }
                }
                if (!$isAjax) {
                    $isRedirect = true;
                    if ($result['status'] === 'error') {
                        $this->addFlash('danger', $result['message']);
                        $isRedirect = false;
                    } elseif ($result['status'] === 'info') {
                        $this->addFlash('info', $result['message']);
                    } elseif ($result['status'] === 'success') {
                        if (method_exists($this, 'afterSave')) {
                            $this->afterSave($obj, $result, $action);
                        }
                        $this->addFlash('success', $result['message']);
                    }
                    if ($isRedirect) {
                        return ($action === 'add')
                            ? $this->redirectToRoute($redirect_route ?: $route, $route_default_params)
                            : $this->redirectToRoute($redirect_route ?: $route, array_merge($route_default_params, ['id'=>$id]));
                    }
                } else {
                    if ($result['status'] === 'error') {
                        $this->addFlash('danger', $result['message']);
                    }
                }
            } else { // not valid
                $msg = $this->translator->trans('Validation error', [], 'crud_form');
                $this->addFlash('danger', $msg);
                if ($isAjax) {
                    $errors = $om->getFormErrors($form);
                    $result = ['status'=>'error', 'message'=>$msg, 'errors'=>$errors];
                }
            }
        } else { // only show form
            if ($obj->getId()) {
                $eventViewed = new ObjectViewedEvent($obj);
                $this->dispatcher->dispatch($eventViewed, ObjectViewedEvent::NAME);
            }
        }

        $params = [
            'crud_template_prefix' => $template_prefix,
            'title' => $layout['@header'],
            'form' => $form->createView(),
            'grid_ref'=>$request->get('grid_ref'),
            'obj' => $obj,
            'is_clone' => $is_clone,
            'entity' => $entity,
        ];

        // render template inside action layout
        $form_html = $twig->createTemplate($builder
            ->buildTwig($layout, $template))->render($params);
        if ($is_submitted and $isAjax) { // return json for submitting from ajax form
            // this html will substitute old content, so we can see validation errors
            $result['form_html'] = $form_html;
            return $this->fmt->jsonResponse($result);
        } else {
            return new Response($form_html);
        }
    }

    /**
     * view object attributes
     */
    public function view(
        Request $request,
        LayoutBuilder $builder,
        ObjectManager $om,
        FormFactoryInterface $formFactory,
        \Twig\Environment $twig,
        $entity, $layout_id, $template, $template_prefix, $id, $layout, $edit_route) {

        $perm = method_exists($this, 'getPermissions') ? $this->getPermissions() : [];
        $permRes = array_key_exists('view', $perm) ? $perm['view'] : ($perm['all'] ?? true);
        if (!$permRes) {
            return $this->render('access_denied.html.twig');
        }

        $repo = $this->getDoctrine()->getRepository($entity);
        if (property_exists($repo, 'is_versioning') and $repo->is_versioning) {
            $max_version = $repo->getMaxVersion($id);
            $obj = $repo->findOneBy([
                'id' => $id,
                'version' => $max_version,
            ]);
        } else {
            $obj = $repo->find($id);
        }
        if (!$obj) {
            $notfoundmsg = $this->translator->trans('No object found for id= %objid%', [
                '%objid%' => $id,
            ], 'crud_form');
            throw $this->createNotFoundException($notfoundmsg);
        }
        $att = (isset($this->fields) and $this->fields) ? $this->fields->getAttSettings() : $repo->attSettings();
        $dataClass = $om->getDataClass($entity);
        if (method_exists($this, 'getViewData')) {
            $form_data = $this->getViewData($obj, $att);
        } else {
            $form_data = $om->getDataObject($dataClass, $obj, $att);
        }
        $form = $formFactory->createNamed('object', \Ecode\CRUDBundle\Form\ObjectType::class, $form_data, [
            'data_class' => $dataClass,
            'att_settings' => $att,
            'id_prefix' => substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 10),
            'entity' => $obj,
            'form_type' => 'view',
            'validation_groups' => ['Default', 'view']
        ]);
        return new Response($twig->createTemplate($builder
            ->buildTwig($layout, $template))->render([
                'crud_template_prefix' => $template_prefix,
                'title' => $layout['@header'],
                'obj' => $obj,
                'form' => $form->createView(),
                'entity' => $entity,
                'edit_route' => $edit_route,
                'grid_ref'=>$request->get('grid_ref'),
            ])
        );
    }

    /**
     * delete object
     *
     * @param $entity string Doctrine entity name
     * @param $id int object identificator - for edit
     * @param $route string Route for action
     * @param $redirect_route string Route for redirect after deletion
     *
     * @return html or json response for ajax
     */
    public function delete(Request $request, ObjectManager $om,
        $entity, $layout_id, $id, $route, $redirect_route) {

        $perm = method_exists($this, 'getPermissions') ? $this->getPermissions() : [];
        $permRes = array_key_exists('delete', $perm) ? $perm['delete'] : ($perm['all'] ?? true);
        if (!$permRes) {
            return $this->render('access_denied.html.twig');
        }

        $em = $this->getDoctrine()->getManager();
        $meta = $em->getClassMetadata($entity);
        $fields = $meta->getFieldNames();
        $repo = $em->getRepository($entity);
        if (property_exists($repo, 'is_versioning') and $repo->is_versioning) {
            $max_version = $repo->getMaxVersion($id);
            $obj = $repo->findOneBy([
                'id' => $id,
                'version' => $max_version,
            ]);
        } else {
            $obj = $repo->find($id);
        }
        $eventDeleted = new ObjectDeletedEvent(clone $obj);
        $statmsg = $this->translator->trans('Successfully deleted', [], 'crud_table');
        $notfoundmsg = $this->translator->trans('No object found for id= %objid%', [
            '%objid%' => $id,
        ], 'crud_form');

        $notfound = false;
        $status = '';
        $message = '';
        if (!$obj) {
            $notfound = true;
        } else {
            try {
                if (in_array('isDeleted', $fields)) {
                    // logical deletion
                    $obj->setIsDeleted(true);
                    $em->persist($obj);
                    $em->flush($obj);
                } else {
                    $em->remove($obj);
                    $em->flush($obj);
                }
                $this->dispatcher->dispatch($eventDeleted, ObjectDeletedEvent::NAME);
                $status = 'success';
                $message = $statmsg;
            } catch (\Exception $e) {
                $status = 'error';
                $message = $e->getMessage();
            }
        }
        if (method_exists($this, 'afterDelete')) {
            $this->afterDelete($obj);
        }
        if ($request->isXmlHttpRequest()) {
            if ($notfound) {
                $result = ['status'=>'error', 'message'=>$notfoundmsg];
            } else {
                $result = ['status'=>$status, 'message'=>$message];
            }
            return $this->fmt->jsonResponse($result);
        } else {
            if ($notfound) {
                throw $this->createNotFoundException($notfoundmsg);
            }
            if ($status === 'success') {
                $this->addFlash('success', $message);
            } else {
                $this->addFlash('danger', $message);
            }
            return $this->redirectToRoute($redirect_route);
        }
    }
}
