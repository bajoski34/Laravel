<?php

use function Pest\Laravel\get;

it('has a welcome page')->get('/')->assertStatus(200);
