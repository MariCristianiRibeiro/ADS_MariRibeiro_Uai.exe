<?php

it('redirects the root page to login', function () {
    $this->get('/')->assertRedirect(route('login'));
});
