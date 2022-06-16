<?php

namespace App\Repositories\Generic\GenericInterface;

use App\Models\Country;
use App\Models\People;
use Illuminate\Http\Request;
use PhpParser\Node\Expr\Cast\Double;

interface GenericRepositoryInterface
{
    public function all();

    public function create(array $data);

    public function update(array $data, $id);

    public function delete($id);

    public function show($id);

    public function findByAttribute($attribute, $value);



    public function verifyPin(Request $data , string $pin);

    public function verifySolde(int $amount , array $account , int $frais);

    public function verifyCountry(string $number , string $number2);




    // public function validateData($rules = [], $messages = []);

}
