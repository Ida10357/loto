<?php

namespace App\Repositories\Implementation;

use App\Models\User;
use App\Models\People;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Entreprise;
use Illuminate\Support\Str;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use App\Models\EntrepriseClerk;
use App\Models\AccountEntreprise;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;
use App\Repositories\Implementation\RoleRepository;
use App\Repositories\Implementation\PeopleRepository;
use App\Repositories\Generic\GenericImplementation\GenericRepository;

class EntrepriseRepository extends GenericRepository
{
    use ApiResponser;
    private $peopleRepo;
    private $accountRepository;
    private $roleRepo;
    public function __construct(PeopleRepository $peopleRepository, AccountRepository $account, RoleRepository $roleRepository)
    {
        $this->peopleRepo = $peopleRepository;
        $this->accountRepository=$account;
        $this->roleRepo=$roleRepository;
    }

    public function model()
    {
        return "App\Models\Entreprise";
    }

    public function onboard(Request $request, int $countryId)
    {

        // Rechercher les information concernant l'Administrateur
        $admin = People::where("country_id",$countryId)->where('number', $request->numberAdmin)->first();

        $UserAdmin = User::where('id', $admin->user_id)->first();
        if ($UserAdmin == null) {
            return $this->errorResponse("user not found", 403);
        }


        $user = new User([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt(Str::random(60)),
            'activation_token' => Str::random(60),
            'pin' => bcrypt($request->pin),
        ]);

        //affecter le role enterprise
        $role = Role::find(6);
        $user->assignRole($role);
        $user->save();

        //creer une instance de l'entreprise

        $enterprise=new Entreprise([
            'name' => $request->name,
            'adress' => $request->adress,
            'phoneNumber' => $request->number
        ]);
        $enterprise->save();
        $people = new People();
        $people->country_id = $countryId;
        $people->user_id = $user->id;
        $people->fullname = $request->name;
        $people->number = $request->number;
        $people = $this->peopleRepo->addOne($people, $enterprise->fresh());
        //ajouter un customer
        $customer = new Customer();
        $customer->save();

        $roleAdmin = Role::find(7);
        $UserAdmin->assignRole($roleAdmin);
        $UserAdmin->save();
        // Création de l'Admin et Affectation à l'Entreprise
        $adminEntreprise = new EntrepriseClerk();
        $adminEntreprise->entreprise_id = $enterprise->id;
        $adminEntreprise->user_id = $admin->user_id;
        $adminEntreprise->save();

        $admin = $this->peopleRepo->addOne($admin, $adminEntreprise->fresh());

        //creer un compte entreprise
        $account = $this->accountRepository->createAcount($customer->id,"entreprise");
        $data["customer"] = collect($people)->except(["people",'id']);
        $data["account_info"] = collect($account)->except("customer_id", 'id');
        return $data;
    }



    public function addUserToAccount(Request $request){
        //retrouver le pays
        $countryResult = Country::where("nicename", $request->country)->first();

        if ($countryResult == null) {
            return $this->errorResponse("country not found", 403);
        }

        //retrouver l'utilisateur
        $user = People::where('number', $request->number)->where("country_id",$countryResult->id)->first();
        if ($user == null) {
            return $this->errorResponse("user not found", 403);
        }

        //retrouver l'entreprise
        $enterprise = People::where('number', $request->enterpriseNumber)
            ->where("country_id", $countryResult->id)
            ->where("people_type", "App\Models\Entreprise")
            ->first();

        if ($enterprise == null) {
            return $this->errorResponse("enterprise not found", 403);
        }
        //effectuer l'affectation

        $accountEntreprise=new AccountEntreprise();
        $accountEntreprise->people_id=$user->id;
        $accountEntreprise->entreprise_id=$enterprise->id;
        $accountEntreprise->save();

        return $this->successResponse(null, "user affected successfully to enterprise", 201);

    }



    public function loginEntreprise(Request $request)
    {
        if(Auth::attempt(['name' => request('name'), 'password' => request('password')])){
            $user = Auth::user();
            $data['role']=$this->roleRepo->getRoles($user);
            $data['token'] = $user->createToken('token')->accessToken;
            $data['infoUser']=People::where('user_id',$user->id)->first();
            $entreprise = EntrepriseClerk::where('user_id', $user->id)->first();
            $data['infoEntreprise']= Entreprise::where('id', $entreprise->entreprise_id)->first();
            if($user->hasRole(['AdministrateurEntreprise', 'AdministrateurPays', 'EntrepriseEmploye']))
            {
                if($user->people) {
                    $data["has_onborded"] = true;
                } else {
                    $data["has_onborded"] = false;
                }
                return $this->successResponse($data, 'user logged successfully', 201);
            }
            else{
             return $this->errorResponse('Authentification failled: email or password incorrect', 403);
         }


        }
        else{
            return $this->errorResponse('Authentification failled: email or password incorrect', 403);
        }
    }

    public function listEntreprise(){
        $entreprise = Entreprise::all();
        return $entreprise;
    }

    public function listEmployeEntreprise(Request $request){
        $entreprise = EntrepriseClerk::where('entreprise_id', $request->entreprise_id)->get();
        if($entreprise == null)
        {
            return $this->errorResponse("Entreprise Not Found !", 403);
        }
        for($i=0; $i<$entreprise->count(); $i++)
        {
            $salary =  EntrepriseClerk::where('user_id', $entreprise[$i]->user_id)->select("salary")->first();
            $people = People::where("people_type", "App\Models\EntrepriseClerk")->where('user_id', $entreprise[$i]->user_id)->first();
            $employe[$i] = [$salary, $people];
        }
        return $employe;
    }


    public function updateEmployeSalary(Request $request)
    {
        $employe = People::where('people_type', "App\Models\EntrepriseClerk")->where('number', $request->EmployeNumber)->first();
        if($employe == null)
        {
            return $this->errorResponse("Clerk Not Found !", 403);
        }
        $updateEmploye = EntrepriseClerk::where('user_id', $employe->user_id)->where('entreprise_id', $request->entreprise_id)->first();

        $updateEmploye->salary = $request->salary;
        $updateEmploye->save();

        return $this->successResponse($employe, 'Clerk updated successfully', 201);
    }

    public function addAdminEntreprise(Request $request)
    {
        // Rechercher les information concernant l'Administrateur
        $admin = People::where("country_id",$request->country_id)->where('number', $request->EmployeNumber)->first();

        $UserAdmin = User::where('id', $admin->user_id)->first();
        if ($UserAdmin == null) {
            return $this->errorResponse("user not found", 403);
        }

        if($this->ClerkExists($request->entreprise_id, $admin->user_id)){
            return $this->errorResponse("Clerk already exists in enterprise !", 403);
        }

        // Création de l'Admin et Affectation à l'Entreprise
        $adminEntreprise = new EntrepriseClerk();
        $adminEntreprise->entreprise_id = $request->entreprise_id;
        $adminEntreprise->user_id = $admin->user_id;
        $adminEntreprise->save();

        $admin = $this->peopleRepo->addOne($admin, $adminEntreprise->fresh());

        $roleAdmin = Role::find(7);
        $UserAdmin->assignRole($roleAdmin);
        $UserAdmin->save();

        return $this->successResponse($admin, 'Administrator affected to enterprise successfully', 201);
    }


    public function addEmployeEntreprise(Request $request)
    {
        $admin = People::where("user_id", Auth::user()->id)->first();
       $country = Country::where('name', $admin->country_id)->select('id')->first();
        // $adminEntreprise = EntrepriseClerk::where("user_id", $admin->user_id)->first();
        // $country = Country::where("nicename", $request->country)->first();

        // if ($country == null) {
        //     return $this->errorResponse("country not found", 403);
        // }

        // $enterprise = People::where('number', $request->enterpriseNumber)
        //     ->where("country_id", $country->id)
        //     ->where("people_type", "App\Models\Entreprise")
        //     ->first();

        //retrouver l'utilisateur
        $user = People::where('number', $request->number)->where("country_id",$country->id)->first();
        if ($user == null) {
            return $this->errorResponse("user not found", 403);
        }

        $employe = User::where('id', $user->user_id)->first();

        if($this->ClerkExists($request->entreprise_id, $employe->id)){
            return $this->errorResponse("Clerk already exists in enterprise !", 403);
        }

        $employeEntreprise = new EntrepriseClerk();
        $employeEntreprise->entreprise_id = $request->entreprise_id;
        $employeEntreprise->user_id = $employe->id;
        $employeEntreprise->salary = $request->salary;
        $employeEntreprise->save();

        $user = $this->peopleRepo->addOne($user, $employeEntreprise->fresh());

        $role = Role::find(8);
        $employe->assignRole($role);
        $employe->save();

        return $this->successResponse($employe, 'Clerk affected to enterprise successfully', 201);
    }

    private function ClerkExists(String $entrepriseId, String $userId)
    {
        return EntrepriseClerk::where('user_id', $userId)
            ->where("entreprise_id", $entrepriseId)
            ->exists();
    }

    public function deleteEmployeEntreprise(Request $request)
    {
        $people = People::where("country_id", $request->country_id)->where("number", $request->numberEmploye)->first();
        if ($people == null) {
            return $this->errorResponse("Clerks not found in this Enterprise", 403);
        }
        $employe = EntrepriseClerk::where("user_id", $people->user_id)->where("entreprise_id", $request->entreprise_id)->get();
        if ($employe == null) {
            return $this->errorResponse("Clerk not found in this Enterprise", 403);
        }

        $total = EntrepriseClerk::where("user_id", $people->user_id)->get();
        if(count($total)== 1)
        {
            $user = User::where('id', $people->user_id)->first();
            $role = Role::find(8);
            $user->removeRole($role);
        }


        $employe= EntrepriseClerk::where("user_id", $people->user_id)->where("entreprise_id", $request->entreprise_id)->delete();

        return $this->successResponse($people, 'Clerk deleted to enterprise successfully', 201);
    }



}




?>
