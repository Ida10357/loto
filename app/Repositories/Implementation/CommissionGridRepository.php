<?php
namespace App\Repositories\Implementation;

use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use App\Models\CommissionGrid;
use App\Models\TrancheGrid;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Repositories\Generic\GenericImplementation\GenericRepository;


class CommissionGridRepository extends GenericRepository {
    use ApiResponser;
    public function model()
    {
        return 'App\Models\CommissionGrid';
    }

    public function allCommission()
    {
        return $this->successResponse($this->all(), 'Toutes les commission', 201);
    }

    public function add(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'label' => 'required|unique:App\Models\CommissionGrid,label',
            'distributorRate' => 'required|integer',
        ]);

        if($validator->fails()){
           return $this->validationErrorResponse($validator->errors()->all(), 402);
        }

        $commissiongrid = new CommissionGrid([
            'label' => $request->label,
            'distributorRate' => $request->distributorRate,
        ]);
        $commissiongrid->save();
        return $this->successResponse($commissiongrid, 'Commission grid enregistrée avec succès', 201);
    }

    public function findName($label)
    {
        $record = $this->getModel()->where('label',$label)->first();
        if (!$record) {
            throw new ModelNotFoundException( app($this->model())->nameModel() .' not found');
        }
        return $record;
    }

    public function updateCommission(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ancienLabel' => 'required',
            'label' => 'sometimes|unique:App\Models\CommissionGrid,label',
            'distributorRate' => 'sometimes|integer',
        ]);

        if($validator->fails()){
            return $this->validationErrorResponse($validator->errors()->all(), 402);
        }

        $commissionexistante = $this->findName($request['ancienLabel']);

        $commissiongrid = [];
        if($request->label != null)
        {
            $commissiongrid["label"] = $request->label;
        }
        if($request->distributorRate != null)
        {
            $commissiongrid["distributorRate"] = $request->distributorRate;
        }
        //dd($commissiongrid);
        $this->update($commissiongrid, $commissionexistante['id']);
        return $this->successResponse($commissiongrid, 'Commission grid modifiée avec succès', 201);
    }

    public function deleteCommission(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'label' => 'required',
        ]);
        if($validator->fails()){
            return $this->validationErrorResponse($validator->errors()->all(), 402);
        }
        $commissiongrid = $this->findName($request['label']);
        $this->delete($commissiongrid['id']);
        return $this->successResponse(null, 'Commission grid supprimée avec succès', 201);
    }

    public function commissionByTranche(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            // 'begin' => 'required|integer',
            // 'end' => 'required|integer',
            // 'commission' => 'required|integer',
        ]);
        if($validator->fails()){
            return $this->validationErrorResponse($validator->errors()->all(), 402);
        }
        $trancheGrid = TrancheGrid::where('id', $request->id)->first();
        $commissiongrid = CommissionGrid::where('id', $trancheGrid['commission_grid_id'])->first();
        //dd($trancheGrid['commission_grid_id']);
        return $this->successResponse($commissiongrid, 'La commission de cette tranche est', 201);
    }
}
