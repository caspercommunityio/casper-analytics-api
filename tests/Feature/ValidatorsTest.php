<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class ValidatorsTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testGetValidatorsSuccess()
    {
        $response = $this->get('/validators');
        $response->assertStatus(200);
    }

   public function testGetValidatorsError()
   {
       $response = $this->get('/validators123');
       $response->assertStatus(404);
   }
   public function testGetValidatorDetails()
   {
       $response = $this->get('/validator/delegations/1234');
       $response->assertStatus(200);
   }

   public function testGetValidatorsJsonSuccess()
   {
       $response = $this->get('/validators');
       print_r($response);
       $response->assertStatus(200)
                ->assertJsonPath("validators.01c6d11a0fa563f8cc3ed5e967d5901c80004bdcde6250ddea18af2b4eae0a902d.publicKey","01c6d11a0fa563f8cc3ed5e967d5901c80004bdcde6250ddea18af2b4eae0a902d");
   }
   public function testGetValidatorsInfoJsonSuccess()
   {
       $response = $this->get('/validator/infos/012Bac1d0ff9240Ff0B7b06D555815640497861619Ca12583dDEf434885416E69B');
       $response->assertStatus(200)
                ->assertJsonPath("publicKey","012bac1d0ff9240ff0b7b06d555815640497861619ca12583ddef434885416e69b");
   }

   public function testGetValidatorsInfoJsonError()
   {
       $response = $this->get('/validator/infos/1234');
       $this->assertEquals($response->getContent(), "Unknow validator");
   }

   public function testGetValidatorsChartsJsonSuccess()
   {
       $response = $this->get('/validator/charts/012Bac1d0ff9240Ff0B7b06D555815640497861619Ca12583dDEf434885416E69B');
       $response->assertSeeText('apy',false)
                ->assertSeeText('rewards',false)
                ->assertSeeText('delegators',false)
                ->assertSeeText('delegations',false)
                ->assertSeeText('undelegations',false)
                ->assertSeeText('fees',false)
                ->assertSeeText('price',false)
                ->assertSeeText('csprStaked',false);
   }


   public function testGetValidatorsChartsJsonError()
   {
       $response = $this->get('/validator/charts/1234');
       $this->assertEquals($response->getContent(), "Unknow validator");
   }

   public function testGetValidatorsListSuccess()
   {
       $response = $this->get('/validators/list');
       $response->assertStatus(200);
   }

   public function testGetValidatorsListError1()
   {
       $response = $this->get('/validators/list/1');
       $response->assertStatus(404);
   }

   public function testGetValidatorsListError2()
   {
       $response = $this->get('/validators/listError');
       $response->assertStatus(404);
   }

}
