<?php

namespace App\Repositories\Implementation;

use App\Models\User;
use App\Models\Card;
use App\Models\Clerk;
use App\Models\Number;
use App\Models\Agent;
use App\Models\AgentNumber;
use App\Models\People;
use App\Models\Country;
use App\Models\Operator;
use App\Models\Distributor;
use Illuminate\Support\Str;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Repositories\Implementation\RoleRepository;
use App\Repositories\Implementation\CustomerRepository;
use App\Repositories\Implementation\CardRepository;
use App\Repositories\Generic\GenericImplementation\GenericRepository;

class UserRepository extends GenericRepository
{
    use ApiResponser;
    private $customerRepository;
    private $roleRepository;
    private $peopleRepository;
    private $countryRepo;
    private $cardRepository;

    public function __construct(CardRepository $cardRepository, CustomerRepository $customerRepository,RoleRepository $roleRepository,PeopleRepository $peopleRepository,CountryRepository $countryRepo)
    {
        $this->customerRepository = $customerRepository;
        $this->roleRepository=$roleRepository;
        $this->peopleRepository=$peopleRepository;
        $this->countryRepo=$countryRepo;
        $this->cardRepository = $cardRepository;
    }
    public function findUser(Request $request)
    {
        $rules = [
            'country' => 'required|string',
            'number' => 'required|integer',

        ];
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->all(), 402);
        }
        //retrouver le pays
        $countryResult = Country::where("nicename", $request->country)->first();

        if ($countryResult == null) {
            return $this->errorResponse("country not found", 403);
        }
        //retrouver l'utilisateur
        $user = People::where('number', $request->number)->where("country_id", $countryResult->id)->first();
        if ($user == null) {
            return $this->errorResponse("user not found", 403);
        }else{
            return $this->successResponse($user, 'user found successfully', 201);
        }
    }

    public function sendOtpCodeViaMail(Request $request) {

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|string',
        ]);

        if($validator->fails()) {
            return $this->errorExceptionResponse($validator->errors()->all(), 'VALIDATION_ERROR', 402);
        }

        $user = User::where('email', $request->get('email'))->first();
        if($user) {
            $number = mt_rand(1000, 9999);
            $data['code_otp'] = $number;

            return $this->successResponse($data,'', 201);
        }
        return $this->errorExceptionResponse('email not exist', 'EMAIL_NOT_EXISTS_EXCEPTION', 403);
    }

    public function resetPassword(Request $request) {

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:App\Models\User,name',
            'password' => 'required|',
            'c_password' => 'required|same:password',
        ]);

        if($validator->fails()) {
            return $this->errorExceptionResponse($validator->errors()->all(), 'VALIDATION_ERROR', 402);
        }

        $user = User::where('name', $request->get('name'))->first();

        if ($user) {
            $user->password = bcrypt($request->get('password'));
            $user->save();

            $data['token'] =  $user->createToken('token')->accessToken;
            $data['user'] =  $user;
            return $this->successResponse($data, 'Successfully updated password', 201);
        } else {

            return $this->errorExceptionResponse('Authentification failled: wrong password incorrect', 'WRONG_PASSWORD_EXCEPTION', 403);
        }
    }

    public function updatePassword(Request $request) {

        $validator = Validator::make($request->all(), [
            'old_password' => 'required|',
            'new_password' => 'required|',
            'c_new_password' => 'required|same:new_password',
        ]);

        if($validator->fails()) {
            return $this->errorExceptionResponse($validator->errors()->all(), 'VALIDATION_ERROR', 402);
        }

        $user = Auth::user();

        if (Auth::guard('web')->attempt(['name' => $user->email, 'password' => request('old_password')])) {
            $user->password = bcrypt($request->new_password);
            $user->save();

            $data['token'] =  $user->createToken('token')->accessToken;
            $data['user'] =  $user;
            return $this->successResponse($data, 'Successfully updated password', 201);
        } else {

            return $this->errorExceptionResponse('Authentification failled: wrong password incorrect', 'WRONG_PASSWORD_EXCEPTION', 403);
        }
    }

    public function addAdministrator(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:App\Models\User,name',
            'email' => 'sometimes|email|string|unique:App\Models\User,email',
            'password' => 'required|',
            'c_password' => 'required|same:password',
            'fullname' => 'required|string',
            'number' => 'required|string',
            //'country_phone_code' => 'required|string',
           'country'=>'required|string'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->all(), 401);
        }
        $user = new User([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'activation_token' => Str::random(60),
            'pin' => bcrypt("00000"),
        ]);
        $role = Role::find(2);
        $user->assignRole($role);

        $user->save();
        $clerk = new Clerk();
        $clerk->registerNumber="12";
        $clerk->save();
        $people = new People();
        $people->fullname = $request->fullname;
        $people->number = $request->number;
        $country = $this->countryRepo->findName($request->country);
        //$country = Country::where("phoneCode", $request->country_phone_code)->first();
        $people->country_id = $country->id;
        $people->user_id = $user->id;
        $people = $this->peopleRepository->addOne($people, $clerk->fresh());
        return $people;
    }

    public function administratorList()
    {
        $adminList=People::where('people_type','App\Models\Clerk')->get();
        return $this->successResponse($adminList, 'Listes des administrateurs', 201);
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:App\Models\User,name',
            'email' => 'sometimes|email|string|unique:App\Models\User,email',
            'password' => 'required|',
            'c_password' => 'required|same:password',
            'pin' => 'required|digits:4',
            'c_pin' => 'required|same:pin',
            'fullname' => 'required|string',
            'number' => 'required|string',
            'country_phone_code' => 'required|string',
          //'role'=>'sometimes|numeric'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->all(), 401);
        }

        $user = new User([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'activation_token' => Str::random(60),
            'pin' => bcrypt($request->pin),
        ]);

        $role = Role::find(5);
        $user->assignRole($role);

        $user->save();
        $data = $this->customerRepository->onboard($request);

        $dat=$this->loginInfo($request);
        return $this->successResponse($dat, 'user logged successfully', 200);
/*
        $distributor = Distributor::where('actif',true)->first();
        $data['distributor']=$this->getDistributor($user);
        $data = $this->customerRepository->onbord($request);
        $data['token'] =  $user->createToken('token')->accessToken;
        $data['email'] =  $user->email;
        return $this->successResponse($data, 'Successfully created user', 201); */
    }

    public function registerByAgent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:App\Models\User,name',
            'email' => 'sometimes|email|string|unique:App\Models\User,email',
            'password' => 'required|',
            'c_password' => 'required|same:password',
            'pin' => 'required|digits:4',
            'c_pin' => 'required|same:pin',
            'fullname' => 'required|string',
            'number' => 'required|string',
            'country_phone_code' => 'required|string',
          //'role'=>'sometimes|numeric'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->all(), 401);
        }

        $user = new User([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'activation_token' => Str::random(60),
            'pin' => bcrypt($request->pin),
        ]);

        $role = Role::find(5);
        $user->assignRole($role);

        $user->save();
        $data = $this->customerRepository->onboard($request);
        return $this->successResponse($data, 'Client créé avec succès', 200);
/*
        $distributor = Distributor::where('actif',true)->first();
        $data['distributor']=$this->getDistributor($user);
        $data = $this->customerRepository->onbord($request);
        $data['token'] =  $user->createToken('token')->accessToken;
        $data['email'] =  $user->email;
        return $this->successResponse($data, 'Successfully created user', 201); */
    }

    private function loginInfo()
    {
        if(Auth::attempt(['name' => request('name'), 'password' => request('password')]))
        {
            $user = Auth::user();

           // $data['distributor']=$this->getDistributor($user);
            $data['token'] =  $user->createToken('token')->accessToken;
           $data['role']=$this->roleRepository->getRoles($user);
            $people=People::where('user_id',$user->id)->first();

            $country = $this->countryRepo->findName($people->country_id);
            $data['codePays']="$country->phoneCode";
         //   $data['operators']= Operator::where('country_id',$country->id)->get();

            $data['user'] = $user->loadMissing('person.people.account');
           /*  if($user->people) {
                $data["has_onborded"] = true;
            } else {
                $data["has_onborded"] = false;
            } */
            return $data;
            //return $this->successResponse($data, 'user logged successfully', 201);
        }
        else{
            return null;
        }
    }

    public function login(Request $request)
    {
        $data=$this->loginInfo($request);

        if($data!=null)
        {
            return $this->successResponse($data, 'user logged successfully', 201);
        }

        else{
        return $this->errorResponse('Authentification failled: email or password incorrect', 403);
        }
    }

    public function loginAdmin(Request $request)
    {
        if(Auth::attempt(['name' => request('name'), 'password' => request('password')])){
            $user = Auth::user();
            $data['role']=$this->roleRepository->getRoles($user);
            $data['token'] =  $user->createToken('token')->accessToken;
            $data['infoUser']=People::where('user_id',$user->id)->first();
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

    //login pour distributeur

    public function loginDistributeur(Request $request)
    {
        if(Auth::attempt(['name' => request('name'), 'password' => request('password')])){
            $user = Auth::user();
            $data['role']=$this->roleRepository->getRoles($user);
            $data['token'] =  $user->createToken('token')->accessToken;
            $data['infoUser']=People::where('user_id',$user->id)->first();
           $distr= $this->getDistributor($user);
         //  dd($distr);

          // if($distr['phoneNumber'])

            return $this->successResponse($data, 'user logged successfully', 201);
        }
    }

    public function loginAgent(Request $request)
    {
        if(Auth::attempt(['name' => request('name'), 'password' => request('password')])){
            $user = Auth::user();
            $people = People::where('user_id',$user->id)->where('people_type', "App\Models\Agent")->first();
            $agent = Agent::where('id', $people->people_id)->first();
            $agentNumber = AgentNumber::where("agent_id", $agent->id)->first();
            $country = $this->countryRepo->findName($people->country_id);
            $data['codePays']="$country->phoneCode";
            $data['operators']= Operator::where('country_id',$country->id)->get();
            $data['token'] =  $user->createToken('token')->accessToken;
            $data['agent'] = $agent;
            $data['agentNumber'] = $agentNumber;
            $data['people'] = $people;
          // if($distr['phoneNumber'])

            return $this->successResponse($data, 'user logged successfully', 201);
        }
    }

    public function logout(Request $request)
    {
        $token = Auth::guard('api')->user()->token();
        $token->revoke();
        return $this->successResponse(null, 'You have been successfully logged out!', 201);
    }

    public function model()
    {
        return 'App\Models\User';
    }

    private function getDistributor($user){

        $people=People::where('user_id',$user->id)->first();
        $country = $this->countryRepo->findName($people->country_id);
        $distributor = Distributor::where('actif',true)
        ->where('country_id',$country->id)->first();

        $distributorinfo =Number::where('distributor_id',$distributor->id)->first();

        return $distributorinfo;

    }

    public function validateAccount(Request $request)
    {
        $people = People::where("number", $request->number)->first();// $this->peopleRepository->findByAttribute("number", $request->number);
        if($people) {
            if(!$this->checkIfPeopleValidateAccount($people)) {
                $card = Card::where("libelle", $request->cardType)->first(); // $this->cardRepository->findByAttribute("libelle", $request->cardType);
                if($card) {
                    $people->cardNumber = $request->cardNumber;
                    $people->card_id = $card->id;
                    $people->save();
                    return $this->successResponse($people, "Compte validé avec succès", 200);
                }
            } else {
                return $this->errorResponse("Compte de cet utilisateur déja validé", 500);
            }

        } else {
            return $this->errorResponse("Aucun utilisateur trouvé pour ce numéro", 404);
        }
    }

    public function updateValidatedAccount(Request $request)
    {
        $people = People::where("number", $request->number)->first();// $this->peopleRepository->findByAttribute("number", $request->number);
        if($people) {
            if($this->checkIfPeopleValidateAccount($people)) {
                $card = Card::where("libelle", $request->cardType)->first(); // $this->cardRepository->findByAttribute("libelle", $request->cardType);
                if($card) {
                    $people->cardNumber = $request->cardNumber;
                    $people->card_id = $card->id;
                    $people->save();
                    return $this->successResponse($people, "Compte modifié avec succès", 200);
                }
            } else {
                return $this->errorResponse("Compte de cet utilisateur non validé", 500);
            }

        } else {
            return $this->errorResponse("Aucun utilisateur trouvé pour ce numéro", 404);
        }
    }

    public function getUser(int $id)
    {
        return User::all()->where('id',$id)->first();
    }
}
