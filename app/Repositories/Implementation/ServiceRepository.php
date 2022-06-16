<?php

namespace App\Repositories\Implementation;

use App\Models\Country;
use App\Models\Service;
use App\Models\Operator;
use App\Models\TypeService;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\OperatorTypeService;
use Illuminate\Support\Facades\Validator;
use App\Repositories\Implementation\CountryRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Repositories\Generic\GenericImplementation\GenericRepository;

class ServiceRepository extends GenericRepository{

    use ApiResponser;
    protected $paysRepo;
    protected $operatorRepo;
    protected $commissionGridRepo;
    protected $countryRepo;

    public function model()
    {
        return 'App\Models\Service';
    }

public function __construct( CommissionGridRepository $commissionGridRepo, OperatorRepository $operatorRepo,CountryRepository $countryRepo)
{
    $this->operatorRepo=$operatorRepo;
    $this->commissionGridRepo=$commissionGridRepo;
    $this->countryRepo=$countryRepo;
}
    public function add(Request $request){

        $validator = Validator::make($request->all(), [
            "libelle" => "required",
            "ussdCodurse" => "required|unique:App\Models\Service,ussdCodurse",
            "commission_grid_label" => "sometimes|string",
            "ref" => "required|string",
            "forSale" => "required|boolean",
            "operator_label" => "required|string",
            'country' => 'required|string',
        ]);

        if($validator->fails()){
            return $this->validationErrorResponse($validator->errors()->all(), 402);
        }
       // dd($request->commission_grid_label);
        if($request->commission_grid_label!="null")
        {
            $commission_grid = $this->commissionGridRepo->findName($request->commission_grid_label);
        }

        $operator = $this->operatorRepo->findName($request->operator_label);
        $country = $this->countryRepo->findName($request["country"]);

       $service=new Service();
       $service->libelle=$request->libelle;
       $service->ussdCodurse=$request->ussdCodurse;

      if($request->forSale==true){//quand c'est un service payant on lui affecte une commission
        $service->commission_grid_id=$commission_grid["id"];
       }

       $service->ref=$request->ref;
       $service->forSale=$request->forSale;

      // if($request->forSale==false){//quand c'est un service non-payant on lui affecte un operateur
        $service->operator_id=$operator->id;
      // }
        $service->country_id=$country->id;

        $service->save();

        return $this->successResponse(null, "Le service a été enregistré avec succès", 201);
    }

    public function countryServices(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'country' => 'required|string',
        ]);


        if($validator->fails()){
            return $this->validationErrorResponse($validator->errors()->all(), 402);
        }
        $country = $this->countryRepo->findName($request->country);

        return $this->successResponse($operatorServices, "Liste des services de $request->operator", 200);
    }

    public function deleteService(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "libelle" => "required|string",
        ]);

        if($validator->fails()){
            return $this->validationErrorResponse($validator->errors()->all(), 402);
        }

        $service = $this->findName($request->libelle);
        $this->delete($service['id']);

        return $this->successResponse(null, "Supprimer un service effectuer avec succès", 201);
    }

    public function getRechargeCode(Request $request){
        $validator = Validator::make($request->all(), [
            'country' => 'required|string',
            'operator_label' => 'required|string',
        ]);

        if($validator->fails()){
            return $this->validationErrorResponse($validator->errors()->all(), 402);
        }

        //retrouver l'id du pays
        $country = $this->countryRepo->findName($request->country);
        //retrouver l'id de l'operateur
        $operator = $this->operatorRepo->findName($request->operator_label);

        //retrouver le service
            $recharge=Service::where('libelle','recharge')
            ->where('operator_id',$operator->id)
            ->where('country_id',$country->id)
            ->first();


            //dd($recharge);
            if($recharge==null){
                return $this->successResponse(null, "Aucun service disponible", 200);
            }


            $distributor=  DB::table('distributors')
            ->join('numbers','distributors.id','=','numbers.distributor_id')
            ->where('numbers.operator_id',$operator->id)
            ->where('distributors.country_id',$country->id)
            ->where('distributors.actif',true)
            ->select('numbers.phoneNumber')
            ->get()
            ->toArray();
            $x = array_rand($distributor,1);

            $code=[
                'code'=>$recharge->ussdCodurse,
                'cle'=>$recharge->ref,
                'distNum'=>$distributor[$x]->phoneNumber
            ];

        return $this->successResponse($code,"code de recharge", 200);
    }
    public function forSale(Request $request){
        $validator = Validator::make($request->all(), [
            'country' => 'required|string',
        ]);
        if($validator->fails()){
            return $this->validationErrorResponse($validator->errors()->all(), 402);
        }
        $country = $this->countryRepo->findName($request["country"]);

        $saleServices=$this->findSaleServices($country->id);
        //dd($saleServices);
        return $this->successResponse($saleServices, "Liste des services de $request->country", 200);
    }

    public function operatorServices(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'country' => 'required|string',
            'operator'=>'required|string'
        ]);


        if($validator->fails()){
            return $this->validationErrorResponse($validator->errors()->all(), 402);
        }
        $country = $this->countryRepo->findName($request->country);
        $operator = $this->operatorRepo->findName($request->operator);

        $operatorServices=$this->findOperatorServices($country->id,$operator->id);
        return $this->successResponse($operatorServices, "Liste des services de $request->operator", 200);
    }

   /*  public function allServiceActif()
    {
        $validator = Validator::make($request->all(), [
            'country' => 'required|string',
        ]);


        if($validator->fails()){
            return $this->validationErrorResponse($validator->errors()->all(), 402);
        }
        $actifsServices=Service::where('country_id',$country->id)
                                ->where('status',0)//service actif
                                ->where('operator_id',$operator->id)
                                ->get();
        return $this->successResponse($actifsServices, "Liste de tous les services actifs", 201);
    } */

    public function updateService(Request $request, CommissionGridRepository $commissionGridRepo, TypeServiceRepository $typeServiceRepo)
    {
        $validator = Validator::make($request->all(), [
            "libelle" => "required|unique:App\Models\Service,libelle",
            "ussdCodurse" => "sometimes|unique:App\Models\Service,ussdCodurse",
            "commission_grid_label" => "required|string",
            "type_service_label" => "required|string",
            "ancienLibelle" => "required",
        ]);

        if($validator->fails()){
            return $this->validationErrorResponse($validator->errors()->all(), 402);
        }

        $commission_grid = $commissionGridRepo->findName($request->commission_grid_label);
        $type_service = $typeServiceRepo->findName($request->type_service_label);
        $serviceOld = $this->findName($request->ancienLibelle);

        if($request->ussdCodurse == null){
            $service = [
                "libelle" => $request->libelle,
                "commission_grid_id" => $commission_grid["id"],
                "type_service_id" => $type_service["id"],
            ];
        } else {
            $service = [
                "libelle" => $request->libelle,
                "ussdCodurse" => $request->ussdCodurse,
                "commission_grid_id" => $commission_grid["id"],
                "type_service_id" => $type_service["id"],
            ];
        }

        $this->update($service, $serviceOld['id']);
        return $this->successResponse($service, 'Service modifiée avec succès', 201);
    }

    public function changeState(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "libelle" => "required",
        ]);

        if($validator->fails()){
            return $this->validationErrorResponse($validator->errors()->all(), 402);
        }
        $serviceOld = Service::where('libelle', $request->libelle)->first();
       // $serviceOld = $this->findName($request->libelle);
        dd($serviceOld);
        $serviceOld->status=!$serviceOld->status;
        $service = $this->update($serviceOld, $serviceOld->id);
        return $this->successResponse($service, "statut du service change", 201);
    }

    public function getCountryRepo()
    {
            return $this->countryRepo;
    }


    public function setCountryRepo($countryRepo)
    {
            $this->countryRepo = $countryRepo;

            return $this;
    }
    private function findName($libelle)
{
    $record = $this->getModel()->where('libelle',$libelle)->first();
    if (!$record) {
        throw new ModelNotFoundException( app($this->model())->nameModel() .' not found');
    }
    return $record;
}

private function findOperatorServices($countryId,$operatorId){
 $opServices=Service::where('country_id',$countryId)
                               // ->where('forSale',0)//1 signifie que c'est  payant
                                ->where('status',1)
                                ->where('operator_id',$operatorId)
                                ->get();
            return $opServices;
}
private function findSaleServices($id)
{
   $saleServices= Service::where('country_id',$id)
    ->where('forSale',1)
    ->where('status',1)
    ->get();
    return $saleServices;
}

public function allServices()
{
    $allservice=Service::all();

    return $this->successResponse($allservice, "Liste de tous les services", 201);
}
    public function allServiceByCountry(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "country" => "required|string",
        ]);

        if($validator->fails()){
            return $this->validationErrorResponse($validator->errors()->all(), 402);
        }

        $pays = $this->countryRepo->findName($request->country);
     //   $operator = Operator::where('country_id', $paysRepo['id'])->first();
        //$operatorTypeService = OperatorTypeService::where('operator_id', $operator['id'])->first();
        //$typeService = TypeService::where('id', $operatorTypeService['type_service_id'])->first();
        $serviceCountry = Service::where('services.country_id',$pays->id)
                                    ->join('operators','operators.id','=','services.operator_id')
                                    ->get();


        return $this->successResponse($serviceCountry, 'Tous les services de ce pays', 201);
    }
   /*  public function allServiceByOperator(Request $request, OperatorRepository $operatorRepo)
    {
        $validator = Validator::make($request->all(), [
            "operateur" => "required|string",
        ]);

        if($validator->fails()){
            return $this->validationErrorResponse($validator->errors()->all(), 402);
        }

        $operatorRepo = $operatorRepo->findName($request->operateur);
        //dd($operatorRepo);
        //$operatorTypeService = OperatorTypeService::where('operator_id', $operatorRepo['id'])->first();
        //$typeService = TypeService::where('id', $operatorTypeService['type_service_id'])->first();
        //$serviceCountry = Service::where('type_service_id', $typeService['id'])->get();

        //return $this->successResponse($serviceCountry, 'Tous les services de ce intitution', 201);
    } */

        /**
         * Get the value of countryRepo
         */

}
