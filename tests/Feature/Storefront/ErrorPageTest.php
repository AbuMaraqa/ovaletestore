<?php

it('shows the branded 404 page with the store layout data, even for URLs that match no route', function () {
    $this->get('/ar/this-page-does-not-exist')->assertNotFound()
        ->assertInertia(fn ($page) => $page->component('Storefront/Error')
            ->where('status', 404)
            ->has('site.name')
            ->has('translations')
            ->where('seo.robots', fn ($robots) => str_contains((string) $robots, 'noindex')));
});

it('renders the 404 page in the language of the URL', function () {
    $this->get('/en/this-page-does-not-exist')->assertNotFound()
        ->assertInertia(fn ($page) => $page->where('locale.current', 'en'));
});

it('keeps JSON 404s as JSON', function () {
    $this->getJson('/ar/this-page-does-not-exist')->assertNotFound()->assertJsonStructure(['message']);
});
