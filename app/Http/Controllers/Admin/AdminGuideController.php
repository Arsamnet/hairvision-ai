<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Guides\GuideContentService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Displays the clinic dashboard guide and FAQ pages.
 *
 * @since 2.0.1
 */
class AdminGuideController extends Controller
{
    /**
     * Display the shared guide for an authenticated dashboard user.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  GuideContentService  $guide  Structured guide content service.
     * @return View Rendered guide page.
     *
     * @since 2.0.1
     */
    public function show(Request $request, GuideContentService $guide): View
    {
        return view('admin.guide.show', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'guide' => $guide->guide(),
            'faqCount' => count($guide->faq()['faqs']),
        ]);
    }

    /**
     * Display frequently asked questions for authenticated clinic users.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  GuideContentService  $guide  Structured guide content service.
     * @return View Rendered FAQ page.
     *
     * @since 2.0.1
     */
    public function faq(Request $request, GuideContentService $guide): View
    {
        return view('admin.guide.faq', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'faq' => $guide->faq(),
        ]);
    }
}
