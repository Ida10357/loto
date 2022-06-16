<?php

namespace App\Repositories\Implementation;

use App\Models\User;
use App\Models\People;
use App\Models\CountrySetting;
use App\Models\Country;
use App\Models\Agent;
use App\Models\Transaction;
use App\Models\AgentNumber;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Traits\ApiResponser;
use App\Repositories\Generic\GenericImplementation\GenericRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AgentRepository extends GenericRepository{
    use ApiResponser;
    protected $peopleRepo;
    protected $countryRepo;
    protected $transactionRepo;
    protected $accountRepo;
    protected $numberRepo;
    protected $roleRepo;
    protected $userRepo;

    public function __construct(RoleRepository $roleRepo, PeopleRepository $peopleRepo, CountryRepository $countryRepo, TransactionRepository $transactionRepo, AccountRepository $accountRepo, AgentNumberRepository $numberRepo, UserRepository $userRepo)
    {
        $this->roleRepo = $roleRepo;
        $this->peopleRepo = $peopleRepo;
        $this->countryRepo=$countryRepo;
        $this->transactionRepo = $transactionRepo;
        $this->accountRepo = $accountRepo;
        $this->numberRepo = $numberRepo;
        $this->userRepo = $userRepo;
    }

    public function model()
    {
        return "App\Models\Agent";
    }

    public function onboard(Request $request)
    {
        $role = Role::where("name", "Agent")->get();// $this->roleRepo->findByAttribute("name", "Agent");
        $country = Country::where("phoneCode", $request->country_phone_code)->first();
        $user = new User([
            'name' => $request->country_phone_code.$request->number,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'activation_token' => Str::random(60),
            'pin' => bcrypt($request->pin),
        ]);
        $user->assignRole($role);
        $user->save();

        $people = new People();
        $people->fullname = $request->fullname;
        $people->number = $request->number;

        $agent = new Agent();
        $agent->agentNumber=$request->agentNumber;
        $agent->save();

        $people->country_id = $country->id;
        $people->user_id = $user->id;
        $people = $this->peopleRepo->addOne($people, $agent->fresh());
        $number = $this->numberRepo->addNumber($request->country_phone_code.$request->number,$request->initialBalance, $agent->id,$request->operator_label);
        $data["people"] = $people;
        $data["Number"] = $number;
        $name = $request->country_phone_code.$request->number;
        if(Auth::attempt(['name' => $name, 'password' => request('password')])){
            $user = Auth::user();
            $data['token'] =  $user->createToken('token')->accessToken;
        }
        return $this->successResponse($data, "Agent ajouté avec succès", 200);
    }



    public function getAgentList(int $country){
        //$agent= null;
        if ($country != 0) {

            $agent=  DB::table('agents')
                  ->join('people','people.people_id','=','agents.id')
                  ->join('countries','countries.id','=','people.country_id')
                  ->where('people.people_type', 'App\Models\Agent')
                  ->where('people.country_id',$country)
                  ->select('agents.id','people.fullname','people.number','agentNumber', 'countries.name as country')
                  ->get();
        } else {
            $agent=  DB::table('agents')
            ->join('people','people.people_id','=','agents.id')
            ->join('countries','countries.id','=','people.country_id')
            ->where('people.people_type', 'App\Models\Agent')
            ->select('agents.id','people.fullname','people.number','agentNumber', 'countries.name as country')
            ->get();
        }

      return $agent;
    }

    public function deposit(Request $request)
    {
        $agent = $this->findByNumber($request->agentNumber);
        if($agent==null) {
            return $this->errorResponse('Aucun agent trouvé avec ce numéro', 404);
        }
        $people = $this->peopleRepo->getPeople("App\Models\Agent", $agent->id);
        $user = User::where("id", $people->user_id)->first();
        if(!Hash::check($request->pin, $user->pin)) {
            return $this->errorResponse("Code pin incorrect", 500);
        }
        $sender = $this->numberRepo->getAgent($agent->id);
        if($sender->balance<$request->amount) {
            return $this->errorResponse('Solde Insuffisante', 505);
        }
        /*
            $agent_country = $this->peopleRepo->getPeople("App\Models\Agent", $agent->id);
            $agent_country_id = Country::where('name', $agent_country->country_id)->get("id");
            $country_conversion = DB::select('select deviseRate from country_settings where country_id  = ? and setting_type = ?', [$agent_country_id[0]->id, "App\Models\UserCountrySetting"]);
            $amount_in_ik = $request->amount - ($request->amount * $country_conversion[0]->deviseRate);
        */
        $receiver = $this->peopleRepo->findByAttribute("number", $request->phoneNumber);
        if($receiver==null) {
            return $this->errorResponse('Aucun utilisateur trouvé avec ce numéro', 401);
        }
        $transaction = $this->transactionRepo->tranfer($sender->phoneNumber, $receiver->number, $request->amount, true);

        return $this->successResponse($transaction, 'Dépot effectuée avec succès', 200);

    }

    public function retrial(Request $request)
    {
        /*
            faire des vérifications
                - le numéro de téléphone,
                - la somme à retiter
                - Nom
                - Prénom
                - Contact
                - Le code de l'agent
            si CodeTransaction est définie ça veut dire que la personne n'a pas de compte
                vérifier si il y a dans la table unsaved_users un user ayant les informations
                si oui
                    vérifier dans la table transaction il y a une transaction avec CodeTransaction et unsavedUser_id correspondate et dont le status est pending
                    si oui
                        mettre la transaction à "effectuer"
                        décompter la somme de la bourse de l'agent
                    si non
                        envoyer un message d'erreur
            récupérer le people avec le numéro passé en paramètre
            vérifier si il existe
            si oui

            si non
                vérifier qu'il y a un ordre de transfert
        */
    }

    public function rapport(Request $request)
    {
        //$date = date("Y-m-d", strtotime($request->begin . ' -1 day'));

            // Récupérer le numéro de l'agent associté à son code
        $agent = $this->findByNumber($request->agentNumber);
        if($agent==null) {
            return $this->errorResponse('Aucun agent trouvé avec ce numéro', 404);
        }
            // Récupérer le people correspondant
        $account = People::where("people_id", $agent->id)->where("people_type", "App\Models\Agent")->first();
            // et le account
        $account = AgentNumber::where('agent_id', $agent->id)->first();

        $transactions = Transaction::where("agent_number_id", $account->id)->whereBetween("created_at", [$request->begin, $request->end])->with('recipient')->get();

        $liquidity = Transaction::where("agent_number_id", $account->id)->whereBetween("created_at", [$request->begin, $request->end])->where('owner_id', null)->sum('montant');
        $money_in_ik = Transaction::where("agent_number_id", $account->id)->whereBetween("created_at", [$request->begin, $request->end])->where('recipient_id', null)->sum('montant');
        $gain = $liquidity - $money_in_ik;
        $rapport = [
            "transactions" => $transactions,
            "liquidity" => $liquidity,
            "money_in_ik" => $money_in_ik,
            "gain" =>   $gain,
        ];
        return $this->successResponse($rapport, 'Votre rapport', 200);
    }

    public function updateInfos(Request $request)
    {

        $name = $request->country_phone_code.$request->number;

        $agent = $this->findByNumber($request->agentNumber);
        if($agent==null) {
            return $this->errorResponse('Aucun agent trouvé avec ce numéro', 404);
        }
        $agent_people = $this->peopleRepo->getPeople("App\Models\Agent", $agent->id);
        if($agent_people==null) {
            return $this->errorResponse('Aucun agent trouvé avec ce numéro', 404);
        }
        $agent_user = $this->userRepo->getUser($agent_people->user_id);
        if($agent_user==null) {
            return $this->errorResponse('Aucun agent trouvé avec ce numéro', 404);
        }

        $formDataUser = $this->createUpdateArray(["name" => $name, "email" => $request->email]);
        //dd($formDataUser);
        $formDataPeople = $this->createUpdateArray($request->only("fullname", "number", "country"));
        $agent_people->update($formDataPeople);
        $agent_user->update($formDataUser);
        return $this->successResponse($agent, 'Mise à jour effectuée avec succès', 200);
    }

    public function getSolde(String $agentNumber)
    {
        $agent = $this->findByNumber($agentNumber);
        if(!$agent) {
            return $this->errorResponse('Aucun agent trouvé avec ce numéro', 404);
        }
        $number = $this->numberRepo->getAgent($agent->id);
        return $this->successResponse($number->balance, 'Votre solde: ', 200);
    }

    public function retrialForUnsavedUsers(Request $request)
    {
        // on recherche la transaction ayant la CodeTransaction fournie par le client
        $transaction = Transaction::all()->where('CodeTransaction', $request->CodeTransaction)->where('status', 'pending')->first();
        if ($transaction) {
            $agent = $this->findByNumber($request->agentNumber);
            $people = $this->peopleRepo->getPeople("App\Models\Agent", $agent->id);
            $user = User::where("id", $people->user_id)->first();
            if(!Hash::check($request->pin, $user->pin)) {
                return $this->errorResponse("Code pin incorrect", 500);
            }
            // Récupérer le agentNumber
            $agentNumber = $this->numberRepo->getAgent($agent->id);
            $agentNumber->balance += $transaction->montant;
            $transaction->status = "done";
            $agentNumber->update();
            $transaction->update();
            return $this->successResponse($transaction, 'Opération de retrait effectuée avec succès', 200);
        } else {
            return $this->errorResponse("Aucune transaction lancée trouvée à cette référence ou transaction déjà effectuée", 404);
        }
    }

    public function changePin(Request $request)
    {
        $agent = $this->findByNumber($request->agentNumber);

        if(!$agent) {
            return $this->errorResponse('Aucun agent trouvé avec ce numéro', 404);
        }

        $people = $this->peopleRepo->getPeople("App\Models\Agent", $agent->id);
        $user = User::where("id", $people->user_id)->first();
        if(!Hash::check($request->oldPin, $user->pin)) {
            return $this->errorResponse("Code pin incorrect", 500);
        }
        $user->pin = bcrypt($request->newPin);
        $user->save();
        return $this->successResponse($user, 'Code pin modifié avec succès', 200);
    }

    public function changePassword(Request $request)
    {
        $agent = $this->findByNumber($request->agentNumber);
        if(!$agent) {
            return $this->errorResponse('Aucun agent trouvé avec ce numéro', 404);
        }

        $people = $this->peopleRepo->getPeople("App\Models\Agent", $agent->id);
        $user = User::where("id", $people->user_id)->first();
        if(!Hash::check($request->oldPassword, $user->password)) {
            return $this->errorResponse("Mot de passe incorrect", 500);
        }
        $user->password = bcrypt($request->newPassword);
        $user->save();
        return $this->successResponse($user, 'Mot de passe modifié avec succès', 200);
    }

    public function findByNumber(String $agentNumber)
    {
        return Agent::where("agentNumber", $agentNumber)->first();
    }

    public function checkIfNull($data)
    {
        if(isset($data)) {
            return true;
        }
        return false ;
    }

    public function createUpdateArray(array $data)
    {
        foreach ($data as $d => $var) {
            if(!$this->checkIfNull($var)) {
                unset($data[$d]);
            }
        }

        //dd($data);
        return $data;
    }

    public function checkDataPresence($data)
    {
        //dd($data);
        if(isset($data)) {
            return $this->errorResponse('Aucun agent trouvé avec ce numéro', 404);
        }
    }

    public function checkIfPinIsCorrect(string $pin, User $user)
    {

    }
}
