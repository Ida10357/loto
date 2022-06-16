<?php

namespace App\Repositories\Implementation;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Repositories\Generic\GenericImplementation\GenericRepository;

class EntrepriseCountrySettingRepository extends GenericRepository{

    public function model() {
        return "App\Models\EntrepriseCountrySetting";
    }

    public function addEntrepriseCountrySetting(Request $request)
    {
        $formRequest = [
            "dailyTransaction" => $request->entrepriseDailyTransaction,
            "dailyamout" => $request->entrepriseDailyamout,
        ];
        return $this->getModel()->create($formRequest);
    }
}

?>
