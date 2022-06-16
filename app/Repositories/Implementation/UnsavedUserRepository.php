<?php

namespace App\Repositories\Implementation;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Repositories\Generic\GenericImplementation\GenericRepository;

class UnsavedUserRepository extends GenericRepository{

    public function model() {
        return "App\Models\UnsavedUser";
    }

    public function addUnsavedUser(Request $request)
    {
        $formRequest = [
            "firstname" => $request->firstname,
            "lastname" => $request->lastname,
            'number'=> $request->receiverNumber,
        ];
        return $this->getModel()->create($formRequest);
    }
}

?>
