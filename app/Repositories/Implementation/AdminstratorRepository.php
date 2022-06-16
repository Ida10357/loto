<?php


namespace App\Repositories\Implementation;
use App\Models\User;
use App\Models\Clerk;
use App\Models\People;
use App\Models\Country;
use Illuminate\Support\Str;
use App\Traits\ApiResponser;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;
use App\Repositories\Generic\GenericImplementation\GenericRepository;

class AdministratorRepository extends GenericRepository {
    use ApiResponser;

    public function model()
    {
        return 'App\Models\User';
    }
    public function onboard(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:App\Models\User,name',
            'email' => 'sometimes|email|string|unique:App\Models\User,email',
            'password' => 'required|',
            'c_password' => 'required|same:password',
            'pin' => 'required|digits:6',
            'c_pin' => 'required|same:pin',
            'fullname' => 'required|string',
            'number' => 'required|string',
            'country_phone_code' => 'required|string',
          //  'role'=>'sometimes|numeric'
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->all(), 401);
        }
        $user = new User([
            'name' => $request->number,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'activation_token' => Str::random(60),
            'pin' => bcrypt($request->pin),
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
        $country = Country::where("phoneCode", $request->country_phone_code)->first();
        $people->country_id = $country->id;
        $people->user_id = $user->id;
        $people = $this->peopleRepo->addOne($people, $clerk->fresh());
      //  $number = $this->numberRepo->addNumber($request->number, $request->initialBalance, $distributor->id);
      /*   $data["distributor"] = $people;
        $data["number_info"] = $number;
 */
        return $people;
    }
}
