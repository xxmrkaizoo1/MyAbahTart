<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $data = app(AdminCategoryController::class)->index()->getData();

        return response()->json(['categories' => CategoryResource::collection($data['categories'])]);
    }
}
