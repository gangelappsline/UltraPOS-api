<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

/**
 * Framework base controller (Laravel 12 style: no parent class).
 */
abstract class Controller
{
    use AuthorizesRequests;
    use ValidatesRequests;
}
