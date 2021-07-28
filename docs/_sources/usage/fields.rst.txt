.. index::
   single: fields

Fields
======

text
----

::

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

textarea
--------

only in form

::

  'type' => 'string',
  'widget' => 'textarea',

password
--------

::

  'label' => 'Password',
  'repeat_label' => 'Confirm password',
  'type' => 'string',
  'widget' => 'password',

also for encode password add to controller:

::

  public function beforeSave($obj, $request, $formData) {
      if ($formData->getPassword()) {
          // password encode
          $obj->setPassword($this->passwordHasher->hashPassword($obj, $formData->getPassword()));
      }
  }

radio
-----

::

  'type' => 'string',
  'widget' => 'radio',
  'choices' => [
      'Test Value'=>'val1',
      'Another text'=>'text',
  ],

set default value

::

  'widget_params' => [
      'data' => 'val1'
  ],

boolean
-------

::

  'type' => 'boolean',
  'widget' => 'radio',

you can set your own values

::

  'type' => 'boolean',
  'widget' => 'radio',
  'yes_val' => 'ok',
  'no_val' => 'not',

or show only checkbox

::

  'type' => 'boolean',
  'widget' => 'checkbox',

email
-----

::

  'type' => 'string',
  'widget' => 'email',

file
----

::

  'type' => 'string',
  'widget' => 'file',

select
------

::

  'type' => 'string',
  'widget' => 'select',
  'choices' => [
      'Test Value'=>'val1',
      'Another text'=>'text',
  ],
  'widget_params' => [
      'placeholder' => '',
      'empty_data'  => null,
  ],

entity
------

generate select with automatically loaded choices

::

  'type' => 'entity',
  'label_field' => 'description',
  'data_full' => [
      'id', 'name', 'description',
  ],
  'class' => 'App\Entity\UserType',
  'widget' => 'select',
  'widget_params' => [
      'placeholder' => '',
      'empty_data'  => null,
      'choice_label' => 'description',
  ],

multichoice entity
------------------

list of checkboxes

::

  'type' => 'string',
  'widget' => 'multichoice',
  'widget_params' => [
      'expanded' => true,
      'choices' => [
          'Test Value'=>'val1',
          'Another text'=>'text',
      ],
  ],

or multiline select

::

      'expanded' => false,

selectautocomplete
------------------

load options dynamically while typing

you must specify route for loading options

for example, list of user logins:

::

  'type' => 'entity',
  'class' => 'App\Entity\User',
  'widget' => 'selectautocomplete',
  'label_field' => 'login',
  'route' => 'get_user_autocomplete',

and add route:

::

  get_user_autocomplete:
      path: /get_user_autocomplete
      controller: Ecode\CRUDBundle\Controller\AutocompleteController::getAutocompleteData
      options:
        expose: true
      defaults:
        res: true
        entity: App\Entity\User
        search: [login]
        title: login
        value: id

multiselectautocomplete
-----------------------

same as selectautocomplete, but can change many values

::

  'widget' => 'multiselectautocomplete',

instead of route you can set optional query builder

::

  'opt_query_builder' => function (EntityRepository $er) {
      return $er->createQueryBuilder('e')
          ->select('e.id, e.description')
          ->where("e.login IN('userlogin','test')");
  },

colour
------

colour selector

::

  'type' => 'string',
  'widget' => 'colour',
  'colours' => json_encode([['#0000ff','#ff0000']]),

date
----

::

  'type' => 'date',
  'widget' => 'date',

daterange
---------

only for filter

::

  'type' => 'date',
  'widget' => 'daterange',

datetime
--------

::

  'type' => 'datetime',
  'widget' => 'datetime',

datetimesec
-----------

::

  'type' => 'datetimesec',
  'widget' => 'datetimesec',

time
----

::

  'type' => 'time',
  'widget' => 'time',

subform entity
--------------

subform with dynamically add

data class is required

::

  'subform_labels' => [
      'id' => '',
      'humanSurname' => 'Surname',
      'humanName' => 'Name',
      'humanPatronymic' => 'Patronymic',
  ],
  'prototype_data' => new \App\Data\PassHumanData(null, '', '', '', null, null, null, false),
  'type' => 'entity',
  'data_full' => [
      'id', 'humanSurname', 'humanName', 'humanPatronymic',
  ],
  'class' => 'App\Entity\PassHuman',
  'widget' => 'subformadd',
  'route' => 'get_human_autocomplete_select',
  'filter_widget' => 'selectautocomplete',
  'filter_field' => 'passCarHuman',
  'entry_type' => 'App\Form\CarPassengerTypeSingleAdd',
  'att_settings' => [
      'id' => [
          'label' => '',
          'type' => 'number',
          'search' => false,
          'widget' => 'hidden',
          'widget_params' => [
              'attr' => [
                  'class' => 'd-none',
              ],
          ],
      ],
      'humanSurname' => [
          'label' => 'Surname',
          'type' => 'string',
          'widget' => 'text',
      ],
      'humanName' => [
          'label' => 'Name',
          'type' => 'string',
          'widget' => 'text',
      ],
      'humanPatronymic' => [
          'label' => 'Patronymic',
          'type' => 'string',
          'widget' => 'text',
      ],
  ],
  'valueformat' => 'list',
  'hide_empty_table' => true,
  'add_button_title' => 'Add human',
  'format_fields' => [
      'humanSurname',
      'humanName',
      'humanPatronymic',
  ],
  'format_params' => [
      'delimiter_field' => ' ',
      'get_label' => false,
  ],
  'show_list'=>true,

joinfield
---------

load field from join entity

`join_field` - key of join entity

in array `join_field_data` set which fields load from join entity

::

  'type' => 'joinfield',
  'widget' => 'text',
  'join_field' => 'user',
  'join_field_data' => [
      ['field'=>'name'],
  ],

