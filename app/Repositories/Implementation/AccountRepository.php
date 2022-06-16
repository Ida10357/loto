<?php

namespace  App\Repositories\Implementation;

use App\Repositories\Generic\GenericImplementation\GenericRepository;
use App\Traits\ApiResponser;
use App\Models\Account;
use App\Models\Number;
use App\Models\AgentNumber;
use App\Models\People;
use App\Models\Transaction;
use App\Models\HistoryBalance;

class AccountRepository extends GenericRepository
{
    use ApiResponser;

    public function __construct()
    {

    }

    public function getAccount(string $number , string $type)
    {
        switch ($type) {
            case 'user':
                $customer = People::where('number', $number)->first();
                $account = Account::where('customer_id', $customer->people_id)->first();
                return $account;
                break;
            case 'entreprise':
                    $customer = People::where('number', $number)->first();
                    $account = Account::where('customer_id', $customer->people_id)->first();
                    return $account;
                    break;

            case 'agent':
                $account = AgentNumber::where('phoneNumber',$number)->first();
                return $account;
                break;

            case 'distributeur':
                $account = Number::where('phoneNumber',$number)->first();
                return $account;
                break;

            default:
                # code...
                break;
        }
    }

    public function debit (string $number , int $amount , string $type)
    {
        $account = $this->getAccount($number,$type);
        $account->balance -= $amount;
        $account->save();

    }

    public function credit (string $number , int $amount , string $type)
    {
        $account = $this->getAccount($number,$type);
        $account->balance += $amount;
        $account->save();

    }

    public function history(array $account , string $type)
    {
        switch ($type) {
            case 'user':
                $history = HistoryBalance::whereDate('created_at', date('Y-m-d'))
                            ->where('customer_account_id',$account["id"])
                            ->first();
                break;
            case 'entreprise':
                    $history = HistoryBalance::whereDate('created_at', date('Y-m-d'))
                                ->where('customer_account_id',$account["id"])
                                ->first();
                    break;

            case 'agent':
                $history = HistoryBalance::whereDate('created_at', date('Y-m-d'))
                            ->where('agent_number_id',$account["id"])
                            ->first();
                break;

            case 'distributeur':
                $history = HistoryBalance::whereDate('created_at', date('Y-m-d'))
                            ->where('distributor_number_id',$account["id"])
                            ->first();
                break;

            default:
                # code...
                break;
        }


            if($history!=null) {
                $history->montant = $account["balance"];
                $history->update();
            } else {
                $history = new HistoryBalance();
                switch ($type) {
                    case 'user':
                        $history->customer_account_id = $account["id"];
                        break;

                    case 'entreprise':
                        $history->customer_account_id = $account["id"];
                        break;


                    case 'agent':
                        $history->agent_number_id = $account["id"];
                        break;

                    case 'distributeur':
                        $history->distributor_number_id = $account["id"];
                        break;

                    default:
                        # code...
                        break;
                }

                $history->montant = $account["balance"];
                $history->save();

            }

    }

    /*
    public function debit(string $number, int $amount) {
        $customer = People::where('number', $number)->first();
        $account = Account::where('customer_id', $customer->people_id)->first();
        $trans = new Transaction();
        //$taux =  $trans->getTaux();
        //$account = Account::where('accountNumber', $account_number)->first();
        //dd($taux);
        if($account && ($amount) <= $account->balance) {
            $account->balance -= $amount;
            $account->save();
            return $account;
        } else {
            return null;
        }
    }
            //@TODO
    public function debitAgent(string $number, int $amount) {
        $agent = AgentNumber::where('phoneNumber',$number)->first();
        if($agent) {
            $agent->balance -= $amount;
            $agent->save();
            // vérifier si il y a une ligne de history_balances enregistrée aujourd'hui
            $history = HistoryBalance::whereDate('created_at', date('Y-m-d'))->first();
            //dd($history);
            if($history!=null) {
                $history->montant = $agent->balance;
                $history->update();
            } else {
                $history = new HistoryBalance();
                $history->agent_number_id = $agent->id;
                $history->montant = $agent->balance;
                $history->save();
                //$history->money_format
            }
            //
            return $agent;
        } else {
            return null;
        }
    }

    public function debitDistributeur(string $number, int $amount) {
        //$customer = People::where('number', $number)->first();
        //$account = Account::where('customer_id', $customer->people_id)->first();
        $distributeur = Number::where('phoneNumber',$number)->first();
        $trans = new Transaction();
        //$taux =  $trans->getTaux();
        //$account = Account::where('accountNumber', $account_number)->first();
        //dd($taux);
        if($distributeur) {
            $distributeur["balance"] -= $amount;
            $distributeur->save();
            return $distributeur;
        } else {
            return null;
        }
    }
    */
    /*
    public function credit(string $number, int $amount) {
        $customer = People::where('number', $number)->first();
        $account = Account::where('customer_id', $customer->people_id)->first();
        //$account = Account::where('accountNumber', $account_number)->first();
        if($account) {
            $account->balance += $amount;
            $account->save();
            return $account;
        } else {

        }

        return null;
    }
    */


    public function changeState(string $account_number) {
        $account = Account::where('accountNumber', $account_number)->first();
        if($account->state) {
            $account->state = false;
        } else {
            $account->state = true;
        }
        $account->save();

        return true;
    }

    public function createAcount($customer_id,$typeAccount){
        /* $data = [
            "accountNumber" => $this->generateNumero(1000000000, 9999999999),
            "customer_id" =>$customer_id
        ];
        $account = $this->create($data); */

        $account = new Account();
        $account->accountNumber = $this->generateNumero(1000000000, 9999999999);
        $account->customer_id = $customer_id;
        $account->typeAccount=$typeAccount;
        $account->save();
        return $account->refresh();
    }

    /*public function createAccountForAgents($agent_id){
        $account = new Account();
        $account->accountNumber = $this->generateNumero(1000000000, 9999999999);
        $account->agent_id = $agent_id;
        $account->save();
        return $account->refresh();
    }*/


    private function generateNumero(int $begin, int $end) {
        $number = mt_rand($begin, $end);
         // call the same function if the barcode exists already
         if ($this->numeroExist($number)) {
            return $this->generateNumero($begin, $end);
        }
        // otherwise, it's valid and can be used
        return $number;
    }

    public function numeroExist($number) {
        return Account::where('accountNumber', $number)->exists();
    }

    public function model()
    {
        return 'App\Models\Account';
    }

}
