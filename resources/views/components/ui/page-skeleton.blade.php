<div class="app-page-skeleton" data-app-page-skeleton hidden aria-hidden="true">
    <main class="app-page-skeleton__main">
            <div class="app-page-skeleton__heading">
                <div><x-ui.skeleton class="app-page-skeleton__title" /><x-ui.skeleton class="app-page-skeleton__subtitle" /></div>
                <x-ui.skeleton class="app-page-skeleton__button" />
            </div>
            <x-ui.skeleton class="app-page-skeleton__filters" />
            <div class="app-page-skeleton__cards">
                @for($card = 0; $card < 4; $card++)<x-ui.skeleton class="app-page-skeleton__card" />@endfor
            </div>
            <x-ui.skeleton class="app-page-skeleton__table" />
    </main>
</div>
