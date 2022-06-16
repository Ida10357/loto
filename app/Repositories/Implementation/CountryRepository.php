<?php

namespace App\Repositories\Implementation;

use App\Repositories\Generic\GenericImplementation\GenericRepository;
use App\Traits\ApiResponser;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CountryRepository extends GenericRepository{

    use ApiResponser;

    public function model()
    {
        return 'App\Models\Country';
    }

    public function findName($name)
    {
        $record = $this->getModel()->where('name',$name)->first();
        if (!$record) {
            throw new ModelNotFoundException( app($this->model())->nameModel() .' not found');
        }
        return $record;
    }




}
