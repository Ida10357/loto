<?php

namespace App\Http\Controllers;
use App\Repositories\Generic\GenericImplementation\GenericRepository;
use App\Models\Numero;
use Illuminate\Http\Request;

class NumeroController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
private $GenericRepository;
    public function __construct(GenericRepository $gen)
    {
        $this->GenericRepository = $gen;
        Numero::all();
        //$this->makeModel();
    }
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
// exist pour vérifier l'existnance
        $num[]=rand(10,80);
        dd($num);
       return $this->GenericRepository->create($num);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Numero  $numero
     * @return \Illuminate\Http\Response
     */
    public function show(Numero $numero)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Numero  $numero
     * @return \Illuminate\Http\Response
     */
    public function edit(Numero $numero)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Numero  $numero
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Numero $numero)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Numero  $numero
     * @return \Illuminate\Http\Response
     */
    public function destroy(Numero $numero)
    {
        //
    }
}
