<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MoveGuideContentRequest;
use App\Http\Requests\Admin\UpdateGuidePageRequest;
use App\Http\Requests\Admin\UpsertGuideSectionRequest;
use App\Http\Requests\Admin\UpsertGuideStepRequest;
use App\Models\GuidePage;
use App\Models\GuideSection;
use App\Models\GuideStep;
use App\Services\Guides\GuideContentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Coordinates the dedicated platform guide management page.
 *
 * @since 2.0.1
 */
class SystemGuideController extends Controller
{
    /**
     * Display the guide master-detail management page.
     *
     * @param  Request  $request  Incoming dashboard request.
     * @param  GuideContentService  $content  Guide domain service.
     * @return View Rendered guide manager.
     *
     * @since 2.0.1
     */
    public function edit(Request $request, GuideContentService $content): View
    {
        $page = $content->guidePage();

        return view('admin.system.guide.edit', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'page' => $page,
            'editingSection' => $page->sections->firstWhere('id', $request->integer('section')),
            'editingStep' => $page->sections->flatMap->steps->firstWhere('id', $request->integer('step')),
            'addStepSection' => $page->sections->firstWhere('id', $request->integer('add_step')),
        ]);
    }

    /**
     * Update guide page title and introduction.
     *
     * @param  UpdateGuidePageRequest  $request  Validated page request.
     * @param  GuideContentService  $content  Guide domain service.
     * @return RedirectResponse Redirect to the guide manager.
     *
     * @since 2.0.1
     */
    public function update(UpdateGuidePageRequest $request, GuideContentService $content): RedirectResponse
    {
        $content->updatePage($content->guidePage(), $request->validated(), $request->user()?->id);

        return $this->backToGuide(__('guide.page_saved'));
    }

    /**
     * Create one guide section.
     *
     * @param  UpsertGuideSectionRequest  $request  Validated section request.
     * @param  GuideContentService  $content  Guide domain service.
     * @return RedirectResponse Redirect to the created section.
     *
     * @since 2.0.1
     */
    public function storeSection(UpsertGuideSectionRequest $request, GuideContentService $content): RedirectResponse
    {
        $section = $content->createSection($content->guidePage(), $request->validated(), $request->user()?->id);

        return $this->backToGuide(__('guide.section_created'), 'guide-section-'.$section->id);
    }

    /**
     * Update one guide section.
     *
     * @param  UpsertGuideSectionRequest  $request  Validated section request.
     * @param  GuideSection  $guideSection  Section selected by route binding.
     * @param  GuideContentService  $content  Guide domain service.
     * @return RedirectResponse Redirect to the updated section.
     *
     * @since 2.0.1
     */
    public function updateSection(UpsertGuideSectionRequest $request, GuideSection $guideSection, GuideContentService $content): RedirectResponse
    {
        $this->ensureGuideSection($guideSection);
        $content->updateSection($guideSection, $request->validated(), $request->user()?->id);

        return $this->backToGuide(__('guide.section_saved'), 'guide-section-'.$guideSection->id);
    }

    /**
     * Delete one guide section and its cascading steps.
     *
     * @param  Request  $request  Incoming dashboard request.
     * @param  GuideSection  $guideSection  Section selected by route binding.
     * @return RedirectResponse Redirect after deleting the section.
     *
     * @since 2.0.1
     */
    public function destroySection(Request $request, GuideSection $guideSection): RedirectResponse
    {
        $this->ensureGuideSection($guideSection);
        $guideSection->delete();

        return $this->backToGuide(__('guide.section_deleted'));
    }

    /**
     * Move one guide section to its adjacent position.
     *
     * @param  MoveGuideContentRequest  $request  Validated ordering request.
     * @param  GuideSection  $guideSection  Section selected by route binding.
     * @param  GuideContentService  $content  Guide domain service.
     * @return RedirectResponse Redirect to the reordered section.
     *
     * @since 2.0.1
     */
    public function moveSection(MoveGuideContentRequest $request, GuideSection $guideSection, GuideContentService $content): RedirectResponse
    {
        $this->ensureGuideSection($guideSection);
        $content->moveSection($guideSection, $request->string('direction')->toString());

        return $this->backToGuide(__('guide.order_saved'), 'guide-section-'.$guideSection->id);
    }

    /**
     * Create one step within a guide section.
     *
     * @param  UpsertGuideStepRequest  $request  Validated step request.
     * @param  GuideSection  $guideSection  Parent section selected by route binding.
     * @param  GuideContentService  $content  Guide domain service.
     * @return RedirectResponse Redirect to the created step.
     *
     * @since 2.0.1
     */
    public function storeStep(UpsertGuideStepRequest $request, GuideSection $guideSection, GuideContentService $content): RedirectResponse
    {
        $this->ensureGuideSection($guideSection);
        $step = $content->createStep($guideSection, $request->validated(), $request->user()?->id);

        return $this->backToGuide(__('guide.step_created'), 'guide-step-'.$step->id);
    }

    /**
     * Update one guide step.
     *
     * @param  UpsertGuideStepRequest  $request  Validated step request.
     * @param  GuideStep  $guideStep  Step selected by route binding.
     * @param  GuideContentService  $content  Guide domain service.
     * @return RedirectResponse Redirect to the updated step.
     *
     * @since 2.0.1
     */
    public function updateStep(UpsertGuideStepRequest $request, GuideStep $guideStep, GuideContentService $content): RedirectResponse
    {
        $this->ensureGuideStep($guideStep);
        $content->updateStep($guideStep, $request->validated(), $request->user()?->id);

        return $this->backToGuide(__('guide.step_saved'), 'guide-step-'.$guideStep->id);
    }

    /**
     * Delete one guide step.
     *
     * @param  Request  $request  Incoming dashboard request.
     * @param  GuideStep  $guideStep  Step selected by route binding.
     * @return RedirectResponse Redirect after deleting the step.
     *
     * @since 2.0.1
     */
    public function destroyStep(Request $request, GuideStep $guideStep): RedirectResponse
    {
        $this->ensureGuideStep($guideStep);
        $sectionId = $guideStep->guide_section_id;
        $guideStep->delete();

        return $this->backToGuide(__('guide.step_deleted'), 'guide-section-'.$sectionId);
    }

    /**
     * Move one guide step to its adjacent position.
     *
     * @param  MoveGuideContentRequest  $request  Validated ordering request.
     * @param  GuideStep  $guideStep  Step selected by route binding.
     * @param  GuideContentService  $content  Guide domain service.
     * @return RedirectResponse Redirect to the reordered step.
     *
     * @since 2.0.1
     */
    public function moveStep(MoveGuideContentRequest $request, GuideStep $guideStep, GuideContentService $content): RedirectResponse
    {
        $this->ensureGuideStep($guideStep);
        $content->moveStep($guideStep, $request->string('direction')->toString());

        return $this->backToGuide(__('guide.order_saved'), 'guide-step-'.$guideStep->id);
    }

    /**
     * Ensure the bound section belongs to the guide page.
     *
     * @param  GuideSection  $section  Bound section.
     * @return void No value is returned.
     *
     * @since 2.0.1
     */
    private function ensureGuideSection(GuideSection $section): void
    {
        abort_unless($section->page()->where('key', GuidePage::GUIDE)->exists(), 404);
    }

    /**
     * Ensure the bound step belongs to the guide page.
     *
     * @param  GuideStep  $step  Bound step.
     * @return void No value is returned.
     *
     * @since 2.0.1
     */
    private function ensureGuideStep(GuideStep $step): void
    {
        abort_unless($step->section()->whereHas('page', fn ($query) => $query->where('key', GuidePage::GUIDE))->exists(), 404);
    }

    /**
     * Build a guide management redirect with an optional fragment.
     *
     * @param  string  $message  Flash status message.
     * @param  string|null  $fragment  Optional destination fragment.
     * @return RedirectResponse Guide management redirect.
     *
     * @since 2.0.1
     */
    private function backToGuide(string $message, ?string $fragment = null): RedirectResponse
    {
        $url = route('admin.system.guide.edit').($fragment ? '#'.$fragment : '');

        return redirect()->to($url)->with('status', $message);
    }
}
