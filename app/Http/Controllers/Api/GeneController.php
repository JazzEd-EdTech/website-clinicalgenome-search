<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Resources\Gene as GeneResource;

use App\GeneLib;

class GeneController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $input = $request->only(['search', 'order', 'offset', 'limit']);

        $results = GeneLib::geneList([	'page' => $input['offset'] ?? 0,
										'pagesize' => $input['limit'] ?? "null",
										'sort' => $sort ?? 'GENE_LABEL',
                                        'direction' => $input['order'] ?? 'ASC',
                                        'search' => $input['search'] ?? null,
                                        'curated' => false ]);
                                        
		if ($results === null)
			die(print_r(GeneLib::getError()));

        return ['total' => $results->count, 
                'totalNotFiltered' => $results->count,
                'rows'=> GeneResource::collection($results->collection)];
    }
}