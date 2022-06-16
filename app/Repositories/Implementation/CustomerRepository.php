<?php

namespace  App\Repositories\Implementation;

use App\Models\Country;
use App\Repositories\Generic\GenericImplementation\GenericRepository;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use App\Models\People;
use App\Models\Customer;
use App\Models\User;
use App\Repositories\Implementation\PeopleRepository;
use App\Repositories\Implementation\AccountRepository;

class CustomerRepository extends GenericRepository
{
    use ApiResponser;
    private $peopleRepository;
    private $accountRepository;

    public function __construct(PeopleRepository $peopleRepository, AccountRepository $accountRepository)
    {
        $this->peopleRepository = $peopleRepository;
        $this->accountRepository = $accountRepository;
    }

    public function createAcount(array $data){
       $customer = new Customer();
       $customer->save();
       $people = $this->create($data, $customer);
    }

    public function onboard(Request $request){
        $user = User::where('name', $request->get('name'))->first();
        $people = new People();
        $customer = new Customer();
        $country = Country::where('phoneCode', $request->get('country_phone_code'))->first();
        $people->fullname = $request->get('fullname');
        $people->number = $request->get('number');
        $people->country_id = $country->id;
        $people->user_id = $user->id;
        $customer->save();
        $people = $this->peopleRepository->addOne($people, $customer->fresh());
        $account = $this->accountRepository->createAcount($customer->id,"user");
        $data["customer"] = $people;
        $data["account_info"] = $account;
        return $data;
    }

    public function model()
    {
        return 'App\Models\Customer';
    }

}
