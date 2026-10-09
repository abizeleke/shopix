<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

$routes->get('/', 'Home::index');

$routes->get('api/database-test', 'DatabaseTest::index');

$routes->get('api/activation-test', 'Api\ActivationController::test');

$routes->post('api/activation', 'Api\ActivationController::activate');

$routes->post('api/login', 'Api\AuthController::login');


// ============================================================
// CATEGORIES
// ============================================================

$routes->group('api/categories', ['filter' => 'auth'], static function ($routes) {

    $routes->get('/', 'Api\CategoryController::index');

    $routes->get('(:num)', 'Api\CategoryController::show/$1');

    $routes->post('/', 'Api\CategoryController::create');

    $routes->put('(:num)', 'Api\CategoryController::update/$1');

    $routes->delete('(:num)', 'Api\CategoryController::delete/$1');
});


// ============================================================
// PRODUCTS
// ============================================================

$routes->group('api/products', ['filter' => 'auth'], static function ($routes) {

    $routes->get('/', 'Api\ProductController::index');

    $routes->get('(:num)', 'Api\ProductController::show/$1');

    $routes->post('/', 'Api\ProductController::create');

    $routes->put('(:num)', 'Api\ProductController::update/$1');

    $routes->delete('(:num)', 'Api\ProductController::delete/$1');
});


// ============================================================
// SALES
// ============================================================

$routes->group('api/sales', ['filter' => 'auth'], static function ($routes) {

    // Get sales
    $routes->get('/', 'Api\SalesController::index');

    // Create sale
    $routes->post('/', 'Api\SalesController::create');

    // Cancel sale - ADMIN ONLY
    $routes->post('(:num)/cancel', 'Api\SalesController::cancel/$1');
});
// ============================================================
// SALES PEOPLE
// ============================================================

$routes->group('api/sales-people', ['filter' => 'auth'], static function ($routes) {

    // Get all sales people
    $routes->get('/', 'Api\SalesPeopleController::index');

    // Create sales person
    $routes->post('/', 'Api\SalesPeopleController::create');

    // Update sales person
    $routes->put('(:num)', 'Api\SalesPeopleController::update/$1');

    // Reset password
    $routes->post(
        '(:num)/reset-password',
        'Api\SalesPeopleController::resetPassword/$1'
    );

    // Enable / disable
    $routes->put(
        '(:num)/status',
        'Api\SalesPeopleController::status/$1'
    );
});


$routes->group('api/notes', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'Api\NotesController::index');
    $routes->post('/', 'Api\NotesController::create');
    $routes->put('(:num)', 'Api\NotesController::update/$1');
    $routes->delete('(:num)', 'Api\NotesController::delete/$1');
});


$routes->group('api/account', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'Api\AccountController::index');
    $routes->put('/', 'Api\AccountController::update');
    $routes->put('password', 'Api\AccountController::changePassword');
});

$routes->group('api/sales', ['filter' => 'auth'], static function ($routes) {

    $routes->get('/', 'Api\SalesController::index');

    $routes->get('dashboard', 'Api\SalesController::dashboard');

    $routes->post('/', 'Api\SalesController::create');

    $routes->delete('(:num)', 'Api\SalesController::cancel/$1');
});

$routes->group('api/salesperson-sales', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'Api\SalespersonSalesController::index');
    $routes->post('/', 'Api\SalespersonSalesController::create');
});


// ============================================================
// SALESPERSON ACCOUNT
// ============================================================

$routes->group(
    'api/salesperson-account',
    ['filter' => 'auth'],
    static function ($routes) {
        $routes->get(
            '/',
            'Api\SalespersonAccountController::index'
        );

        $routes->put(
            '/',
            'Api\SalespersonAccountController::update'
        );

        $routes->put(
            'password',
            'Api\SalespersonAccountController::changePassword'
        );
    }
);