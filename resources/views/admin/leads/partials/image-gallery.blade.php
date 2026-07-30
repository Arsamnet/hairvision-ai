@if($leadGalleryItems !== [])
    <div data-lead-gallery-items hidden>
        @foreach($leadGalleryItems as $galleryItem)
            <span
                data-lead-gallery-item
                data-gallery-key="{{ $galleryItem['key'] }}"
                data-gallery-src="{{ $galleryItem['url'] }}"
                data-gallery-title="{{ $galleryItem['title'] }}"
                data-gallery-description="{{ $galleryItem['description'] }}"
                data-gallery-file-name="{{ $galleryItem['fileName'] }}"
            ></span>
        @endforeach
    </div>

    <div class="lead-image-gallery hidden" data-lead-gallery-modal aria-hidden="true">
        <div class="lead-image-gallery-backdrop" data-lead-gallery-backdrop aria-hidden="true"></div>

        <section
            class="lead-image-gallery-dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="lead-image-gallery-dialog-title"
        >
            <header class="lead-image-gallery-toolbar">
                <div class="flex min-w-0 items-center gap-2">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-teal-50 text-teal-700">
                        <x-admin.icon name="gallery" class="h-4 w-4" />
                    </span>
                    <div class="min-w-0">
                        <h2 id="lead-image-gallery-dialog-title" class="truncate text-sm font-bold text-zinc-900">
                            {{ __('leads.gallery.title') }}
                        </h2>
                        <p class="mt-0.5 text-xs font-bold text-zinc-500" data-lead-gallery-counter></p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <x-admin.button href="#" variant="secondary" size="compact-sm" icon="arrow-down" data-lead-gallery-download>
                        {{ __('leads.gallery.download') }}
                    </x-admin.button>
                    <x-admin.button type="button" variant="subtle" size="icon-sm" icon="x" aria-label="{{ __('leads.gallery.close') }}" data-lead-gallery-close />
                </div>
            </header>

            <div class="lead-image-gallery-stage">
                <x-admin.button type="button" variant="subtle" size="icon-md" icon="chevron-right" aria-label="{{ __('leads.gallery.previous') }}" data-lead-gallery-previous />

                <figure class="lead-image-gallery-figure">
                    <span class="lead-image-gallery-loader" data-lead-gallery-loader aria-hidden="true"></span>
                    <img class="lead-image-gallery-image" data-lead-gallery-image alt="">
                </figure>

                <x-admin.button type="button" variant="subtle" size="icon-md" icon="chevron-left" aria-label="{{ __('leads.gallery.next') }}" data-lead-gallery-next />
            </div>

            <footer class="lead-image-gallery-caption">
                <div class="min-w-0 flex-1">
                    <h3 class="text-sm font-bold text-zinc-900" data-lead-gallery-title></h3>
                    <p class="mt-1 text-xs leading-6 text-zinc-500" data-lead-gallery-description></p>
                </div>
            </footer>
        </section>
    </div>
@endif
