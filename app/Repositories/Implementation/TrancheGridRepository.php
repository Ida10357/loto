<?php
namespace App\Repositories\Implementation;

use App\Models\TrancheGrid;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Repositories\Generic\GenericImplementation\GenericRepository;

class TrancheGridRepository extends GenericRepository {
    use ApiResponser;
    protected $commissionGridRepo;

    public function model()
    {
        return 'App\Models\TrancheGrid';
    }

    public function allTrancheGrids()
    {
        return $this->successResponse($this->all(), 'Toutes les tranches grid', 201);
    }

    public function allTrancheGridsByCommission(Request $request, CommissionGridRepository $commissionGridRepo)
    {
        $validator = Validator::make($request->all(), [
            'commission_grid_label'=> 'required|string',
        ]);
        if($validator->fails()){
            return $this->validationErrorResponse($validator->errors()->all(), 402);
        }

        $commission_grid = $commissionGridRepo->findName($request->commission_grid_label);
        $tranchegrid = TrancheGrid::where('commission_grid_id', $commission_grid['id'])->get();
        //dd($tranchegrid);
        return $this->successResponse($tranchegrid, 'Toutes les tranches grid de cette commission', 201);
    }

    public function addTrancheGrids(Request $request, CommissionGridRepository $commissionGridRepo)
    {
        $validator = Validator::make($request->all(),[
            'begin' => 'required|integer',
            'end' => 'required|integer',
            'commission' => 'required|integer',
            'commission_grid_label'=> 'required|string',
        ]);

        if($validator->fails()){
           return $this->validationErrorResponse($validator->errors()->all(), 402);
        }

        $commission_grid = $commissionGridRepo->findName($request->commission_grid_label);

        $tranchegrid = new TrancheGrid([
            'begin' => $request->begin,
            'end' => $request->end,
            'commission' => $request->commission,
            'commission_grid_id'=> $commission_grid['id'],
        ]);
        $tranchegrid->save();
            //dd($tranchegrid);
        return $this->successResponse($tranchegrid, 'Tranche grid enregistrée avec succès', 201);
    }

    public function updateTrancheGrid(Request $request, CommissionGridRepository $commissionGridRepo)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
            'begin' => 'sometimes|integer',
            'end' => 'sometimes|integer',
            'commission' => 'sometimes|integer',
            'commission_grid_label'=> 'sometimes|string',
        ]);

        if($validator->fails()){
            return $this->validationErrorResponse($validator->errors()->all(), 402);
        }
        //$tranchegrid_old = TrancheGrid::find($request->id)->get();
        $tranchegrid = [];
        if($request->begin != null)
        {
            $tranchegrid["begin"] = $request->begin;
        }
        if($request->end != null)
        {
            $tranchegrid["end"] = $request->end;
        }
        if($request->commission != null)
        {
            $tranchegrid["commission"] = $request->commission;
        }
        if($request->commission_grid_label != null)
        {
            $commission_grid = $commissionGridRepo->findName($request->commission_grid_label);
            $tranchegrid["commission_grid_id"] = $commission_grid['id'];
        }
        //dd($tranchegrid_old['id']);
        $this->findName($request->id);
        $this->update($tranchegrid, $request->id);

        return $this->successResponse($tranchegrid, 'Tranche grid modifiée avec succès', 201);
    }

    public function deleteTrancheGrid(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
        ]);

        if($validator->fails()){
            return $this->validationErrorResponse($validator->errors()->all(), 402);
        }
        $tranchegrid = TrancheGrid::where('id', $request->id)->first();

        //dd(count($tranchegrid));
        $tranchegrid->delete();
        //$this->delete(18);

        return $this->successResponse(null, "Tranche supprimée avec succès", 201);
    }

    public function findName($id)
    {
        $record = $this->getModel()->where('id',$id)->first();
        if (!$record) {
            throw new ModelNotFoundException( app($this->model())->nameModel() .' not found');
        }
        return $record;
    }
}
