<?php

namespace App\Repositories\Implementation;

use App\Models\Country;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Models\CountrySetting;
use App\Repositories\Generic\GenericImplementation\GenericRepository;
use Illuminate\Database\Eloquent\Model;

class CountrySettingRepository extends GenericRepository{

    public function model()
    {
        return 'App\Models\CountrySetting';
    }

    public function addCountrySettingForUser(Request $request, $country, $userCountrySetting)
    {
        $formRequest = [
            "rateInterCountry" => $request->userRateInterCountry,
            "rateIntraCountry" => $request->userRateIntraCountry,
            "commissionInterCountry" => $request->userCommissionInterCountry,
            "rateUnsavedUserInterCountry" => $request->userRateUnsavedUserInterCountry,
            "rateUnsavedUserIntraCountry" => $request->userRateUnsavedUserIntraCountry,
            "country_id" => $country->id,
            "setting_id" => $userCountrySetting->id,
            "deviseRate" => $request->deviseRate/100,
            "setting_type" => 'App\Models\UserCountrySetting'
        ];
        return $this->getModel()->create($formRequest);
    }

    public function addCountrySettingForEntreprise(Request $request, $country, $entrepriseCountrySetting)
    {
        $formRequest = [
            "rateInterCountry" => $request->entrepriseRateInterCountry,
            "rateIntraCountry" => $request->entrepriseRateIntraCountry,
            "commissionInterCountry" => $request->entrepriseCommissionInterCountry,
            "rateUnsavedUserInterCountry" => $request->entrepriseRateUnsavedUserInterCountry,
            "rateUnsavedUserIntraCountry" => $request->entrepriseRateUnsavedUserIntraCountry,
            "country_id" => $country->id,
            "setting_id" => $entrepriseCountrySetting->id,
            "deviseRate" => $request->deviseRate/100,
            "setting_type" => 'App\Models\EntrepriseCountrySetting'
        ];
        return $this->getModel()->create($formRequest);
    }

    public function findByCountry(Country $country)
    {
        return $country->countrySetting;
    }

    public function childContrySetting(CountrySetting $countrySetting , Model $model)
    {
        return $model::find($countrySetting["setting_id"]);

    }
}

?>
