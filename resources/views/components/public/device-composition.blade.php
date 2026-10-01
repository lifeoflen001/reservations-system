<div class="public-device-composition" role="group" aria-label="Responsive Lodgix product preview">
    <div class="public-device-composition__primary">
        <x-public.screenshot-frame
            browser-shell
            src="assets/images/landing/lodgix-dashboard-light.jpg"
            :srcset="asset('assets/images/landing/lodgix-dashboard-light-960.jpg').' 960w, '.asset('assets/images/landing/lodgix-dashboard-light-1440.jpg').' 1440w, '.asset('assets/images/landing/lodgix-dashboard-light.jpg').' 1654w'"
            sizes="(max-width: 900px) calc(100vw - 36px), 58vw"
            :width="1654"
            :height="921"
            aspect-ratio="1654 / 921"
            alt="Lodgix desktop dashboard with hotel operations summaries."
            loading="lazy"
        />
    </div>
    <div class="public-device-composition__tablet">
        <x-public.screenshot-frame
            browser-shell
            aspect-ratio="4 / 3"
            alt="Illustrative Lodgix tablet feature view, not a product screenshot."
            placeholder-title="Illustrative view"
            placeholder-text="A visual summary of hotel workflows, not a live application screen."
        />
    </div>
    <div class="public-device-composition__mobile">
        <x-public.screenshot-frame
            browser-shell
            src="assets/images/landing/lodgix-dashboard-mobile.webp"
            :width="485"
            :height="887"
            aspect-ratio="485 / 887"
            alt="Lodgix mobile dashboard showing hotel operations summaries."
            loading="lazy"
        />
    </div>
</div>
