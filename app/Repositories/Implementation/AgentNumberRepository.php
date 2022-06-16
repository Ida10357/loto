<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Repositories\Implementation;

use App\Models\AgentNumber;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Repositories\Generic\GenericImplementation\GenericRepository;

/**
 * Description of AgentNumberRepository
 *
 * @author Sariel
 */
class AgentNumberRepository extends GenericRepository{
    use ApiResponser;
    private $operatorRepo;

    public function __construct( OperatorRepository $operatorRepo)
    {
            $this->operatorRepo=$operatorRepo;
    }

    public function model() {
        return "App\Models\AgentNumber";
    }

    public function addNumber($phoneNumber, $initialBalance, $agent_id)
    {
        $number = new AgentNumber();
        $number->phoneNumber = $phoneNumber;
        $number->balance = $initialBalance;
        $number->initialBalance = $initialBalance;
        $number->agent_id = $agent_id;
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
            "agent_id" => "sometimes|integer",
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
        if($request->agent_id != null)
        {
            $number["agent_id"] = $request->agent_id;
        }
        //dd($commissiongrid);
        $this->update($number, $existingNumber['id']);
        return $this->successResponse($number, 'Agent Number modifiée avec succès', 201);
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
        return $this->successResponse(null, 'Agent Number supprimée avec succès', 201);
    }

    public function findName($label)
    {
        $record = $this->getModel()->where('label',$label)->first();
        if (!$record) {
            throw new ModelNotFoundException( app($this->model())->nameModel() .' not found');
        }
        return $record;
    }

    public function getAgent(int $agent_id)
    {
        return AgentNumber::where('agent_id', $agent_id)->first();
    }
}
