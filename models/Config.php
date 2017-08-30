<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

class Config {

    static $settings_file = __DIR__ . '/../config/settings.yml';
    static $forms_file = __DIR__ . '/../config/forms.yml';
    static $steps_file = __DIR__ . '/../config/settings.yml';
    static $queries_file = __DIR__ . '/../config/queries.yml';
    static $re_connection = 0;
    static $max_reconnections = 3;
    static $default_form_name = 'form';

    private static function get_configs($f_file = null) {
        $s_file = is_null($f_file) ? self::$settings_file : $f_file;
        if (file_exists($s_file)) {
            $data = Yaml::parse(file_get_contents($s_file));
            return $data;
        }
    }

    private static function get_user_actions($id, $edit_variables = []) {
        $actions = ['Y' => 'Active', 'N' => 'Deactivate'];
        $str = '';
        foreach ($actions as $key => $value) {
            $selected = self::array_get($edit_variables, $id, '') == $key ? 'selected' : '';
            $str .= sprintf('<option value="%s" %s>%s</option>', $key, $selected, $value);
        }
        return $str;
    }

    private function get_genders($id, $edit_variables = []) {
        $genders = ['male' => 'Male', 'female' => 'Female'];
        $str = '<option></option>';
        foreach ($genders as $key => $value) {
            $selected = self::array_get($edit_variables, $id, '') == $key ? 'selected' : '';
            $str .= sprintf('<option value="%s" %s>%s</option>', $key, $selected, $value);
        }
        return $str;
    }

    public static function get_assoc($query, $cached = false) {
        $assoc = [];
        $query = $cached ? self::get_configs(self::$queries_file)[$query] : $query;
        $conn = self::db_connect();
        if (!$conn):
            return $assoc;
        endif;
        try {
            $result = $conn->query($query);
            while ($row = $result->fetch_assoc()) {
                $assoc[] = $row;
            }
        } catch (Exception $exc) {
            echo $exc->getTraceAsString();
        } finally {
            $result->free();
        }
        $conn->close();
        return $assoc;
    }

    public static function get_user_groups($id, $edit_variables = []) {
        $str = '<option></option>';
        foreach (self::get_assoc('job-list', true) as $result) {
            $selected = self::array_get($edit_variables, $id, '') == $result['job_id'] ? 'selected' : '';
            $str .= sprintf('<option value="%s" %s>%s</option>', $result['job_id'], $selected, $result['job']);
        }
        return $str;
    }

    private static function default_function($id, $edit_variables = []) {
        return null;
    }

    public static function execute_sql($sql_parts) {
        $mysqli = self::db_connect();
        $error = [];
        if (!($stmt = $mysqli->prepare($sql_parts['sql']))) {
            $error[] = $mysqli->error;
        }

        if (!call_user_func_array([$stmt, 'bind_param'], $sql_parts['bind'])) {
            $error[] = $stmt->error;
        }

        if (!$stmt->execute()) {
            $error[] = $stmt->error;
        }
        $stmt->close();
        return $error ? ['status' => 'error', 'message' => implode('</br>', $error)] : ['status' => 'success', 'message' => 'Operation completed successfully'];
    }

    private static function get_attributes($array, $tag = 'none', $text = null, $edit_values = []) {
        $str = '';
        foreach ($array as $attr => $value) {
            if ($attr == 'text'):
                $text = $value;
                continue;
            endif;
            $str .= sprintf(' %s="%s"', $attr, $value);
        }
        switch ($tag) {
            case 'button':
                $str = sprintf('<button%s>%s</button>', $str, $text);
                break;
            case 'none':
                break;
            case 'select':
                $str = sprintf('<%s %s>%s</%s>', $tag, $str, $text, $tag);
                break;
            default:
                $str = sprintf('<%s %s value="%s"/>', $tag, $str, self::array_get($edit_values, self::array_get($array, 'id')));
                break;
        }
        return $str;
    }

    public static function get_forms($form_name, $edit_values = []) {
        $form = self::get_configs(self::$forms_file)[$form_name];
        $runid = self::array_get($edit_values, 'id', -1);
        $definitions = '';
        $action = $edit_values && $runid >= 0 ? 'update' : 'insert';
        $name = self::array_get($form, 'name', self::$default_form_name);
        $buttons = '';
        foreach ($form['fields'] as $fields) {
            foreach ($fields as $id => $field) {
                $get_options = self::array_get($field, 'options', 'default_function');
                $options = self::$get_options($id, $edit_values);
                $attributes = array_merge(self::array_get($field, 'attributes', []), ['id' => $id, 'name' => sprintf('%s[%s]', $name, $id)]);
                $label = self::array_get($field, 'label') ? sprintf(self::array_get($form, 'label-format', ''), self::array_get($field['label'], 'class'), $id, self::array_get($field['label'], 'text')) : '';
                $tag = self::get_attributes($attributes, $field['tag'], $options, $edit_values);
                $definitions .= sprintf(self::array_get($form, 'field-format', ''), $label, $tag, self::array_get($field, 'icon'));
            }
        }
        foreach ($form['buttons'] as $fields) {
            foreach ($fields as $id => $field) {
                $attributes = array_merge(self::array_get($field, 'attributes', []), ['id' => $id]);
                $buttons .= self::get_attributes($attributes, 'button', self::array_get($field, 'text'));
            }
        }
        $buttons = sprintf(self::array_get($form, 'button-format', ''), $buttons);
        return sprintf(self::array_get($form, 'form-format', ''), self::get_attributes(self::array_get($form, 'attributes', [])), $definitions, $action, $name, $buttons, $runid);
    }

    public static function set_configs($data) {
        if (file_exists(self::$settings_file)) {
            $yaml = Yaml::dump($data);
            file_put_contents(self::$settings_file, $yaml);
        }
    }

    public static function get_assets($type) {
        $assets = self::get_configs()[$type];
        $data = PHP_EOL;
        $version = time();
        foreach ($assets['files'] as $asset) {
            $data .= sprintf($assets['code'], $assets['root'], $asset, $version) . PHP_EOL;
        }
        return $data . PHP_EOL;
    }

    public static function get_image($file_name, $attributes = []) {
        $res = "./assets/static/images/{$file_name}";
        $path = __DIR__ . "/.{$res}";
//        echo $path;
        $attr = '';
        if (!file_exists($path)):
            $attributes = ['aria-hidden' => 'true', 'class' => 'fa fa-question-circle'];
        endif;
        foreach ($attributes as $attribute => $value) {
            $attr .= sprintf('%s="%s" ', $attribute, $value);
        }

        if (file_exists($path) && is_file($path)) {
            $resource = sprintf('<img src="%s" %s />', $res, $attr);
        } else {
            $resource = sprintf('Resource Missing <i %s></i>', $attr);
        }
        return $resource . PHP_EOL;
    }

    public static function get_menu($responsibility, $append = '') {
        $menu_items = PHP_EOL;
        try {
            $menus = self::get_configs()['menu'][$responsibility];
            $title = null;
            $get = $menus['parameter'];
            $active = array_key_exists($get, $_GET) ? $_GET[$get] : null;
            $active_url = null;
            $load = null;
            foreach ($menus['navigation'] as $menu) {
                $i = array_keys($menu)[0];
                if (array_key_exists('default', $menu[$i]) && $menu[$i]['default'] && is_null($active)) {
                    $active = $menu[$i]['href'];
                }
                $class = '>';
                $url = $append . sprintf($menus['url'], $get, $menu[$i]['href'], $menu[$i]['query']);
                if ($active == $menu[$i]['href']):
                    $title = self::array_get($menu[$i],'title');
                    $class = ' class="active">';
                    $active_url = $url;
                    $load = self::array_get($menu[$i], 'load', 'undefined');
                endif;
                $menu_items .= '<li' . ($class) . PHP_EOL . '<a href="' . $url . '">' .
                        '<i aria-hidden="true" class="' . $menu[$i]['icon'] . ' ' . self::array_get($menu[$i], 'class', '') . '"></i> '
                        . $i . '</a>' . PHP_EOL . '</li>' . PHP_EOL;
            }
//            echo $menu_items;
            return ['title' => $title, 'menu' => $menu_items, 'active' => $active, 'uri' => $active_url, 'load' => $load];
        } catch (Exception $ex) {
            echo $ex->getTraceAsString();
        }
    }

    public static function get_quick_access($prepend = '') {
        $menu_items = '';
        $menus = self::get_configs()['menu']['quick-access'];
        $get = $menus['parameter'];
        $active = array_key_exists($get, $_GET) ? $_GET[$get] : null;
        foreach ($menus['navigation'] as $menu) {
            $i = array_keys($menu)[0];
            if (array_key_exists('default', $menu[$i]) && $menu[$i]['default'] && is_null($active)) {
                $active = $menu[$i]['href'];
            }

            $url = $prepend . sprintf($menus['url'], $get, $menu[$i]['href'], $menu[$i]['query']);

            $menu_items .= '<a data-toggle="tooltip" data-placement="top" title="' . $i . '" href="' . $url . '">' . PHP_EOL .
                    '<i aria-hidden="true" class="' . $menu[$i]['icon'] . '"></i> ' . PHP_EOL
                    . '</a>' . PHP_EOL;
        }
        return $menu_items;
    }

    public static function array_get($array, $key, $default = null) {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        } else {
            return $default;
        }
    }

    public static function db_connect() {
        error_reporting(E_ERROR);
        $db = self::get_configs()['database'];
        try {
            $conn = new mysqli($db['host'], $db['username'], $db['password'], $db['name']);
            if ($conn->connect_errno) {
                throw new Exception($conn->connect_error);
            } else {
                return $conn;
            }
        } catch (Exception $ex) {
            self::$re_connection += 1;
            if (self::$re_connection <= self::$max_reconnections) {
                echo ("Retry [" . self::$re_connection . "] Connection failed - [" . $ex->getMessage() . "]</br>");
                self::db_connect();
            }
            echo $ex;
            return null;
        }
    }

}
