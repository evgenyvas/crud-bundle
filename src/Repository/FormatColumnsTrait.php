<?php

namespace Ecode\CRUDBundle\Repository;

trait FormatColumnsTrait {
    public function formatColumns($attSettings=null, $userParams=[]) {
        if (!$attSettings) {
            $attSettings = $this->attSettings();
        }
        $columns = [];

        // keep order
        if (!empty($userParams)) {
            $attSettingsNew = [];
            foreach ($userParams as $f=>$v) {
                if (isset($attSettings[$f])) {
                    $attSettingsNew[$f] = $attSettings[$f];
                    unset($attSettings[$f]);
                }
            }
            $attSettings = $attSettingsNew+$attSettings;
        }
        foreach ($attSettings as $att=>$attVal) {
            $render_list = $attVal['render_list'] ?? true;
            $userParamsAtt = $userParams[$att] ?? [];

            if (!$render_list) continue;
            $type = $attVal['type'];
            $valueformat = $attVal['valueformat'] ?? null;
            $w_params = $attVal['widget_params'] ?? [];
            $w_exp = $w_params['expanded'] ?? false;
            $data = [
                'key'=>$att,
                'title'=>$attVal['label'] ?? $att,
                'label'=>$userParamsAtt['lb'] ?? $attVal['label'] ?? $att,
                'sortable'=> $attVal['sort'] ?? false,
                'show'=>$attVal['show'] ?? $attVal['show_list'] ?? false,
            ];
            if (array_key_exists('so', $userParamsAtt)) {
                $data['sortable'] = boolval($userParamsAtt['so']);
            }
            if (array_key_exists('sh', $userParamsAtt)) {
                $data['show'] = boolval($userParamsAtt['sh']);
            }
            if ($type === 'json') {
                $data['valueformat'] = 'list';
            }
            if ($valueformat) {
                $data['valueformat'] = $valueformat;
            }
            $data['thClass'] = 'column_'.$att;
            $data['export'] = isset($attVal['export']) ? boolval($attVal['export']) : true;
            if (array_key_exists('show_params', $attVal)) {
                $data['fieldParams'] = boolval($attVal['show_params']);
            }
            $columns[] = $data;
        }
        return $columns;
    }
}
