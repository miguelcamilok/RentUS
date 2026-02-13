<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\RentalRequest;
use Illuminate\Http\Request;
use App\Models\User;

class OrmController extends Controller
{
    //

public function consulta(){
 
        $contracts = Contract::find(2); 
        return $contracts->property;

}

}