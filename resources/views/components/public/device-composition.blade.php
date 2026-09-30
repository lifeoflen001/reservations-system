<div class="public-device-composition" role="group" aria-label="Responsive Lodgix product preview">
    <div class="public-device-composition__primary">
        <x-public.screenshot-frame
            browser-shell
            src="assets/images/landing/lodgix-dashboard-light.webp"
            :width="1654"
            :height="921"
            aspect-ratio="1654 / 921"
            alt="Sanitized Lodgix desktop product screen."
            loading="lazy"
        />
    </div>
    <div class="public-device-composition__tablet">
        <x-public.screenshot-frame
            browser-shell
            aspect-ratio="4 / 3"
            alt="Sanitized Lodgix tablet product screen."
            placeholder-title="Tablet screenshot required"
            placeholder-text="Use a sanitized demo capture for the tablet view."
        />
    </div>
    <div class="public-device-composition__mobile">
        <x-public.screenshot-frame
            browser-shell
            src="assets/images/landing/lodgix-dashboard-mobile.webp"
            :width="485"
            :height="887"
            aspect-ratio="485 / 887"
            alt="Sanitized Lodgix mobile product screen."
            loading="lazy"
        />
    </div>
</div>
