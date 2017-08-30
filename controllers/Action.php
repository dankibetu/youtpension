<?php

require __DIR__ . '/../models/Config.php';

class Action {

    public static function get_sql($action, $variables, $table = "dummy", $configs = []) {
        $sql;
        $bind_var = '';
        $labels = [];
        $values = [];
        $keys = [];
        $where_id = Config::array_get($variables, 'runid', -1);
        unset($variables['runid']);
        foreach ($variables as $key => $value) {
            $values[] = $value;
            $labels[] = '?';
            $keys[] = $key;
            $bind_var .= Config::array_get($configs, $key, "s");
        }
        switch ($action) {
            case 'update':
                foreach ($keys as $key) {
                    $sql[] = sprintf('%s = ?', $key);
                }
                $sql = sprintf('UPDATE %s SET %s', $table, implode(',', $sql));
                if ($where_id >= 0):
                    $sql .= ' WHERE id = ?';
                    $bind_var .= 'd';
                    $values[] = $where_id;
                endif;
                break;
            default:
                $sql = sprintf('INSERT INTO %s (%s) VALUES(%s)', $table, implode(',', $keys), implode(',', $labels));
                break;
        }
        array_unshift($values, $bind_var);
        return ['sql' => $sql, 'bind' => $values];
    }

}
