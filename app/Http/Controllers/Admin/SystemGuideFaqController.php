<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MoveGuideContentRequest;
use App\Http\Requests\Admin\UpdateGuidePageRequest;
use App\Http\Requests\Admin\UpsertGuideFaqRequest;
use App\Models\GuideFaq;
use App\Models\GuidePage;
use App\Services\Guides\GuideContentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Coordinates the dedicated platform FAQ management page.
 *
 * @since 2.0.1
 */
class SystemGuideFaqController extends Controller
{
    /**
     * Display the FAQ master-detail management page.
     *
     * @param  Request  $request  Incoming dashboard request.
     * @param  GuideContentService  $content  Guide domain service.
     * @return View Rendered FAQ manager.
     *
     * @since 2.0.1
     */
    public function edit(Request $request, GuideContentService $content): View
    {
        $page = $content->faqPage();

        return view('admin.system.guide.faq', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'page' => $page,
            'editingFaq' => $page->faqs->firstWhere('id', $request->integer('faq')),
        ]);
    }

    /**
     * Update FAQ page title and introduction.
     *
     * @param  UpdateGuidePageRequest  $request  Validated page request.
     * @param  GuideContentService  $content  Guide domain service.
     * @return RedirectResponse Redirect to the FAQ manager.
     *
     * @since 2.0.1
     */
    public function update(UpdateGuidePageRequest $request, GuideContentService $content): RedirectResponse
    {
        $content->updatePage($content->faqPage(), $request->validated(), $request->user()?->id);

        return $this->backToFaq(__('guide.page_saved'));
    }

    /**
     * Create one frequently asked question.
     *
     * @param  UpsertGuideFaqRequest  $request  Validated FAQ request.
     * @param  GuideContentService  $content  Guide domain service.
     * @return RedirectResponse Redirect to the created FAQ.
     *
     * @since 2.0.1
     */
    public function store(UpsertGuideFaqRequest $request, GuideContentService $content): RedirectResponse
    {
        $faq = $content->createFaq($content->faqPage(), $request->validated(), $request->user()?->id);

        return $this->backToFaq(__('guide.faq_created'), 'managed-faq-'.$faq->id);
    }

    /**
     * Update one frequently asked question.
     *
     * @param  UpsertGuideFaqRequest  $request  Validated FAQ request.
     * @param  GuideFaq  $guideFaq  FAQ selected by route binding.
     * @param  GuideContentService  $content  Guide domain service.
     * @return RedirectResponse Redirect to the updated FAQ.
     *
     * @since 2.0.1
     */
    public function updateItem(UpsertGuideFaqRequest $request, GuideFaq $guideFaq, GuideContentService $content): RedirectResponse
    {
        $this->ensureFaq($guideFaq);
        $content->updateFaq($guideFaq, $request->validated(), $request->user()?->id);

        return $this->backToFaq(__('guide.faq_saved'), 'managed-faq-'.$guideFaq->id);
    }

    /**
     * Delete one frequently asked question.
     *
     * @param  Request  $request  Incoming dashboard request.
     * @param  GuideFaq  $guideFaq  FAQ selected by route binding.
     * @return RedirectResponse Redirect after deleting the FAQ.
     *
     * @since 2.0.1
     */
    public function destroy(Request $request, GuideFaq $guideFaq): RedirectResponse
    {
        $this->ensureFaq($guideFaq);
        $guideFaq->delete();

        return $this->backToFaq(__('guide.faq_deleted'));
    }

    /**
     * Move one FAQ to its adjacent position.
     *
     * @param  MoveGuideContentRequest  $request  Validated ordering request.
     * @param  GuideFaq  $guideFaq  FAQ selected by route binding.
     * @param  GuideContentService  $content  Guide domain service.
     * @return RedirectResponse Redirect to the reordered FAQ.
     *
     * @since 2.0.1
     */
    public function move(MoveGuideContentRequest $request, GuideFaq $guideFaq, GuideContentService $content): RedirectResponse
    {
        $this->ensureFaq($guideFaq);
        $content->moveFaq($guideFaq, $request->string('direction')->toString());

        return $this->backToFaq(__('guide.order_saved'), 'managed-faq-'.$guideFaq->id);
    }

    /**
     * Ensure the bound question belongs to the FAQ page.
     *
     * @param  GuideFaq  $faq  Bound FAQ.
     * @return void No value is returned.
     *
     * @since 2.0.1
     */
    private function ensureFaq(GuideFaq $faq): void
    {
        abort_unless($faq->page()->where('key', GuidePage::FAQ)->exists(), 404);
    }

    /**
     * Build a FAQ management redirect with an optional fragment.
     *
     * @param  string  $message  Flash status message.
     * @param  string|null  $fragment  Optional destination fragment.
     * @return RedirectResponse FAQ management redirect.
     *
     * @since 2.0.1
     */
    private function backToFaq(string $message, ?string $fragment = null): RedirectResponse
    {
        $url = route('admin.system.faq.edit').($fragment ? '#'.$fragment : '');

        return redirect()->to($url)->with('status', $message);
    }
}
