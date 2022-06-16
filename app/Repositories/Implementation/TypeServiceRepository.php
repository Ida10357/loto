<?php

namespace App\Repositories\Implementation;

use App\Repositories\Generic\GenericImplementation\GenericRepository;
use App\Traits\ApiResponser;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;

class TypeServiceRepository extends GenericRepository{
    protected $rules = [
        'label' => 'required|string|unique:App\Models\TypeService',
        'ancienLabel' => 'sometimes|string|different:label',
    ];
    use ApiResponser;

    public function model()
    {
        return 'App\Models\TypeService';
    }

    public function findName($label)
    {
        $record = $this->getModel()->where('label',$label)->first();
        if (!$record) {
            throw new ModelNotFoundException( app($this->model())->nameModel() .' not found');
        }
        return $record;
    }
}
