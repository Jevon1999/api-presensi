<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * @OA\Info(
 *     title="PKL Attendance API",
 *     version="1.0.0",
 *     description="API untuk sistem absensi PKL menggunakan WhatsApp Bot & Web Admin",
 *     @OA\Contact(
 *         email="admin@example.com",
 *         name="API Support"
 *     ),
 *     @OA\License(
 *         name="MIT",
 *         url="https://opensource.org/licenses/MIT"
 *     )
 * )
 * 
 * @OA\Server(
 *     url="http://localhost:8000",
 *     description="Local Development Server"
 * )
 * 
 * @OA\Server(
 *     url="https://api-production.example.com",
 *     description="Production Server"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="Sanctum",
 *     description="Enter token dari endpoint /api/login"
 * )
 * 
 * @OA\Tag(
 *     name="Authentication",
 *     description="Endpoint untuk login/logout admin"
 * )
 * 
 * @OA\Tag(
 *     name="Attendance",
 *     description="Endpoint untuk check-in/check-out dan manage attendance"
 * )
 * 
 * @OA\Tag(
 *     name="Members",
 *     description="CRUD member/peserta PKL"
 * )
 * 
 * @OA\Tag(
 *     name="Offices",
 *     description="CRUD office/kantor dan lokasi geofencing"
 * )
 * 
 * @OA\Tag(
 *     name="Progress",
 *     description="Laporan progress harian member"
 * )
 */
class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}