<?php

namespace App\Repositories\Implementation;

use App\Models\Operator;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Repositories\Generic\GenericImplementation\GenericRepository;

class OperatorRepository extends GenericRepository{
    use ApiResponser;

    public function model()
    {
        return 'App\Models\Operator';
    }

    public function addOperator(Request $request, CountryRepository $countryRepo)
    {
        $validator = Validator::make($request->all(), [
            'label' => 'required|string',
            'serviceCode' => 'required|string|unique:App\Models\Operator,serviceCode',
            'serviceLabel' => 'required|string',
           // 'logo' => 'required|string',
            'country' => 'required|string',
        ]);

        if($validator->fails()){
            return $this->validationErrorResponse($validator->errors()->all(), 402);
        }

        $country = $countryRepo->findName($request["country"]);
        $formRequest = [
            'serviceCode' => $request["serviceCode"],
            'label' => $request["label"],
            'serviceLabel' => $request["serviceLabel"],
            //'logo' =>$request["logo"],
            //request('logo')->store('logos', 'public'),
            'country_id' => $country["id"],
        ];
        $operator = new Operator($formRequest);
        $operator->save();
//        if($request->logo != null){
//            $this->storeLogo($operator);
//        }

        return $this->successResponse(null, "Ajouter un opérateur effectuer avec success", 201);
    }

    public function updateOperator(Request $request, CountryRepository $countryRepo)
    {
        $validator = Validator::make($request->all(), [
            'label' => 'sometimes|string',
            'serviceCode' => 'sometimes|string|unique:App\Models\Operator,serviceCode',
            'serviceLabel' => 'sometimes|string',
            //'logo' => 'sometimes|image',
            'country' => 'sometimes|string',
            'ancienLabel' => 'required|string',
        ]);

        if($validator->fails()){
            return $this->validationErrorResponse($validator->errors()->all(), 402);
        }

        $oldOperator = $this->findName($request["ancienLabel"]);

        $formRequest = [];

        if($request->serviceCode != null) {
            $formRequest["serviceCode"] = $request["serviceCode"];
        }

        if($request->serviceLabel != null) {
            $formRequest["serviceLabel"] = $request["serviceLabel"];
        }

        /* if($request->logo != null) {
            $formRequest["logo"] = request('logo')->store('logos', 'public');
        } */

        if($request->label != null) {
            $formRequest["label"] = $request["label"];
        }

        if($request->country  != null) {
            $country = $countryRepo->findName($request["country"]);
            $formRequest['country_id'] = $country["id"];
        }

        $operator = $this->update($formRequest, $oldOperator["id"]);

        return $this->successResponse(null, "Mise a jour d'un opérateur effectuer avec success", 201);
    }

    public function allOperators(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'country' => 'required|string',
        ]);

        if($validator->fails()){
            return $this->validationErrorResponse($validator->errors()->all(), 402);
        }

        $country = $countryRepo->findName($request["country"]);
        $operators = Operator::where('country_id',$country->id)->get();

        return $this->successResponse($operators, "Liste des opérateurs", 201);
    }

    public function deleteOperator(Request $request)
    {
     //   dd($request->all());
        $validator = Validator::make($request->all(), [
            'serviceCode' => 'required|string',
        ]);

        if($validator->fails()){
            return $this->validationErrorResponse($validator->errors()->all(), 402);
        }
        $operator = $this->findNameServiceCode($request["serviceCode"]);
       // dd($operator);
        $this->delete($operator["id"]);
        return $this->successResponse(null, "Supprimer un opérateur effectuer avec success", 201);
    }


    public function assignTypeServiceToOperator(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "serviceCode" => "required|string",
            "type_service_id" => "required|array",
        ]);

        if($validator->fails()){
            return $this->validationErrorResponse($validator->errors()->all(), 402);
        }

        $operator = $this->findNameServiceCode($request->serviceCode);
        $operator->typeservices()->sync($request->type_service_id);
    }


    public function allOperatorsByCountry(Request $request, CountryRepository $countryRepo)
    {
        $validator = Validator::make($request->all(), [
            "country" => "required|string",
        ]);

        if($validator->fails()){
            return $this->validationErrorResponse($validator->errors()->all(), 402);
        }

        $country = $countryRepo->findName($request->country);
        $operators = $this->getModel()->where("country_id", $country->id)->get();
        $data = [];
        foreach($operators as $operator){
            $data[] = $operator;
        }

        return $this->successResponse($data);
    }

    public function allServicesByOperatorCountry(Request $request, CountryRepository $countryRepo)
    {
        $validator = Validator::make($request->all(), [
            "serviceCode" => "required|string",
            "label" => "required|string",
        ]);

        if($validator->fails()){
            return $this->validationErrorResponse($validator->errors()->all(), 402);
        }

        $operators = $this->getModel()->where("serviceCode", $request->serviceCode)->get();
        $data = [];
        foreach($operators as $operator){
            if($operator->serviceCode == $request->serviceCode){
                foreach($operator->typeservices as $typeservice){
                    if($typeservice->label == $request->label){
                        foreach($typeservice->services as $service){
                            $array = explode("_", $service->libelle);
                            //dd($array[0]);
                            if($service->status == 1 && $array[0] == $operator->label){
                                $service->libelle = $this->getLibelleAttribute($service->libelle);
                                $data[] = $service;
                            }
                        }
                    }
                }
            }
        }
        //$tmp = array_unique($data);
       return $this->successResponse($data);
    }


    private function getLibelleAttribute($value){
        $array = explode("_",$value);
        if(count($array) == 2){
            return $array[1];
        } else {
            $data = [];
            for($i=1; $i<count($array); $i++){
               $data[] = $array[$i];
            }
            return implode("_", $data);
        }
    }

    public function findName($label)
    {
        $record = $this->getModel()->where('label',$label)->first();
        if (!$record) {
            throw new ModelNotFoundException( app($this->model())->nameModel() .' not found');
        }
        return $record;
    }

    private function findNameServiceCode($label)
    {

         $record = $this->getModel()->where('serviceCode',$label)->first();
        if (!$record) {
            throw new ModelNotFoundException( app($this->model())->nameModel() .' not found');
        }
        return $record;
    }

//    private function storeLogo(Operator $operator){
//        if(request('logo')){
//            $operator->update([
//                'logo' => request('logo')->store('logos', 'public'),
//            ]);
//        }
//    }
}
