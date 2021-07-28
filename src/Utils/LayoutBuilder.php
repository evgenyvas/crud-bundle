<?php

namespace Ecode\CRUDBundle\Utils;

class LayoutBuilder
{
    public function buildTwig(array $layout, string $base_tmpl): string {
        $renderTwigBlock = function($b) {
            $b['@wrap'] = $b['@wrap'] ?? 'false';
            return "{%- block ${b['@name']} -%}".
                (($b['@wrap']==='true' or isset($b['@class']))
                    ? "\n<div".(isset($b['@class'])
                    ? ' class="'.$b['@class'].'"' : '').">" : '').
                ((isset($b['#']) and $b['#']) ? $b['#'] : "{{ parent() }}").
                (($b['@wrap']==='true' or isset($b['@class'])) ? "</div>\n" : '').
                "{% endblock %}";
        };
        $tmpl = ["{% extends '$base_tmpl' %}"];
        $layout['block'] = $layout['block'] ?? [];
        $layout['row'] = $layout['row'] ?? [];
        // generate twig template
        foreach ($layout['block'] as $b) {
            // twig block
            $tmpl[] = $renderTwigBlock($b);
        }
        $tmpl[] = "{% block content %}";
        foreach ($layout['row'] as $r) {
            $row_elem = $r['@elem'] ?? 'div'; unset($r['@elem']); // row element
            $pos = $r['position'] ?? []; unset($r['position']);
            if (!isset($r['@class'])) {
                $r['@class'] = '';
            }
            // attach attributes to element
            $row_att = '';
            foreach ($r as $att_name=>$att_val) {
                if (substr($att_name, 0, 1) !== '@') continue;
                if ($att_name === '@class') {
                    $att_val = 'row'.($att_val ? ' '.$att_val : '');
                }
                $row_att .= ' '.substr($att_name, 1).'="'.$att_val.'"';
            }
            $tmpl[] = "<$row_elem$row_att>"; // begin row
            foreach ($pos as $p) {
                $elem = $p['elem'] ?? []; unset($p['elem']);
                $disabled = $p['@disabled'] ?? 'false'; unset($p['@disabled']);
                if ($disabled === 'true') continue;
                if (!isset($p['@class'])) {
                    $p['@class'] = '';
                }
                // classes for other types of devices (twitter bootstrap 4)
                $width_classes = [
                    'width-sm' => 'col-sm-', // small
                    'width-md' => 'col-md-', // medium
                    'width-lg' => 'col-lg-', // large
                    'width-xl' => 'col-xl-', // extra large
                ];
                if (isset($p['@width'])) {
                    $p_class = 'col-'.$p['@width'];
                    unset($p['@width']);
                } else {
                    $p_class = 'col';
                }
                foreach ($width_classes as $w_attr=>$w_class) {
                    if (isset($p['@'.$w_attr])) {
                        $p_class .= ' '.$w_class.$p['@'.$w_attr];
                    }
                    unset($p['@'.$w_attr]);
                }
                foreach ($elem as $e) {
                    if ($e['@type'] === 'content') {
                        $p_class .= ' content-container';
                        break;
                    }
                }
                // attach attributes to element
                $pos_att = '';
                foreach ($p as $att_name=>$att_val) {
                    if (substr($att_name, 0, 1) !== '@') continue;
                    if ($att_name === '@class') {
                        $att_val = $p_class.($att_val ? ' '.$att_val : '');
                    }
                    $pos_att .= ' '.substr($att_name, 1).'="'.$att_val.'"';
                }
                $tmpl[] = "<div$pos_att>"; // begin position
                foreach ($elem as $e) {
                    $disabled = $e['@disabled'] ?? 'false'; unset($e['@disabled']);
                    if ($disabled === 'true') continue;
                    if ($e['@type'] === 'content') {
                        // just include parent template
                        $tmpl[] = '{{parent()}}';
                    } elseif ($e['@type'] === 'block') {
                        // include content of twig block
                        $tmpl[] = $renderTwigBlock($e);
                    } elseif ($e['@type'] === 'component') {
                        // include content of twig template with variables
                        $tmpl[] = "{% include '${e['@tmpl']}'".
                            (isset($e['param']) ? " with {".implode(', ',
                                array_map(function($p) {
                                    return "'".$p['@name']."': '".$p['#']."'";
                                }, $e['param']))."}" : '').
                            " %}\n";
                    } elseif ($e['@type'] === 'controller') {
                        // include controller action
                        $tmpl[] = "{{ render(controller(\n'${e['@action']}'".
                            (isset($e['param']) ? ", {".implode(', ',
                                array_map(function($p) {
                                    $escape = isset($p['@escape']) ? $p['@escape'] === 'true' : true;
                                    return "'".$p['@name']."': ".($escape ? "'".$p['#']."'" : $p['#']);
                                }, $e['param']))."}" : '').
                            ")) }}\n";
                    }
                }
                $tmpl[] = "</div>"; // end position
            }
            $tmpl[] = "</$row_elem>"; // end row
        }
        $tmpl[] = '{% endblock %}';
        return implode("\n", $tmpl);
    }
}
