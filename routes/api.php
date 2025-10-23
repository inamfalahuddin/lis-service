<?php

use Dingo\Api\Routing\Router;
use Illuminate\Http\Request;
use Specialtactics\L5Api\Http\Middleware\CheckUserRole;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

/*
 * Welcome route - link to any public API documentation here
 */
// Route::get('/', function () {
//     echo 'Welcome to our API';
// });

Route::get('/', function () {
    return view('welcome');
});

/** @var \Dingo\Api\Routing\Router $api */
$api = app('Dingo\Api\Routing\Router');
$api->version('v1', ['middleware' => ['api']], function (Router $api) {
    /*
     * Authentication
     */
    $api->group(['prefix' => 'auth'], function (Router $api) {
        $api->group(['prefix' => 'jwt'], function (Router $api) {
            $api->get('/token', 'App\Http\Controllers\Auth\AuthController@token');
        });
    });

    /*
     * Authenticated routes
     */
    $api->group(['middleware' => ['api.auth']], function (Router $api) {
        /*
         * Authentication
         */
        $api->group(['prefix' => 'auth'], function (Router $api) {
            $api->group(['prefix' => 'jwt'], function (Router $api) {
                $api->get('/refresh', 'App\Http\Controllers\Auth\AuthController@refresh');
                $api->delete('/token', 'App\Http\Controllers\Auth\AuthController@logout');
            });

            $api->get('/me', 'App\Http\Controllers\Auth\AuthController@getUser');
        });

        /*
         * Users
         */
        $api->group(['prefix' => 'users', 'middleware' => 'check_role:admin'], function (Router $api) {
            $api->get('/', 'App\Http\Controllers\UserController@getAll');
            $api->get('/{uuid}', 'App\Http\Controllers\UserController@get');
            $api->post('/', 'App\Http\Controllers\UserController@post');
            $api->put('/{uuid}', 'App\Http\Controllers\UserController@put');
            $api->patch('/{uuid}', 'App\Http\Controllers\UserController@patch');
            $api->delete('/{uuid}', 'App\Http\Controllers\UserController@delete');
        });

        /*
         * Roles
         */
        $api->group(['prefix' => 'roles'], function (Router $api) {
            $api->get('/', 'App\Http\Controllers\RoleController@getAll');
        });


        /*
         * GET
         */
        $api->get('/result', 'App\Http\Controllers\ResultsController@info');
        $api->get('/result/no_order', 'App\Http\Controllers\ResultsController@get_by_no_lab');
        $api->get('/result/periode', 'App\Http\Controllers\ResultsController@get_by_periode');
        $api->get('/result/mrn_periode', 'App\Http\Controllers\ResultsController@get_by_mrn_periode');
        $api->get('/result/all_periode', 'App\Http\Controllers\ResultsController@get_by_mrn_all_periode');

        /*
         * POST
         */
        $api->post('/order', 'App\Http\Controllers\OrderController@order');
        $api->post('/order_me', 'App\Http\Controllers\OrderController@order_me');
        $api->post('/pasien', 'App\Http\Controllers\PasienController@pasien');


        /*
         * WEBHOOK
         */
        $api->group(['prefix' => 'webhook'], function (Router $api) {
            $api->post('/order', 'App\Http\Controllers\WebhookController@order');
        });
    });
});
