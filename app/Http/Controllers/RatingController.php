<?php

namespace App\Http\Controllers;

use App\Models\Rating;
use Illuminate\Http\Request;

class RatingController extends Controller
{
    public function index()
    {
        $ratings = Rating::included()->filter()->sort()->getOrPaginate();
        return response()->json($ratings);
    }

    public function show(Rating $rating)
    {
        //
    }

    public function edit(Rating $rating)
    {
        //
    }

    public function update(Request $request, Rating $rating)
    {
        //
    }

    public function destroy(Rating $rating)
    {
        //
    }
}
