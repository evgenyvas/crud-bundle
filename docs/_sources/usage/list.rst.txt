.. index::
   single: list

List
====

Base config
-----------

create .xml config in directory `config/crud/`. For example, `user.xml`

::

  <?xml version="1.0" encoding="utf-8"?>
  <view>
    <layout id="users" entity="\App\Entity\User" path="/user" controller="App\Controller\UserController">
      <action type="list" header="Users">
        <row>
          <position>
            <elem type="content"/>
          </position>
        </row>
      </action>
      <action type="view" header="View user attributes">
        <block name="heading" class="popup-hide"/>
        <block name="heading_title">Viewing user attributes</block>
        <row>
          <position>
            <elem type="content"/>
          </position>
        </row>
      </action>
      <action type="add" header="Create user">
        <block name="heading" class="popup-hide"/>
        <block name="heading_title">Adding a new user</block>
        <row>
          <position>
            <elem type="content"/>
          </position>
        </row>
      </action>
      <action type="edit" header="Editing a user">
        <block name="heading" class="popup-hide"/>
        <block name="heading_title">Editing a user</block>
        <row>
          <position>
            <elem type="content"/>
          </position>
        </row>
      </action>
      <action type="delete"/>
    </layout>
  </view>

create controller, which will be used for crud operations. For example, `src/Controller/UserController.php`

::

  <?php
  
  namespace App\Controller;
  
  use Ecode\CRUDBundle\Service\ObjectFormatter;
  use Ecode\CRUDBundle\Traits\CRUDTrait;
  use Ecode\CRUDBundle\Traits\FilterTrait;
  use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
  use Symfony\Component\HttpFoundation\Response;
  use Symfony\Component\HttpFoundation\Request;
  use Symfony\Component\EventDispatcher\EventDispatcherInterface;
  use Symfony\Contracts\Translation\TranslatorInterface;
  use App\Fields\UserFields;
  
  class UserController extends AbstractController
  {
      use CRUDTrait, FilterTrait;
  
      private $dispatcher;
      private $fmt;
      private $translator;
  
      public function __construct(
          EventDispatcherInterface $dispatcher,
          ObjectFormatter $fmt,
          TranslatorInterface $translator,
          UserFields $fields
      ) {
          $this->dispatcher = $dispatcher;
          $this->fmt = $fmt;
          $this->translator = $translator;
          $this->fields = $fields;
      }
  }

create fields config. For example in file `src/Fields/UserFields.php`

::

  <?php
  
  namespace App\Fields;
  
  class UserFields
  {
      public function getAttSettings($params=[]) {
          $att = [
              'id' => [
                  'label' => 'Identificator',
                  'type' => 'number',
                  'widget' => 'number',
                  'sort' => false,
                  'show_list' => false,
                  'show_view' => false,
                  'load_list' => true,
                  'filter' => false,
                  'render_add' => false,
                  'show_edit' => false,
                  'show_print' => false,
              ],
              'name' => [
                  'label' => 'Name',
                  'type' => 'string',
                  'widget' => 'text',
                  'required' => 'required',
                  'sort' => true,
                  'show_list' => true,
                  'show_add' => true,
                  'show_edit' => true,
              ],
              'login' => [
                  'label' => 'Login',
                  'type' => 'string',
                  'widget' => 'text',
                  'required' => 'required',
                  'sort' => true,
                  'show_list' => true,
                  'show_add' => true,
                  'show_edit' => true,
              ],
              'password' => [
                  'label' => 'Password',
                  'repeat_label' => 'Confirm password',
                  'type' => 'string',
                  'widget' => 'password',
                  'required' => 'required',
                  'sort' => false,
                  'ignore_format' => true,
                  'show_list' => false,
                  'render_list' => false,
                  'load_list' => false,
                  'show_view' => false,
                  'show_add' => true,
                  'show_edit' => true,
                  'show_single' => false,
                  'show_print' => false,
                  'filter' => false,
                  'search' => false,
              ],
              'roles' => [
                  'label' => 'Roles',
                  'type' => 'json',
                  'widget' => 'multichoice',
                  'widget_params' => [
                      'expanded' => true, // checkboxes
                      'choices' => [
                          'Administrator' => 'ROLE_ADMIN',
                          'User' => 'ROLE_USER',
                      ],
                  ],
                  'required' => 'required',
                  'sort' => false,
                  'filter' => true,
                  'show_list' => true,
                  'show_add' => true,
                  'show_edit' => true,
                  'render_add' => true,
                  'render_edit' => true,
                  'show_single' => false,
                  'show_print' => false,
              ],
          ];
          return $att;
      }
  }

Changing rows ordering
----------------------

To enable changing rows order:

1. add new column in table

::

  <field name="ordering" type="integer" column="ordering" nullable="false"/>

2. add field config for this column

::

  'ordering' => [
      'label' => 'Ordering',
      'type' => 'number',
      'widget' => 'number',
      'sort' => true,
      'show_list' => false,
      'show_view' => false,
      'load_list' => true,
      'change' => false,
      'filter' => false,
      'render_add' => false,
      'show_edit' => false,
      'show_print' => false,
  ],

Table data must return column named `ordering`. If your column has different name, add config like this:

::

  'ordering' => [
      'label' => 'Ordering',
      'type' => 'number',
      'widget' => 'number',
      'sort' => true,
      'show_list' => false,
      'show_view' => false,
      'load_list' => true,
      'change' => false,
      'filter' => false,
      'render_add' => false,
      'show_edit' => false,
      'show_print' => false,
      'format_func' => function($col_val) {
          return $col_val['num'];
      },
  ],

3. add route for save ordering

config/routes.yaml

::

  user_table_save_ordering:
    path: /user/table_save_ordering
    methods: [POST]
    options:
      expose: true
    controller: App\Controller\UserController::saveOrdering

src/Controller/UserController.php

::

  public function saveOrdering(Request $request, EntityManagerInterface $em) {
      $toUpd = json_decode($request->get('toUpd'), true);

      $foundEntity = $this->userRepo->createQueryBuilder('e', 'e.id')
          ->where('e.id IN (:ids)')->setParameter('ids', array_column($toUpd, 'id'))
          ->getQuery()->getResult();
      // set null value to escape unique constraint
      foreach ($foundEntity as $obj) {
          $obj->setOrdering(null);
          $em->persist($obj);
      }
      $em->flush();
      foreach ($toUpd as $upd) {
          $obj = $foundEntity[$upd['id']];
          $obj->setOrdering($upd['val']);
          $em->persist($obj);
      }
      $em->flush();
      return $this->fmt->jsonResponse([
          'status'=>'success',
          'message'=>'Successfully saved',
      ]);
  }

also add in `beforeSave` method

::

  public function beforeSave($obj, $request, $formData) {
      if (!$obj->getId()) {
          // get max ordering
          $res = $this->userRepo->createQueryBuilder('u')
              ->select('MAX(u.ordering)')
              ->getQuery()
              ->getSingleScalarResult();
          $maxOrdering = $res ? $res + 1 : 1;
          $obj->setOrdering($maxOrdering);
      }
  }

templates/user/list.html.twig

::

  {% set save_ordering_route = 'user_table_save_ordering' %}

