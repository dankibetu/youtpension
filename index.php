<?php

session_start();

require __DIR__ . '/models/Config.php';
$parameters = [
    'application' => 'Yout Pension',
    'developer' => 'Samuel Yout',
    'customer' => [],
    'logo' => '<i class="fa fa-cube"></i>'
];
$content = Config::get_menu((Config::array_get($parameters, 'staff-group', 'hr')));
$parameters['title'] = $content['title'];
$quick_access = Config::get_quick_access($content['uri']);
$user_action = Config::get_menu('user', $content['uri']);
if (isset($_SESSION['login'])) {
    $parameters = array_merge($parameters, ['styling' => 'stylesheet', 'scripts' => 'javascript']);
    $views = [
        '/view/segments/header.part.phtml',
        '/view/segments/body.part.phtml',
        '/view/segments/footer.part.phtml',
    ];
} else {
    $parameters = array_merge($parameters, ['styling' => 'login-stylesheet', 'scripts' => 'login-javascript']);
    $views = [
        '/view/segments/header.part.phtml',
        '/view/segments/login.part.phtml',
        '/view/segments/footer.part.phtml',
    ];
    $parameters['title'] = 'Login';
}

foreach ($views as $view) {
    $part = __DIR__ . $view;
    if (file_exists($part)) {
        require $part;
    }
}