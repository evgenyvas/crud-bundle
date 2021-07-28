.. index::
   single: install

Install
=======

install package via composer

::

  composer require evgenyvas/crud-bundle

create file `config/routes/crud_routing.yaml` with content:

::

  app_crud:
    resource: '@CRUDBundle/Resources/config/routes.yaml'

execute commands:

::

  bin/console doctrine:migrations:diff
  bin/console doctrine:migrations:migrate

add parameters for date format in file `config/services.yaml`

::

  date_format: 'd.m.Y'
  datetime_format: 'd.m.Y H:i:s'
  flatpickr_date_format: 'd.m.Y'
  flatpickr_datetime_format: 'd.m.Y H:i'

include in your base template inside `javascripts` block before other scripts

::

  <script src="{{ asset('bundles/fosjsrouting/js/router.min.js') }}"></script>
  <script src="{{ path('fos_js_routing_js', { callback: 'fos.Router.setData' }) }}"></script>

insert template for modal windows. It must be inside Vue app template

::

  {% if modal_size is not defined %}
    {% set modal_size = 'lg' %}
  {% endif %}
  {% if modal_expand_size is not defined %}
    {% set modal_expand_size = 'xl' %}
  {% endif %}
  {% if modal_expanded is not defined %}
    {% set modal_expanded = false %}
  {% endif %}
  
  {% include '@CRUD/components/modal.html.twig' with {
    'modal_size': modal_size,
    'modal_expand_size': modal_expand_size,
    'modal_expanded': modal_expanded
  } %}

attach mixins in your Vue instance

::

  mixins: [
    gridValue(),
    AjaxModal,
  ],
