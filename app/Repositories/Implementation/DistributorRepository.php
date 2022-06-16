<?php

namespace App\Repositories\Implementation;

use App\Models\User;
use App\Models\People;
use App\Models\Country;
use App\Models\Distributor;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Repositories\Generic\GenericImplementation\GenericRepository;

class DistributorRepository extends GenericRepository{
    protected $peopleRepo;
    protected $numberRepo;
    protected $countryRepo;
    public function __construct(PeopleRepository $peopleRepo, NumberRepository $numberRepo,CountryRepository $countryRepo)
    {
        $this->peopleRepo = $peopleRepo;
        $this->numberRepo = $numberRepo;
        $this->countryRepo=$countryRepo;
    }

    public function onboard(Request $request)
    {
        $user = new User([
            'name' => $request->country_phone_code.$request->number,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'activation_token' => Str::random(60),
            'pin' => bcrypt($request->pin),
        ]);
        $role = Role::find(4);
        $user->assignRole($role);
        $user->save();

        $people = new People();
        $people->fullname = $request->fullname;
        $people->number = $request->number;
        $country = Country::where("nicename", $request->country)->first();

        $distributor = new Distributor();
        $distributor->country_id=$country->id;
        $distributor->save();

        $people->country_id = $country->id;
        $people->user_id = $user->id;
        $people = $this->peopleRepo->addOne($people, $distributor->fresh());

        $number = $this->numberRepo->addNumber($request->number,$request->initialBalance, $distributor->id,$request->operator_label);
        $data["distributor"] = $people;
        $data["number_info"] = $number;

        return $data;
    }

    public function getDistributorList($country){
      $distributor=  DB::table('distributors')
                ->join('people','people.people_id','=','distributors.id')
                ->join('numbers','distributors.id','=','numbers.distributor_id')
                ->join('operators','operators.id','=','numbers.operator_id')
                ->where('people.people_type', 'App\Models\Distributor')
                ->where('people.country_id',$country)
                ->select('distributors.id','people.fullname','people.number','numbers.operator_id','operators.label','actif')
                ->get();

    return $distributor;
    }

    Public function desactivateDist(Request $request)
    {

    }
    public function getDistActif(Request $request)
    {
        $country=$this->countryRepo->findName($request->country);
       $disActif= Distributor::where('country_id',$country->id)
       ->where('actif',true)
       ->where('')
       ->get();
    }

    public function model()
    {
        return "App\Models\Distributor";
    }
}
