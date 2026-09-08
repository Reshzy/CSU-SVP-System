<?php

namespace App\Http\Controllers\Supply;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Named so queued submit mail can link here. Review actions arrive in Slice 4.
 */
class PurchaseRequestController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('supply/purchase-requests/index');
    }
}
