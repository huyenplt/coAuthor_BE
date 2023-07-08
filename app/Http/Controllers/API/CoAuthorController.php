<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\CoAuthor;
use Illuminate\Http\Request;
use App\Services\CoAuthorService;
use Illuminate\Support\Facades\DB;

class CoAuthorController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    private $coAuthorService;

    public function __construct(CoAuthorService $coAuthorService)
    {
        $this->coAuthorService = $coAuthorService;
    }


    public function index()
    {
        $coAuthors = CoAuthor::select('author_id1', 'author_id2', 'paper_id', 'paper_year')->get();
        return response()->json($coAuthors);
    }

    public function importCoAuthor()
    {
        $coAuthors = $this->coAuthorService->importCoAuthors();
        return response()->json($coAuthors);
    }

    public function getCandidates()
    {
        $candidates = Candidate::select('author_id1', 'author_id2', 'label', 'measure1', 'measure2', 'measure3', 'measure4')->get();
        return response()->json($candidates);
    }

    public function importCandidate($split_year)
    {
        $candidates = $this->coAuthorService->importCandidates($split_year);
        return response()->json($candidates);
    }

    public function labedCandidate($split_year)
    {
        $this->coAuthorService->labeledCandidates($split_year);
    }

    public function getMeasures()
    {
        $test = [];

        $candidates = Candidate::select('author_id1', 'author_id2')
            ->get();

        foreach ($candidates as $candidate) {
            $neighbor1 = Candidate::select('author_id2')
                ->where('author_id1', $candidate->author_id1)
                ->pluck('author_id2')
                ->toArray();

            $neighbor2 = Candidate::select('author_id2')
                ->where('author_id1', $candidate->author_id2)
                ->pluck('author_id2')
                ->toArray();
            set_time_limit(0);
            $commonElements = array_intersect($neighbor1, $neighbor2);

            $neighborMeasure = count($commonElements);
            array_push($test, $neighborMeasure);
        }

        return response()->json($test);
    }

    public function calculateMeasures(Request $request) {
        $data = $request->json()->all();
        $result = $this->coAuthorService->calculateMeasures($data);
        return response()->json($result);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\CoAuthor  $coAuthor
     * @return \Illuminate\Http\Response
     */
    public function show(CoAuthor $coAuthor)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\CoAuthor  $coAuthor
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, CoAuthor $coAuthor)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\CoAuthor  $coAuthor
     * @return \Illuminate\Http\Response
     */
    public function destroy(CoAuthor $coAuthor)
    {
        //
    }
}
