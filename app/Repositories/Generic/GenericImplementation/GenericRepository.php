<?php

namespace  App\Repositories\Generic\GenericImplementation;

use App\Models\Country;
use App\Repositories\Generic\GenericInterface\GenericRepositoryInterface;
use Illuminate\Support\Facades\App;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;
use App\Models\People;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

abstract class GenericRepository implements GenericRepositoryInterface
{
    private $app;
    protected $model;

    /**
     * @param App $app
     * @throws
     */

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->makeModel();
    }

    /**
     * specification du model
     * @return mixed
     */
    abstract function model();

    public function all()
    {
        return $this->model->get();
    }

    public function allActif()
    {
        return $this->model->where("status", 1)->get();
    }

    public function create(array $data)
    {
        try {
            return $this->model->create($data);
        } catch (QueryException $e) {
            throw new QueryException($e->getMessage(),$data,$e->getPrevious());
        }
    }

    public function update(array $data, $id)
    {
        $record = $this->model->find($id);
        if (!$record) {
            throw new ModelNotFoundException( app($this->model())->nameModel() .' not found');
        }
        try {
            return $record->update($data);
        } catch (QueryException $e) {
            throw new QueryException($e->getMessage(),$data,$e->getPrevious());
        }
    }

    public function delete($id)
    {
        try {
            return $this->model->delete($id);
        } catch (QueryException $e) {
            throw new QueryException($e->getMessage(),$id,$e->getPrevious());
        }
    }

    public function show($id)
    {
        try {
            return $this->model->findOrFail($id);
        } catch (QueryException $e) {
            throw new ModelNotFoundException( app($this->model())->nameModel() .' not found');
        }
    }

    public function getModel()
    {
        return $this->model;
    }

    public function setModel($model)
    {
        $this->model = $model;
        return $this;
    }

    public function findByAttribute($attribute, $value)
    {
        return $this->model->where($attribute, $value)->first();
    }


    /**
     * Cette fonction permet de verifier le solde par rapport aux montant en parametre
    */
    public function verifyPin(Request $data , string $pin)
    {
        if (Hash::check($data["pin"], $pin)) {
            return true;
        }
        else{
            return false;
        }
    }

    /**
     * Cette fonction permet de verifier le solde par rapport aux montant en parametre
    */
    public function verifySolde(int $amount , array $account , int $frais)
    {
        if ($amount+ $frais <= $account["balance"]) {
            return true;
        }
        else{
            return false;

        }
    }

    public function verifyCountry(string $number, string $number2)
    {
        $record = substr_compare($number, $number2, 0, 3); // 0;
        if ($record == 0) {
            return true;
        }else {
            return false;
        }
    }

    /**
    * validate data from request
    *
    * @param $rules Array of rules
    * @return Instance of Validator
    */
    public function validateData($rules = [], $messages = [])
    {
        return Validator::make(request()->all(), $rules, $messages);
    }


    /**
     * @return \Illuminate\Database\Eloquent\Builder
     * @throws ReposittoryException
     *
    */
    public function makeModel()
    {
       $model = app($this->model());
       if (!$model instanceof Model)
           //throw new RepositoryException("Class {$this->model()} must be an instance of Illuminate\\Database\\Eloquent\\Model");
           dd("ERROR");

       return $this->model = $model->newQuery();
    }


}
