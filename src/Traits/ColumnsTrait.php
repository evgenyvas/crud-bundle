<?php

namespace Ecode\CRUDBundle\Traits;

use Ecode\CRUDBundle\Service\ObjectManager;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

trait ColumnsTrait {

    public function saveColumnsData(
        Request $request,
        ObjectManager $om,
        EntityManagerInterface $em) {
        $config_id = $request->get('config_id');
        $data = $request->get('data');

        if ($config_id) {
            $columnsObj = $em->getRepository('CRUDBundle:ColumnsData')->find($config_id);
            if ($data) {
                $columnsObj->setData($data);
                $em->persist($columnsObj);
                $em->flush($columnsObj);
                $result = ['status'=>'success', 'message'=>$this->translator->trans('Parameters saved successfully', [], 'crud_table')];
            }
        } else {
            $layout = $request->get('layout');
            $name = $request->get('name');
            $result = ['status'=>'error', 'message'=>$this->translator->trans('Parameters not found', [], 'crud_table')];
            if ($data) {
                $columnsObj = new \Ecode\CRUDBundle\Entity\ColumnsData;
                $columnsObj->setLayout($layout);
                $columnsObj->setTitle($name);
                $columnsObj->setData($data);
                $em->persist($columnsObj);
                $em->flush($columnsObj);
                $result = ['status'=>'success', 'message'=>$this->translator->trans('Parameters saved successfully', [], 'crud_table'), 'new_id'=>$columnsObj->getId()];
            }
        }
        return $this->fmt->jsonResponse($result);
    }

    public function deleteColumnsData(
        Request $request,
        ObjectManager $om,
        EntityManagerInterface $em) {
        $id = $request->get('id');
        $result = ['status'=>'error', 'message'=>$this->translator->trans('Parameters not found', [], 'crud_table')];
        if ($id) {
            $columnsObj = $em->getRepository('CRUDBundle:ColumnsData')->find($id);
            if ($columnsObj) {
                $em->remove($columnsObj);
                $em->flush($columnsObj);
                $result = ['status'=>'success', 'message'=>$this->translator->trans('Parameters deleted successfully', [], 'crud_table')];
            }
        }
        return $this->fmt->jsonResponse($result);
    }

    public function saveFieldParams(Request $request, UserInterface $user) {
        $grid_id = $request->get('grid_id');
        $fields = json_decode($request->get('fields'), true);
        $em = $this->getDoctrine()->getManager();
        $field_params = [];
        foreach ($fields as $field) {
            $field_params[$field['key']] = [
                'lb' => $field['label'],
                'sh' => intval($field['show']),
                //'fi' => intval($field['filterable']),
                //'se' => intval($field['searchable']),
                'so' => intval($field['sortable']),
            ];
        }
        $json_encode_pretty = function($data) {
            return json_encode(
                $data,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
            );
        };
        $oldSettings = $user->getSettings();
        if (is_null($oldSettings)) {
            // create new
            $settings = new UserSettings();
            $settings->setUser($user);
            $settings->setTheme('');
            $settings->setGrid($json_encode_pretty([$grid_id=>$field_params]));
        } else {
            $oldGridJson = $oldSettings->getGrid();
            $newGrid = json_decode($oldGridJson, true);
            $newGrid[$grid_id] = $field_params;
            $newGridJson = $json_encode_pretty($newGrid);
            if ($oldGridJson === $newGridJson) {
                return $this->fmt->jsonResponse(['status'=>'info', 'message'=>$this->translator->trans('Parameters did not change', [], 'crud_table')]);
            } else {
                $oldSettings->setGrid($newGridJson);
                $em->persist($oldSettings);
                $em->flush($oldSettings);
            }
        }
        return $this->fmt->jsonResponse(['status'=>'success', 'message'=>$this->translator->trans('Parameters saved successfully', [], 'crud_table')]);
    }
}
