<?php

namespace App\Http\Controllers;

use Specialtactics\L5Api\Http\Controllers\RestfulController as BaseController;

abstract class MshController extends BaseController
{
    protected function getMshData()
    {
        return [
            'product' => env('LIS_PRODUCT_NAME'),
            'version' => env('LIS_VERSION'),
            'user_id' => env('LIS_USER_ID'),
            'key' => env('LIS_SECRET_KEY')
        ];
    }
}
