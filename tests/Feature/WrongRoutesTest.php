<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WrongRoutesTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testWrongRoutes()
    {
        $response = $this->get('/wrongroutes');
        $response->assertStatus(404);
    }


}
