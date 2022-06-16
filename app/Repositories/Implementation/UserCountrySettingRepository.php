<?php

namespace App\Repositories\Implementation;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Repositories\Generic\GenericImplementation\GenericRepository;

use App\Models\UserCountrySetting;

class UserCountrySettingRepository extends GenericRepository{

    public function model() {
        return "App\Models\UserCountrySetting";
    }

    public function addUserCountrySetting(Request $request)
    {
        $formRequest = [
            "dailyTransaction" => $request->userDailyTransaction,
            "dailyamout" => $request->userDailyamout,
        ];
        return $this->getModel()->create($formRequest);
    }
}

?>
