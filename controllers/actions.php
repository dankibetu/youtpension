<?php

session_start();

require __DIR__ . '/Action.php';
$return_url = NULL;
$form = $_REQUEST['controller'];
$fields = $_REQUEST[$form];
$action = Config::array_get($fields, 'action');
if ($action):
    unset($fields['action']);
endif;



switch ($form) {
    case 'customer':
        $return_url = '../?action=' . ['insert' => 'staff:enrollment', 'update' => 'staff:management'][$action];
        $sql_parts = Action::get_sql($action, $fields, 'all_users_t', ['staff_group' => 'i']);
        $_SESSION['cgi'] = Config::execute_sql($sql_parts);
        if ($_SESSION['cgi']['status'] == 'success'):
            unset($_SESSION['form-input']);
        else:
            $_SESSION['form-input'] = $fields;
        endif;
        break;
    default:
        echo '<pre>';
        var_dump($fields);
        echo '</pre>';
        $_SESSION['cgi']['status'] = 'error';
        $_SESSION['cgi']['message'] = 'Sign in failed!';
        $return_url = '../';
        break;
        
}
if (!is_null($return_url)):
    header('location:' . $return_url);
endif;
