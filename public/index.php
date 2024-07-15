<?php

// Подключение автозагрузки через composer
require __DIR__ . '/../vendor/autoload.php';

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;
use Slim\Middleware\MethodOverrideMiddleware;
use App\UserRepository;
use App\Validator;

$courses = [
    ['id' => 1, 'name' => 'Course One'],
    ['id' => 2, 'name' => 'Course Two']
];

$repo = new App\UserRepository();

$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions(
    [
        'renderer' => function () {
            return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
        },
        // Add container definition for the flash component
        'flash' => function () {
            return new \Slim\Flash\Messages();
        }
    ]
);

AppFactory::setContainer($containerBuilder->build());

$app = AppFactory::create();
$app->add(MethodOverrideMiddleware::class);

// Add session start middleware
$app->add(
    function ($request, $next) {
        // Start PHP session
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // Change flash message storage
        $this->get('flash')->__construct($_SESSION);
        return $next->handle($request);
    }
);

$app->addErrorMiddleware(true, true, true);
$router = $app->getRouteCollector()->getRouteParser();

$app->get('/', function ($request, $response) {
    $flash = $this->get('flash')->getMessages();

    $params = [
        'currentUser' => $_SESSION['user'] ?? null,
        'flash' => $flash
    ];
    return $this->get('renderer')->render($response->write('Welcome to Slim!'), 'index.phtml', $params);
});

$app->post('/session', function ($request, $response) use ($repo) {
    $userData = $request->getParsedBodyParam('user');

    $foundUser = null;
    foreach ($repo->all() as $user) {
        if ($user['name'] === $userData['name']
            && $user['passwordDigest'] === hash('sha256', $userData['password'])
        ) {
            $foundUser = $user;
            break;
        }
    }

    if ($foundUser) {
        $_SESSION['user'] = $foundUser;
    } else {
        $this->get('flash')->addMessage('error', 'Wrong password or name');
    }
    return $response->withRedirect('/');
});

$app->delete('/session', function ($request, $response) {
    $_SESSION = [];
    session_destroy();
    return $response->withRedirect('/');
});

$app->get('/users', function ($request, $response) use ($repo) {
    $term = $request->getQueryParam('term', '');
    $allUsers = $repo->all();
    $filteredUsers = [];
    
    if ($term === '') {
        $filteredUsers = $allUsers;
    } else {
        foreach ($allUsers as $user) {
            if (str_contains(strtolower($user['name']), strtolower($term))) {
                $filteredUsers[] = $user;
            }
        }
    }

    $messages = $this->get('flash')->getMessages();
    $msg = $messages['error'][0] ?? ($messages['success'][0] ?? '');

    $params = ['users' => $filteredUsers, 'term' => $term, 'msg' => $msg];
    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
})->setName('users');

$app->post('/users', function ($request, $response) use ($router, $repo) {
    $user = $request->getParsedBodyParam('user');

    $validator = new App\Validator();
    $errors = $validator->validate($user);
    
    if (count($errors) === 0) {
        $id = $repo->save($user);

        if ($id === "") {
            $this->get('flash')->addMessage('error', 'Such user already exist');
        } else {
            $this->get('flash')->addMessage('success', 'User has been created');
        }

        $url = $router->urlFor('users');
        return $response->withRedirect($url);
    }

    $params = [
        'user' => $user,
        'errors' => $errors
    ];

    $response->withStatus(422);
    return $this->get('renderer')->render($response, "users/new.phtml", $params);
});

$app->get('/users/new', function ($request, $response) {
    $params = [
        'user' => ['name' => $user['name'] ?? '', 'email' => $user['email'] ?? ''],
        'errors' => []
    ];
    return $this->get('renderer')->render($response, "users/new.phtml", $params);
})->setName('newUser');

$app->get('/users/{id}/edit', function ($request, $response, array $args) use ($repo) {
    $id = $args['id'];
    $user = $repo->find($id);
    
    $params = [
        'user' => $user,
        'errors' => []
    ];

    return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
})->setName('editUser');

$app->patch('/users/{id}', function ($request, $response, array $args) use ($router, $repo) {
    $id = $args['id'];
    $user = $repo->find($id);

    $userData = $request->getParsedBodyParam('user');

    $validator = new Validator();
    $errors = $validator->validate($userData);

    if (count($errors) === 0) {
        $user['name'] = $userData['name'];
        $user['email'] = $userData['email'];

        $id = $repo->save($user);
        if ($id === "") {
            $this->get('flash')->addMessage('error', 'Such user already exist');
        } else {
            $this->get('flash')->addMessage('success', 'User has been updated');
        }
        
        $url = $router->urlFor('users');
        return $response->withRedirect($url);
    }

    $params = [
        'user' => $user,
        'errors' => $errors
    ];

    $url = $router->urlFor('editUser', ['id' => $user['id']]);
    $response = $response->withRedirect($url)->withStatus(422);
    return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
});

$app->get('/users/{id}/delete', function ($request, $response, array $args) use ($repo) {
    $id = $args['id'];
    $user = $repo->find($id);
    
    $params = [
        'user' => $user
    ];
    return $this->get('renderer')->render($response, 'users/delete.phtml', $params);
})->setName('deleteUser');

$app->delete('/users/{id}', function ($request, $response, array $args) use ($router, $repo) {
    $id = $args['id'];
    $repo->destroy($id);
    $this->get('flash')->addMessage('success', 'User has been deleted');
    return $response->withRedirect($router->urlFor('users'));
});

$app->get('/users/{id:[0-9]+}', function ($request, $response, $args) {
    $params = ['id' => $args['id'], 'nickname' => 'user-' . $args['id']];
    return $this->get('renderer')->render($response, 'users/show.phtml', $params);
});

$app->get('/courses', function ($request, $response) use ($courses) {
    $params = ['courses' => $courses];
    return $this->get('renderer')->render($response, 'courses/index.phtml', $params);
});

$app->get('/courses/{id:[0-9]+}', function ($request, $response, array $args) {
    $id = $args['id'];
    return $response->write("Course id: {$id}");
});

$app->run();
