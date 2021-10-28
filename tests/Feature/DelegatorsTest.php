<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DelegatorsTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testGetDelegatorsListSuccess()
    {
        $response = $this->get('/delegators/list');
        $response->assertStatus(200);
    }

    public function testGetDelegatorsListError1()
    {
        $response = $this->get('/delegators/list/1');
        $response->assertStatus(404);
    }

    public function testGetDelegatorsListError2()
    {
        $response = $this->get('/delegators/listError');
        $response->assertStatus(404);
    }

    public function testGetDelegatorsListError3()
    {
        $response = $this->get('/delegators');
        $response->assertStatus(404);
    }

}
