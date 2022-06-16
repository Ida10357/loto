<?php

namespace  App\Repositories\Implementation;

use App\Repositories\Generic\GenericImplementation\GenericRepository;
use App\Traits\ApiResponser;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Ably\AblyRest;
use App\Models\Country;
use App\Models\EntrepriseCountrySetting;
use App\Models\Service;
use App\Models\People;
use App\Models\UserCountrySetting;
use App\Repositories\Implementation\RoleRepository;
use App\Traits\ExternalApi;
use Illuminate\Support\Facades\DB;

class TransactionRepository extends GenericRepository
{
    use ApiResponser;
    private $accountRepository;
    use ExternalApi;
    protected $operatorRepo;
    protected $countryRepo;
    private $roleRepository;
    protected $unsavedUserRepo;
    protected $countrySettingRepository;

    public function __construct(AccountRepository $accountRepository,RoleRepository $roleRepository,OperatorRepository $operatorRepo,CountryRepository $countryRepo,
                                UnsavedUserRepository $unsavedUserRepo , CountrySettingRepository $countrySettingRepository
                                )
    {
        $this->accountRepository = $accountRepository;
        $this->operatorRepo=$operatorRepo;
        $this->roleRepository=$roleRepository;
        $this->countryRepo=$countryRepo;
        $this->unsavedUserRepo=$unsavedUserRepo;
        $this->countrySettingRepository=$countrySettingRepository;
    }

    public function getTransactionByReference(string $ref) {
        return Transaction::where('reference', $ref)->first();
    }

    public function getCurrentUserTransactions() {
        $user = Auth::user();
        return $this->getUserTransactions($user->person->people->account[0]);
    }

    public function getAccountBalance()
    {
        $user = Auth::user();
        $data["balance"] = $user->person->people->account[0]->balance;
        return $data;
    }

    public function getCurrentUserAllTransaction() {
        $user = Auth::user();
        return $this->getAllUserTransactions($user->person->people->account[0]);
    }

    public function getCurrentUserTransaction(int $perPage) {
        $user = Auth::user();

        $role=$this->roleRepository->getRoles($user);
        $people=People::where('user_id',$user->id)->first();

        if($role[0] == 'Client')
        {
            $role[0] = 'user';
        }
        if($role[0] == 'Agent')
        {
            $role[0] = 'agent';
        }
        if($role[0] == 'Distributeur')
        {
            $role[0] = 'distributeur';
        }
        $account = $this->accountRepository->getAccount($people->number , $role[0]) ;
        // dd($account);
        return $this->getUserTransactions($account, $perPage);
        // return $this->getUserTransactions($user->person->people->account, $perPage);
    }

    private function getUserTransactions(Account $account, int $perPage = 15) {
        return Transaction::where('owner_id', $account->id)
            ->orWhere('recipient_id', $account->id)
            ->orWhere
            ->latest()->paginate($perPage);
    }

    private function getAllUserTransactions(Account $account) {
        // foreach($accounts as $account){
        //     $data = [
        //         $account->accountNumber => Transaction::where('owner_id', $account->id)
        //         ->orWhere('recipient_id', $account->id)->latest()->get()
        //     ];
        // }
        // return $data;
        return Transaction::where('owner_id', $account->id)
               ->orWhere('recipient_id', $account->id)->latest()->get();
    }

    /**
     * Cette fonction permet de convertir le montant entrer en monnaie Ik
     */


    private function dailyTransaction($countrySetting , $type)
    {
        switch ($type) {
            case 'user':
                    $usercountrySetting = new UserCountrySetting();
                    $dailyTransaction = $this->countrySettingRepository->childContrySetting($countrySetting[0] , $usercountrySetting);
                    return $dailyTransaction;
                break;

            case 'entreprise':
                    $entreprisecountrySetting = new EntrepriseCountrySetting();
                    $dailyTransaction = $this->countrySettingRepository->childContrySetting($countrySetting[1] , $entreprisecountrySetting                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     );
                    return $dailyTransaction;
                break;

            default:
                # code...
                break;
        }
    }
    /**
     * Cette fonction permet de verifier si la limite des transaction a ete atteint ou pas
    */
    private function verifyDailyTransaction(string $type , Country $country , string $number)
    {
        switch ($type) {
            case 'user':
                $account = $this->accountRepository->getAccount($number,$type);
                $champ =  ["owner_id","recipient_id"];
                $transactionAmount = $this->getUserAmountTransactions($account,$champ);
                $countrySetting = $this->countrySettingRepository->findByCountry($country) ;

                $dailyTransaction = $this->dailyTransaction($countrySetting , $type);
                break;
            case 'entreprise':
                    $account = $this->accountRepository->getAccount($number,$type);
                    $champ =  ["owner_id","recipient_id"];
                    $transactionAmount = $this->getUserAmountTransactions($account,$champ);
                    $countrySetting = $this->countrySettingRepository->findByCountry($country) ;
                    $dailyTransaction = $this->dailyTransaction($countrySetting , $type);
                    break;

            default:
                # code...
                break;
        }
        $data = [];
        if ($dailyTransaction["dailyTransaction"] >= count($transactionAmount)  ) {
            $data["dailyTransaction"] = true;
        } else {
            $data["dailyTransaction"] = false;
        }
        if ($dailyTransaction["dailyamout"] >= collect($transactionAmount)->sum("montant")) {
            $data["dailyamout"] = true;
        } else {
            $data["dailyamout"] = false;
        }
        return $data;
    }

    private function getUserAmountTransactions(Account $account , array $champ) {
         // pour le montant calculer par jour
         $now =  date('Y-m-d H:i:s',mktime(00,00,01));
        $fin =  date('Y-m-d  H:i:s');
        return Transaction::where($champ[0], $account->id)
                ->orWhere($champ[1], $account->id)->latest()
                ->whereBetween('created_at',[$now , $fin])
                ->get();
    }

    public function getTransactions(int $perPage) {
        return Transaction::latest()->paginate($perPage);
    }

    public function waiting(string $account_number, int $amount, string $country , string $operator_label ) {



        $country = $this->countryRepo->findName($country);
        $operator = $this->operatorRepo->findName($operator_label);


        $distributor=  DB::table('distributors')
            ->join('numbers','distributors.id','=','numbers.distributor_id')
            ->where('numbers.operator_id',$operator->id)
            ->where('numbers.balance','>',$amount)
            ->where('distributors.country_id',$country->id)
            ->where('distributors.actif',true)
            ->select('numbers.phoneNumber')
            ->get()
            ->toArray();
        $distributor_number = array_rand($distributor,1);
        $recharge=Service::where('libelle','recharge')
            ->where('operator_id',$operator->id)
            ->where('country_id',$country->id)
            ->first();
        $ably = new AblyRest($this->ablyToken());

        $transaction=[
                'code'=>$recharge->ussdCodurse,
                'key'=>$recharge->ref,
                'distributor'=>$distributor[$distributor_number]->phoneNumber,
               // 'distributor'=>"90301399",
                'somme'=>(string)$amount,
                'numero'=>$account_number,
            ];
        $ably->channel($this->ablyChannel())->publish('',$transaction);
        return $transaction;
    }

    /*
    public function withdrawal(string $account_number, int $amount ,string $distributor) {
        $transaction = new Transaction();
        $transaction_distributor = new Transaction();
        $account = $this->accountRepository->debit($account_number, $amount);
        $account_distributor = $this->accountRepository->debitDistributeur($distributor,$amount);

        $description = 'Retrait de '.$amount.' depuis votre compte';
        $description_distributor = 'Retrait de '.$amount.' depuis votre compte';

        $transaction->reference = $this->generateRef('WDL');
        $transaction_distributor->reference = $this->generateRef('WDL');

        $transaction->owner_id =$account->id;
        $transaction_distributor->number_id =$account_distributor["id"];
        $transaction->description = $description;
        $transaction_distributor->description = $description_distributor;
        $transaction->montant = $amount;
        $transaction_distributor->montant = $amount;
        if($account) {
            $transaction->status = 'success';
            $transaction_distributor->status = 'success';
        } else {
            $transaction->status = 'failed';
            $transaction_distributor->status = 'failed';
        }
        $transaction->save();
        $transaction_distributor->save();
        return $transaction;
    }
    */

    public function transfer(Request $request)
    {
        $loginUser = auth()->guard('api')->user();
        $sameCountry = $this->verifyCountry($request["senderNumber"] , $request["receiverNumber"]);
        $countryReceiver = $this->findByPhoneCode(substr($request["receiverNumber"], 0,3));
        $countrySender = $this->findByPhoneCode(substr($request["senderNumber"], 0,3));
        $verifyPin =  $this->verifyPin($request, $loginUser->pin);
        $verifyNumber =  $this->verifyNumber($request["receiverNumber"]);
        $infoAccount = [
            'balance' => $request["balance"]
        ];

        // Verifier si l'utilisateur est verifier dans le systeme

        // Dans le cas ou l'utilisateur n'existe pas
        if ($verifyNumber == false) {
            // frais pour un les utilisateurs non enregistrer qui sont dans deux pays differents
            if ($sameCountry == false &&  $verifyNumber == false) {

                $countrysetting = $countrySender->countrySetting;
                $verifySolde =  $this->verifySolde($request["amount"],$infoAccount,($request["amount"]*$countrysetting[0]["rateUnsavedUserInterCountry"]));
                $amount = $request["amount"]+ ($request["amount"]*$countrysetting[0]["rateUnsavedUserInterCountry"]);

            }

             // frais pour un les utilisateurs non enregistrer qui sont dans le meme pays
             if ($sameCountry == true &&  $verifyNumber == false) {
                $countrysetting = $countrySender->countrySetting;
                $verifySolde =  $this->verifySolde($request["amount"],$infoAccount,($request["amount"]*$countrysetting[0]["rateUnsavedUserIntraCountry"]));
                $amount = $request["amount"]+ ($request["amount"]*$countrysetting[0]["rateUnsavedUserIntraCountry"]);

            }

            if ($verifySolde == true && $verifyPin == true) { //
               return  $this->unsavedUserTransaction($request,$request["typeSender"],$amount);
            } else {
                return $this->errorResponse("Verifier les informations saisies",401);
            }
        } else{


            $verifySender = $this->checkIfPeopleValidateAccount($this->getPeopleNumber($request["senderNumber"]));
            $verifyReceiver = $this->checkIfPeopleValidateAccount($this->getPeopleNumber($request["receiverNumber"]));
            if ($verifySender == false || $verifyReceiver == false) {
                $verifyDailyTransactionReceiver =   $this->verifyDailyTransaction($request["typeReceiver"],$countryReceiver,$request["receiverNumber"]);
                $verifyDailyTransactionSender =   $this->verifyDailyTransaction($request["typeSender"],$countrySender,$request["senderNumber"]);
                // on verifie que niveau des transaction que tout est normal
                if ($verifyDailyTransactionReceiver["dailyamout"] == false || $verifyDailyTransactionSender["dailyamout"] == false || $verifyDailyTransactionSender["dailyTransaction"] == false) {
                    return $this->errorResponse("Seuil de votre compte atteint pour la journee",401);
                }
            }
            // frais pour un les utilisateurs  qui sont dans deux pays differents
            if ($sameCountry == false) {
                $countrysetting = $countrySender->countrySetting;
                $verifySolde =  $this->verifySolde($request["amount"],$infoAccount,($request["amount"]*$countrysetting[0]["rateInterCountry"]));
                $amount = $request["amount"]+ ($request["amount"]*$countrysetting[0]["rateInterCountry"]);
            }
            // frais pour un les utilisateurs  qui sont dans le meme pays
            else {
                $countrysetting = $countrySender->countrySetting;
                $verifySolde =  $this->verifySolde($request["amount"],$infoAccount,($request["amount"]*$countrysetting[0]["rateIntraCountry"]));
                $amount = $request["amount"]+ ($request["amount"]*$countrysetting[0]["rateIntraCountry"]);
            }
            if ($verifySolde == true && $verifyPin == true) { //
                return $this->basicTransaction($request,$request["typeSender"],$amount);

            } else {
                return $this->errorResponse($request["balance"],401);
            }

        }
    }

    public function InformationTransaction(Request $request)
    {
        $loginUser = auth()->guard('api')->user();
        $sameCountry = $this->verifyCountry($request["senderNumber"] , $request["receiverNumber"]);
        $countryReceiver = $this->findByPhoneCode(substr($request["receiverNumber"], 0,3));
        $countrySender = $this->findByPhoneCode(substr($request["senderNumber"], 0,3));
        $verifyNumber =  $this->verifyNumber($request["receiverNumber"]);
        if ($verifyNumber == false) {
            // frais pour un les utilisateurs non enregistrer qui sont dans deux pays differents
            if ($sameCountry == false &&  $verifyNumber == false) {

                $countrysetting = $countrySender->countrySetting;
                $frais =($request["amount"]*$countrysetting[0]["rateUnsavedUserInterCountry"]);
                $amount = $request["amount"]+ $frais;

            }

             // frais pour un les utilisateurs non enregistrer qui sont dans le meme pays
             if ($sameCountry == true &&  $verifyNumber == false) {
                $countrysetting = $countrySender->countrySetting;
                $frais =($request["amount"]*$countrysetting[0]["rateUnsavedUserIntraCountry"]);
                $amount = $request["amount"]+ ($request["amount"]*$countrysetting[0]["rateUnsavedUserIntraCountry"]);

            }
            $data['verifyUser'] = false;
            $data['frais'] = $frais;
            $data['montantTotal'] = $amount;
            $data['montant'] = $request["amount"];

            return $this->successResponse($data,"Information d'une transaction");

        } else{

            $InformationReceiver = $this->getPeopleNumber($request["receiverNumber"]);

            // frais pour un les utilisateurs  qui sont dans deux pays differents
            if ($sameCountry == false) {
                $countrysetting = $countrySender->countrySetting;
                $frais =($request["amount"]*$countrysetting[0]["rateInterCountry"]);
                $amount = $request["amount"]+ $frais;
            }
            // frais pour un les utilisateurs  qui sont dans le meme pays
            else {
                $countrysetting = $countrySender->countrySetting;
                $frais =($request["amount"]*$countrysetting[0]["rateIntraCountry"]);
                $amount = $request["amount"]+ ($request["amount"]*$countrysetting[0]["rateIntraCountry"]);
            }

            $data['verifyUser'] = true;
            $data['informationUser'] = $InformationReceiver;
            $data['frais'] = $frais;
            $data['montantTotal'] = $amount;
            $data['montant'] = $request["amount"];

            return $this->successResponse($data,"Information d'une transaction");


        }

    }





    public function depositAgent(Request $request)
    {
        $loginUser = auth()->guard('api')->user();

        //dd($loginUser);
        $sameCountry = $this->verifyCountry($request["senderNumber"] , $request["receiverNumber"]);
        $countryReceiver = $this->findByPhoneCode(substr($request["receiverNumber"], 0,3));
        $countrySender = $this->findByPhoneCode(substr($request["senderNumber"], 0,3));
        $verifyPin =  $this->verifyPin($request, $loginUser->pin);
        $verifyNumber =  $this->verifyNumber($request["receiverNumber"]);
        $infoAccount = [
            'balance' => $request["balance"]
        ];

        //on trouve le amount. On ajouter le rateInterCountry si l'expediteur et le destinataire sont dans deux pays different dans le cas contraire les frais sont de 0
        if ($sameCountry == false) {
            $countrysetting = $countrySender->countrySetting;
            $verifySolde =  $this->verifySolde($request["amount"],$infoAccount,($request["amount"]*$countrysetting[0]["rateInterCountry"]));
            $amount = $request["amount"]+ ($request["amount"]*$countrysetting[0]["rateInterCountry"]);

        } else {
            $verifySolde =  $this->verifySolde($request["amount"],$infoAccount,0);
            $amount = $request["amount"]+ 0;
        }
        // On verifie le solde le pin et l'existence de l'utilisateur. On verifie aussi si l'utilisateur est verifier ou pas
        // Si l'utilisateur n'est pas verifier on verifie le nombre de transaction.

        if ($verifySolde == true && $verifyPin == true && $verifyNumber == true) { //
            $verifyUser = $this->checkIfPeopleValidateAccount($this->getPeopleNumber($request["receiverNumber"]));
            if ($verifyUser == false) {
                 $verifyDailyTransaction =   $this->verifyDailyTransaction($request["typeReceiver"],$countryReceiver,$request["receiverNumber"]);
                if ($verifyDailyTransaction["dailyamout"] == false) {
                    return $this->errorResponse("Seuil de votre compte atteint pour la journee",401);
                }
            }

            return $this->basicTransaction($request,$request["typeSender"],$amount);

        } else {
            return $this->errorResponse("Verifier les informations saisi",401);
        }
    }

    public function depositPeople(Request $request)
    {
        $countrySender = $this->findByPhoneCode(substr($request["senderNumber"], 0,3));
        //return $this->successResponse($countrySender);
        //$verifyPin =  $this->verifyPin($request, $loginUser->pin);
        $amount =  $this->conversionIk($request["amount"] , $countrySender , $request["typeTransaction"]);
        $verifyUser = $this->checkIfPeopleValidateAccount($this->getPeopleNumber($request["senderNumber"]));
            if ($verifyUser == false) {
                 $verifyDailyTransaction =   $this->verifyDailyTransaction($request["typeSender"],$countrySender,$request["senderNumber"]);
                if ($verifyDailyTransaction["dailyamout"] == false) {
                    return $this->errorResponse("Seuil de votre compte atteint pour la journee",401);
                }
            }
            return $this->peopleTransaction($request,$request["typeTransaction"],$amount);




    }

    /**
     * Cette fonction permet de faire les operations de debit et credit mise a jour des comptes dans la transaction et creation des historiques
     */
    private function basicTransaction(Request $request , string $typeEnvoi , int $amount)
    {
        $this->accountRepository->debit($request["senderNumber"],$amount,$typeEnvoi);
        $this->accountRepository->credit($request["receiverNumber"],$amount,$request["typeReceiver"]);
        $sendAccount = $this->accountRepository->getAccount($request["senderNumber"],$typeEnvoi);
        $receiverAccount = $this->accountRepository->getAccount($request["receiverNumber"],$request["typeReceiver"]);
        if ($typeEnvoi == "agent") {
            $transaction = $this->transactionAgent($sendAccount["id"] ,$amount,$receiverAccount["id"]);
        }
        else {
            $transaction = $this->transaction($sendAccount["id"] , $sendAccount["accountNumber"],$receiverAccount["accountNumber"],$amount,$receiverAccount["id"]);
        }
        $this->accountRepository->history($sendAccount->toArray(),$typeEnvoi);
        $this->accountRepository->history($receiverAccount->toArray(),$request["typeReceiver"]);
        return $this->successResponse($transaction, "Transaction effectuée avec succès.");
    }

    private function peopleTransaction(Request $request , string $typeTransaction , $amount)
    {
        switch ($typeTransaction) {
            case 'retrait':
                $this->accountRepository->debit($request["senderNumber"],$amount,$request["typeSender"]);
                $this->accountRepository->debit($request["receiverNumber"],$amount,$request["typeReceiver"]);
                break;
            case 'depot':
                $this->accountRepository->credit($request["senderNumber"],$amount,$request["typeSender"]);
                $this->accountRepository->credit($request["receiverNumber"],$amount,$request["typeReceiver"]);
                break;

            default:
                # code...
                break;
        }

        $sendAccount = $this->accountRepository->getAccount($request["senderNumber"],$request["typeSender"]);
        $receiverAccount = $this->accountRepository->getAccount($request["receiverNumber"],$request["typeReceiver"]);

        $transaction = $this->peopleStoreTransaction($sendAccount["id"],$amount, $typeTransaction,$request["typeSender"]);
        $transaction2 = $this->peopleStoreTransaction($receiverAccount["id"],$amount, $typeTransaction,$request["typeReceiver"]);

        $this->accountRepository->history($sendAccount->toArray(),$request["typeSender"]);
        $this->accountRepository->history($receiverAccount->toArray(),$request["typeReceiver"]);
        return $this->successResponse($transaction);

    }

    private function unsavedUserTransaction(Request $request , string $typeEnvoi , int $amount)
    {
        $unsavedUser = $this->unsavedUserRepo->addUnsavedUser($request);
        $code =rand(1000,9999);
        $sendAccount = $this->accountRepository->getAccount($request["senderNumber"],$typeEnvoi);
        $transaction = $this->transactionUnsavedUser($sendAccount["id"] , $sendAccount["accountNumber"],$request["receiverNumber"],$amount,$unsavedUser["id"],$code);
        $this->accountRepository->debit($request["senderNumber"],$amount,$typeEnvoi);
        $this->accountRepository->history($sendAccount->toArray(),$typeEnvoi);

        return $this->successResponse($transaction);
    }

    public function InformationwithdrawalUnsavedUser(Request $request)
    {
        $loginUser = auth()->guard('api')->user();
        $verifyPin =  $this->verifyPin($request, $loginUser->pin);
        if ($verifyPin == true) {
            $transaction = Transaction::where('CodeTransaction', $request->CodeTransaction)
                            ->where('status', 'pending')
                            ->join('unsaved_users','transactions.unsavedUser_id','=','unsaved_users.id')
                            ->select('transactions.*','unsaved_users.firstname','unsaved_users.lastname','unsaved_users.number')
                            ->first();

            if ($transaction != null) {
                return $this->successResponse($transaction, 'Information transaction', 200);
            } else {
                return $this->errorResponse("Aucune transaction lancée trouvée à cette référence ou transaction déjà effectuée", 404);
            }
        }else {
            return $this->errorResponse("Code Pin erroner", 404);
        }
    }

    public function withdrawalUnsavedUser(Request $request)
    {
            $transaction = Transaction::find($request->transactionId);
            if ($transaction != null) {
                $sendAccount = $this->accountRepository->getAccount($request["agentNumber"],"agent");
                $this->accountRepository->credit($request["agentNumber"],$transaction->montant,"agent");
                $transaction->status = 'success';
                $transaction->update();
                $this->accountRepository->history($sendAccount->toArray(),"agent");
                return $this->successResponse($transaction, 'Transaction effectuer avec success', 200);
            } else {
                return $this->errorResponse("Aucune transaction lancée trouvée à cette référence ou transaction déjà effectuée", 404);
            }
    }

    private function transactionAgent(string $accountSenderId, int $amount,string $accountReceiverId,string $service=null  ) {
        $transaction = new Transaction();
        if($service!=null){
            $description = 'Utilisation du service '.$service.' a hauteur de '.$amount.'FCFA';
        }else{
            $description = 'Dépôt de '.$amount.' sur votre compte chez l agent ';
        }

        $transaction->reference = $this->generateRef('DEP');
        $transaction->agent_number_id =$accountSenderId;
        $transaction->description = $description;
        $transaction->montant = $amount;
        $transaction->recipient_id =$accountReceiverId;
        $transaction->status = 'success';
        $transaction->save();
        return $transaction;
    }

    private function transaction(string $accountSenderId,string $accountNumberSender,string $accountNumberReceiver, int $amount,string $accountReceiverId,string $service=null  ) {
        $transaction = new Transaction();
        if($service!=null){
            $description = 'Utilisation du service '.$service.' a hauteur de '.$amount.'FCFA';
        }else{
            $description = 'Transfert de '.$amount.' depuis le compte numéro: '. $accountNumberSender.' vers le compte numéro: '.$accountNumberReceiver;
        }
        $transaction->reference = $this->generateRef('DEP');
        $transaction->owner_id =$accountSenderId;
        $transaction->description = $description;
        $transaction->montant = $amount;
        $transaction->recipient_id =$accountReceiverId;
        $transaction->status = 'success';
        $transaction->save();
        return $transaction;
    }

    private function peopleStoreTransaction(string $accountSenderId, int $amount,string $typeTransaction, string $type ,string $service=null  ) {
        $transaction = new Transaction();
        if($service!=null){
            $description = 'Utilisation du service '.$service.' a hauteur de '.$amount.'FCFA';
        }
        if ($typeTransaction == "retrait") {
            $description = 'Retrait de '.$amount.' depuis votre compte';
        }
        if ($typeTransaction == "depot") {
            $description = 'Dépôt de '.$amount.' sur votre compte';
        }

        $transaction->reference = $this->generateRef('DEP');
        switch ($type) {
            case 'user':
                $transaction->owner_id =$accountSenderId;
                break;
            case 'entreprise':
                $transaction->owner_id =$accountSenderId;
                break;
            case 'distributeur':
                $transaction->number_id =$accountSenderId;
                break;

            default:
                # code...
                break;
        }
        $transaction->description = $description;
        $transaction->montant = $amount;
        $transaction->status = "success";
        $transaction->save();
        return $transaction;


    }

    private function transactionUnsavedUser(string $accountSenderId,string $accountNumberSender,string $numberReceiver, int $amount,string $accountReceiverId,string $code  ) {
        $transaction = new Transaction();
        $description = 'Transfert de '.$amount.' depuis le compte numéro: '. $accountNumberSender.' vers le  numéro: '.$numberReceiver;
        $transaction->reference = $this->generateRef('DEP');
        $transaction->owner_id =$accountSenderId;
        $transaction->description = $description;
        $transaction->montant = $amount;
        $transaction->unsavedUser_id =$accountReceiverId;
        $transaction->status = 'pending';
        $transaction->CodeTransaction = $code;
        $transaction->save();
        return $transaction;
    }


    private function generateRef($prefix) {
        return $prefix.''.now();
    }

    public function model()
    {
        return 'App\Models\Transaction';
    }

    public function getTransactionByCountry(String $countryName)
    {
        $country = Country::where("name", $countryName)->first();

        $transactions=  DB::select('select t.status, t.reference, t.montant, t.created_at, p.fullname, p.number, p.cardNumber, c.nicename, c.name
                                        from transactions as t
                                        join accounts as a
                                            on t.owner_id = a.id
                                            or t.recipient_id = a.id
                                        join customers
                                            on customers.id = a.customer_id
                                        join people as p
                                            on p.people_id = customers.id
                                        join countries as c
                                            on p.country_id = c.id
                                        where c.id = ? and p.people_type = ?', [$country->id, "App\Model\Customer"]);

        return $this->successResponse($transactions,'liste des transactions', 201);
    }

    public function getTransactionForCustomer(Request $request)
    {
        $people = People::where("fullname", $request->fullname)->where("people_type", "App\Model\Customer")->where("number", $request->number)->first();
        if(!$people) {
            return $this->errorResponse('Aucun utilisateur trouvé', 404);
        }
        $sent =  DB::select('select t.status, t.reference, t.montant, t.created_at, p2.fullname as receiver_name, p2.number as receiver_number, co.name as receiver_country
                                       from transactions as t
                                        left join accounts as a1
                                            on t.owner_id = a1.id
                                        left join accounts as a2
                                            on t.recipient_id = a2.id
                                        left join customers c1
                                            on c1.id = a1.customer_id
                                        left join customers c2
                                            on c2.id = a2.customer_id
                                        left join people as p1
                                            on p1.people_id = c1.id
                                        left join people as p2
                                            on p2.people_id = c2.id
                                        left join countries as co
                                            on p2.country_id = co.id
                                        where p1.fullname = ? and p1.people_type = ? and p1.number = ?', [$request->fullname, "App\Model\Customer", $request->number]);

        $sentToUnsaved =  DB::select('select t.status, t.reference, t.montant, t.created_at, u.firstname as receiver_fistname, u.lastname as receiver_lastname
                                        from transactions as t
                                        join unsaved_users u
                                            on u.id = t.unsavedUser_id
                                        left join accounts as a
                                            on t.recipient_id = a.id
                                        left join customers c
                                            on c.id = a.customer_id
                                        left join people as p
                                            on p.people_id = c.id
                                        where p.fullname = ? and p.people_type = ? and p.number = ?', [$request->fullname, "App\Model\Customer", $request->number]);

        $received =  DB::select('select t.status, t.reference, t.montant, t.created_at, p2.fullname as sender_name, p2.number as sender_number, co.name as sender_country
                                        from transactions as t
                                        left join accounts as a1
                                            on t.recipient_id = a1.id
                                        left join accounts as a2
                                            on t.owner_id = a2.id
                                        left join customers c1
                                            on c1.id = a1.customer_id
                                        left join customers c2
                                            on c2.id = a2.customer_id
                                        left join people as p1
                                            on p1.people_id = c1.id
                                        left join people as p2
                                            on p2.people_id = c2.id
                                        left join countries as co
                                            on p2.country_id = co.id
                                        where p1.fullname = ? and p1.people_type = ? and p1.number = ?', [$request->fullname, "App\Model\Customer", $request->number]);

        $transactions = [
            "sent" => $sent,
            "received" => $received,
            "sentToUnsavedUser" => $sentToUnsaved
        ];
        return $this->successResponse($transactions,'liste des transactions', 201);
    }
}
