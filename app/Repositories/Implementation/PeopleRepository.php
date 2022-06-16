<?php

namespace  App\Repositories\Implementation;

use App\Models\People;
use App\Repositories\Generic\GenericImplementation\GenericRepository;
use App\Traits\ApiResponser;
use GuzzleHttp\Psr7\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class PeopleRepository extends GenericRepository
{
    use ApiResponser;
    private $rules = [

    ];

    public function addOne(People $people, Model $model) {
        if($model instanceof Model) {
            //$people = $this->create($data);
            $people->people()->associate($model);
            // $people->user_id = Auth::user()->id;
            //dd($people);
            $people->save();
            return $people;
        }
        return null;
    }

    public function model()
    {
        return 'App\Models\People';
    }

    public function getPeople(String $type, int $id)
    {
        return People::where("people_type", $type)->where("people_id", $id)->first();
    }
}
