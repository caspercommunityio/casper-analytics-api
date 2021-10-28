<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HoldersTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testGetHoldersListSuccess()
    {
        $response = $this->get('/holders');
        $response->assertStatus(200);
    }

   public function testGetHoldersListError404()
   {
       $response = $this->get('/holders/123');
       $response->assertStatus(404);
   }

}
