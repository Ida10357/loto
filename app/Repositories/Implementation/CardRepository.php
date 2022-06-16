<?php

namespace App\Repositories\Implementation;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Repositories\Generic\GenericImplementation\GenericRepository;

class CardRepository extends GenericRepository{

    public function model() {
        return "App\Models\Card";
    }
}

?>
