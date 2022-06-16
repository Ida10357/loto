<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Repositories\Implementation;

use App\Models\Number;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Repositories\Generic\GenericImplementation\GenericRepository;

/**
 * Description of NumberRepository
 *
 * @author Sariel
 */
class NumberRepository extends GenericRepository{
    use ApiResponser;
private $operatorRepo;
    public function __construct( OperatorRepository $operatorRepo)
{
    $this->operatorRepo=$operatorRepo;

}
    public function model() {
        return "App\Models\Number";
    }

    public function addNumber($phoneNumber, $initialBalance, $distributor_id,$operator_label)
    {

        $operator = $this->operatorRepo->findName($operator_label);
        
        $number = new Number();
        $number->phoneNumber = $phoneNumber;
        $number->balance = $initialBalance;
        $number->initialBalance = $initialBalance;
        $number->distributor_id = $distributor_id;
        $number->operator_id= $operator->id;
        $number->save();
        return $number->refresh();
    }

    public function updateNumber(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "oldPhoneNumber"=> "required|string|unique:App\Models\Number,phoneNumber",
            "phoneNumber" => "sometimes|string|unique:App\Models\Number,phoneNumber",
            "balance" => "sometimes|string",
            "initialBalance" => "sometimes|string",
            "distributor_id" => "sometimes|integer",
        ]);
        if($validator->fails()){
            return $this->validationErrorResponse($validator->errors()->all(), 402);
        }

        $existingNumber = $this->findName($request['oldPhoneNumber']);
        $number= [];
        if($request->phoneNumber != null)
        {
            $number["phoneNumber"] = $request->phoneNumber;
        }
        if($request->balance != null)
        {
            $number["balance"] = $request->balance;
        }
        if($request->initialBalance != null)
        {
            $number["initialBalance"] = $request->initialBalance;
        }
        if($request->distributor_id != null)
        {
            $number["distributor_id"] = $request->distributor_id;
        }
        //dd($commissiongrid);
        $this->update($number, $existingNumber['id']);
        return $this->successResponse($number, 'Number modifiée avec succès', 201);
    }

    public function deleteNumber(Request $request){
        $validator = Validator::make($request->all(), [
            "phoneNumber" => "required|string|unique:App\Models\Number,phoneNumber",
        ]);
        if($validator->fails()){
            return $this->validationErrorResponse($validator->errors()->all(), 402);
        }

        $number = $this->findName($request['phoneNumber']);
        $this->delete($number['id']);
        return $this->successResponse(null, 'Number supprimée avec succès', 201);
    }

    public function findName($label)
    {
        $record = $this->getModel()->where('label',$label)->first();
        if (!$record) {
            throw new ModelNotFoundException( app($this->model())->nameModel() .' not found');
        }
        return $record;
    }
}
