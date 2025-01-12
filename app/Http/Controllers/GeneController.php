<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests\GeneListRequest;

use Auth;
use Session;
use Cookie;

use App\GeneLib;
use App\User;
use App\Gene;
use App\Panel;
use App\Nodal;
use App\Filter;
use App\Omim;
use App\Mim;
use App\Pmid;

/**
*
* @category   Web
* @package    Search
* @author     P. Weller <pweller1@geisinger.edu>
* @author     S. Goehringer <scottg@creationproject.com>
* @copyright  2020 ClinGen
* @license    http://www.php.net/license/3_01.txt  PHP License 3.01
* @version    Release: @package_version@
* @link       http://pear.php.net/package/PackageName
* @see        NetOther, Net_Sample::Net_Sample()
* @since      Class available since Release 1.2.0
*
* */
class GeneController extends Controller
{
	private $api = '/api/genes';
	private $api_curated = '/api/curations';
	private $user = null;

	protected $validity_sort_order = [
		'SEPIO:0004504' => 20,				// Definitive
		'SEPIO:0004505' => 19,				// Strong
		'SEPIO:0004506' => 18,				// Moderate
		'Supportive' => 17,					// Supportive
		'SEPIO:0004507' => 16,				// Limited
		'Animal Model Only' => 15,			// Animal Mode Only
		'SEPIO:0000404' => 14,				// Disputing
		'SEPIO:0004510' => 13,				// Refuted
		'SEPIO:0004508' => 12				// No Known Disease Relationship
	];

	protected $actionability_sort_value = [
		'Definitive Actionability' => 20,
		'Strong Actionability' => 19,
		'Moderate Actionability' => 18,
		'Limited Actionability' => 17,
		'Insufficient Actionability' => 16,
		'No Actionability' => 15,
		'Assertion Pending' => 14,
		'Has Insufficient Evidence for Actionability Based on Early Rule-out' => 13,
		'N/A - Insufficient evidence: expert review' => 12,
		'N/A - Insufficient evidence: early rule-out' => 11
   ];

	/**
	* Create a new controller instance.
	*
	* @return void
	*/
	public function __construct()
	{
		$this->middleware(function ($request, $next) {
            if (Auth::guard('api')->check())
                $this->user = Auth::guard('api')->user();
            return $next($request);
        });
	}


	/**
	* Display a listing of all genes.
	*
	* @return \Illuminate\Http\Response
	*/
	public function index(GeneListRequest $request, $page = 1, $size = 25, $search = "")
	{
		// process request args
		foreach ($request->only(['page', 'size', 'order', 'sort', 'search']) as $key => $value)
			$$key = $value;

		// set display context for view
        $display_tabs = collect([
            'active' => "gene",
            'title' => "Genes",
            'scrid' => Filter::SCREEN_ALL_GENES,
			'display' => "All Genes"
		]);

        if (Auth::guard('api')->check())
            $user = Auth::guard('api')->user();

        // get list of all current bookmarks for the page
        $bookmarks = ($this->user === null ? collect() : $this->user->filters()->screen(Filter::SCREEN_ALL_GENES)->get()->sortBy('name', SORT_STRING | SORT_FLAG_CASE));

        // get active bookmark, if any
        $filter = Filter::preferences($request, $this->user, Filter::SCREEN_ALL_GENES);

        if ($filter !== null && getType($filter) == "object" && get_class($filter) == "Illuminate\Http\RedirectResponse")
            return $filter;

        // don't apply global settings if local ones present
        $settings = Filter::parseSettings($request->fullUrl());

        if (empty($settings['size']))
            $display_list = ($this->user === null ? 25 : $this->user->preferences['display_list'] ?? 25);
        else
            $display_list = $settings['size'];

		return view('gene.index', compact('display_tabs'))
						->with('apiurl', $this->api)
						->with('pagesize', $size)
						->with('page', $page)
						->with('search', $search)
						->with('user', $this->user)
						->with('display_list', $display_list)
						->with('bookmarks', $bookmarks)
                        ->with('currentbookmark', $filter);
	}


	/**
	* Show all of the curated genes
	*
	* @return \Illuminate\Http\Response
	*/
	public function curated(GeneListRequest $request, $page = 1, $size = 50)
	{
		// process request args
		foreach ($request->only(['page', 'size', 'order', 'sort', 'search']) as $key => $value)
			$$key = $value;

		// set display context for view
        $display_tabs = collect([
            'active' => "gene-curations",
			'title' => "ClinGen Curated Genes",
			'scrid' => Filter::SCREEN_CURATED_GENES,
			'display' => "All Curated Genes"
        ]);

		// get list of all current bookmarks for the page
		$bookmarks = ($this->user === null ? collect() : $this->user->filters()->screen(Filter::SCREEN_CURATED_GENES)->get()->sortBy('name', SORT_STRING | SORT_FLAG_CASE));

        // get active bookmark, if any
        $filter = Filter::preferences($request, $this->user, Filter::SCREEN_CURATED_GENES);

        if ($filter !== null && getType($filter) == "object" && get_class($filter) == "Illuminate\Http\RedirectResponse")
            return $filter;

		// don't apply global settings if local ones present
		$settings = Filter::parseSettings($request->fullUrl());

        if (empty($settings['size']))
			$display_list = ($this->user === null ? 25 : $this->user->preferences['display_list'] ?? 25);
        else
            $display_list = $settings['size'];

		return view('gene.curated', compact('display_tabs'))
						->with('apiurl', $this->api_curated)
						->with('pagesize', $size)
						->with('page', $page)
						->with('user', $this->user)
						->with('display_list', $display_list)
						->with('bookmarks', $bookmarks)
                        ->with('currentbookmark', $filter);
	}


	/**
	* Display the specified gene, organized by disease.
	*
	* @param  int  $id
	* @return \Illuminate\Http\Response
	*/
	public function show_by_disease(Request $request, $id = null)
	{
		if ($id === null)
			return view('error.message-standard')
				->with('title', 'Error retrieving Gene details')
				->with('message', 'The system was not able to retrieve details for this Gene. Please return to the previous page and try again.')
				->with('back', url()->previous())
				->with('user', $this->user);

        $gene = Gene::rosetta($id);

        $mimflag = false;

        if ($gene === null && (stripos($id, 'OMIM:') === 0 ||stripos($id, 'MIM:') === 0))
        {
			$t = explode(':', $id);
            //$t = substr($id, 5 );
			if (isset($t[1]))
			{
				$mim = Mim::mim($t[1])->first();
				if ($mim !== null)
					$gene = $mim->gene;
				$mimflag = ($gene === null ? false : $t[1]);
			}
        }

        if ($gene === null || $gene->hgnc_id == null)
            return view('error.message-standard')
                    ->with('title', 'Error retrieving Gene details')
                    ->with('message', 'The system was not able to retrieve details for this Gene. Please return to the previous page and try again.')
                    ->with('back', url()->previous())
                    ->with('user', $this->user);

        $id = $gene->hgnc_id;

		$record = GeneLib::geneDetail([
										'gene' => $id,
										'curations' => true,
										'action_scores' => true,
										'validity' => true,
										'dosage' => true,
                                        'variant' => true
									]);

		if ($record === null)
			return view('error.message-standard')
						->with('title', 'Error retrieving Gene details')
						->with('message', 'The system was not able to retrieve details for this Gene.  Error message was: ' . GeneLib::getError() . '. Please return to the previous page and try again.')
						->with('back', url()->previous())
                        ->with('user', $this->user);

        // the new follow stuff.  protptype wip
		$follow = false;
		$email = '';
		$user = Auth::guard('api')->user();

		if (Auth::guard('api')->check())
		{
			$user = Auth::guard('api')->user();
			//dd($user);
			$follow = $user->genes->contains('hgnc_id', $id);
		}
		else
		{

			$cookie = $request->cookie('clingenfollow');

			if ($cookie !== null)
			{
				$user = User::cookie($cookie)->first();

				if ($user !== null)
				{
					$follow = $user->genes->contains('hgnc_id', $id);
					$email = $user->email;
				}
			}
		}
		// end follow

		$disease_collection = collect();
        $variant_collection = collect();
        $mims = [];
        $pmids = [];

		foreach ($record->genetic_conditions as $key => $disease)
		{
			$node = new Nodal([	'disease' => $disease->disease->label, 'validity' => null]);

			$validity_collection = collect();

			// validity
			foreach ($disease->gene_validity_assertions as $assertion)
			{
				$assertion_node = new Nodal(['order' => $this->validity_sort_order[$assertion->classification->curie] ?? 0,
									'disease' => $disease->disease, 'assertion' => $assertion]);
				$validity_collection->push($assertion_node);
                $mims = array_merge($mims, $assertion->las_included, $assertion->las_excluded);
                if (isset($assertion->las_rationale['pmids']))
                    $pmids = array_merge($pmids, $assertion->las_rationale['pmids']);
			}

			// reapply any sorting requirements
			if ($validity_collection->isNotEmpty())
				$node->validity = $validity_collection->sortByDesc('order');

			$disease_collection->push($node);

		}

        // get the mim names
        $mim_names = MIM::whereIn('mim', $mims)->get();

        $msave = $mims;
        $mims = [];

        foreach ($mim_names as $mim)
            $mims[$mim->mim] = $mim->title;

        foreach ($msave as $value)
        {
            if (!isset($mims[intval($value)]))
            {
                $omim = Omim::omimid($value)->first();

                if ($omim !== null)
                    $mims[$omim->omimid] = $omim->titles;
            }
        }

        // get the pmids
        $pmid_names = Pmid::whereIn('pmid', $pmids)->get();

        $pmids = [];

        foreach($pmid_names as $pmid)
            $pmids[$pmid->pmid] = ['title' => $pmid->sortfirstauthor . ', et al, ' . $pmid->pubdate . ', ' . $pmid->title,
                               //     'author' => $pmid->sortfirstauthor,
                                //    'published' =>  $pmid->pubdate,
                                    'abstract' => $pmid->abstract];
//dd($disease_collection);
		//dd($disease_collection->where('disease', $disease->disease->label)->first()->validity);

        if ($record->nvariant > 0)
			$variant_collection = collect($record->variant);

        // collect all the unique panels
        $variant_panels = [];
        $variant_collection->each(function ($item) use (&$variant_panels){
            $variant_panels = array_merge($variant_panels, array_column($item['panels'], 'affiliation'));
        });

        $variant_panels = array_unique($variant_panels);

		$vceps = Gene::hgnc($id)->first()->panels->where('type', PANEL::TYPE_VCEP);
		$gceps = Gene::hgnc($id)->first()->panels->where('type', PANEL::TYPE_GCEP);
        $pregceps = collect();

		if ($record->curation_status !== null)
		{
			foreach ($record->curation_status as $precuration)
			{
				if ($precuration['status'] == "Retired Assignment" || $precuration['status'] == "Published")
					continue;

				if (empty($precuration['group_id']))
					$panel = Panel::where('title_abbreviated', $precuration['group'])->first();
				else
					$panel = Panel::allids($precuration['group_id'])->first();

				if ($panel == null)
					continue;

                // blacklist panels we don't want displayed
                if ($panel->affiliate_id == "40018" || $panel->affiliate_id == "40019")
                    continue;

				$pregceps->push($panel);
			}

            $remids = $gceps->pluck('id');
			$pregceps = $pregceps->whereNotIn('id', $remids);
		}

		$pregceps = $pregceps->unique();

        $total_panels = /*$validity_eps + count($variant_panels)
                        + ($record->ndosage > 0 ? 1 : 0)
                        + ($actionability_collection->isEmpty() ? 0 : 1)
                        + */ count($pregceps);

		// set display context for view
		$display_tabs = collect([
			'active' => "gene",
			'title' => $record->label . " curation results"
		]);
//dd($variant_collection);
        return view('gene.by-disease', compact('display_tabs', 'record', 'follow', 'email', 'user',
                        'pmids', 'mimflag', 'mims',
                         'disease_collection', 'total_panels', 'variant_collection'))
						->with('user', $this->user);;
	}


	/**
	* Display the specified gene, organized by condition.
	*
	* @param  int  $id
	* @return \Illuminate\Http\Response
	*/
	public function show_by_activity(Request $request, $id = null)
	{
		if ($id === null)
			return view('error.message-standard')
						->with('title', 'Error retrieving Gene details')
						->with('message', 'The system was not able to retrieve details for this Gene. Please return to the previous page and try again.')
						->with('back', url()->previous())
						->with('user', $this->user);

        $gene = Gene::rosetta($id);

		$mimflag = false;

        if ($gene === null && (stripos($id, 'OMIM:') === 0 ||stripos($id, 'MIM:') === 0))
        {
			$t = explode(':', $id);
            //$t = substr($id, 5 );
			if (isset($t[1]))
			{
				$mim = Mim::mim($t[1])->first();
				if ($mim !== null)
					$gene = $mim->gene;
				$mimflag = ($gene === null ? false : $t[1]);
			}
        }

        if ($gene === null || $gene->hgnc_id === null)
            return view('error.message-standard')
                    ->with('title', 'Error retrieving Gene details')
                    ->with('message', 'The system was not able to retrieve details for this Gene. Please return to the previous page and try again.')
                    ->with('back', url()->previous())
                    ->with('user', $this->user);

        $id = $gene->hgnc_id;

		$record = GeneLib::geneDetail([
									'gene' => $id,
									'curations' => true,
									'action_scores' => true,
									'validity' => true,
									'dosage' => true,
									'pharma' => true,
									'variant' => true
								]);

		if ($record === null)
			return view('error.message-standard')
						->with('title', 'Error retrieving Gene details')
						->with('message', 'The system was not able to retrieve details for this Gene.  Error message was: ' . GeneLib::getError() . '. Please return to the previous page and try again.')
						->with('back', url()->previous())
						->with('user', $this->user);

		// the new follow stuff.  protptype wip
		$follow = false;
		$email = '';
		$user = Auth::guard('api')->user();

		if (Auth::guard('api')->check())
		{
			$user = Auth::guard('api')->user();
			//dd($user);
			$follow = $user->genes->contains('hgnc_id', $id);
		}
		else
		{

			$cookie = $request->cookie('clingenfollow');

			if ($cookie !== null)
			{
				$user = User::cookie($cookie)->first();

				if ($user !== null)
				{
					$follow = $user->genes->contains('hgnc_id', $id);
					$email = $user->email;
				}
			}
		}
		// end follow

		//reformat the response structure for view by activity
		$actionability_collection = collect();
		$validity_collection = collect();
		$dosage_collection = collect();
		$variant_collection = collect();
		$pharma_collection = collect();
        // mim st
        $mims = [];
        $pmids = [];
        $key = 0;


		foreach ($record->genetic_conditions as $key => $disease)
		{
			// actionability
			if (!empty($disease->actionability_assertions))
			{
				$adult = null;
				$pediatric = null;
				$order = 0;
                $reports = [];

				// regroup the adult and pediatric assertions
				foreach ($disease->actionability_assertions as $assertion)
				{
                    if ($assertion->attributed_to !== null)
                    {
                        if ($assertion->attributed_to->label == "Adult Actionability Working Group")
                        {
                            $label = $assertion->report_label;
                            if (!array_key_exists($label, $reports))
                                $reports[$label] = ['adult' => null, 'pediatric' => null];
                            $reports[$label]['adult'] = $assertion;
                            $adult = $assertion;
                        }
                        if ($assertion->attributed_to->label == "Pediatric Actionability Working Group")
                        {
                            $label = $assertion->report_label;
                            if (!array_key_exists($label, $reports))
                                $reports[$label] = ['adult' => null, 'pediatric' => null];
                            $reports[$label]['pediatric'] = $assertion;
                            $pediatric = $assertion;
                        }
                    }
                    else{
                        // workaround for genegraph bug 5/11/2021
                        if (strpos($assertion->source, "Adult") !== false)
                        {
                            $label = $assertion->report_label;
                            if (!array_key_exists($label, $reports))
                                $reports[$label] = ['adult' => null, 'pediatric' => null];
                            $reports[$label]['adult'] = $assertion;
                            $adult = $assertion;
                        }
                        if (strpos($assertion->source, "Pediatric") !== false)
                        {
                            $label = $assertion->report_label;
                            if (!array_key_exists($label, $reports))
                                $reports[$label] = ['adult' => null, 'pediatric' => null];
                            $reports[$label]['pediatric'] = $assertion;
                            $pediatric = $assertion;
                        }

                    }

					$aorder = $this->actionability_sort_order[$assertion->classification->label] ?? 0;
					if ($aorder > $order)
						$order = $aorder;
				}

				$node = new Nodal([	'order' => $order,
										'disease' => $disease->disease, 'adult_assertion' => $adult,
										'pediatric_assertion' => $pediatric,
                                        'reports' => $reports ]);

				$actionability_collection->push($node);
			}

			// validity
			foreach ($disease->gene_validity_assertions as $assertion)
			{
				$node = new Nodal([	'order' => $this->validity_sort_order[$assertion->classification->curie] ?? 0,
									'disease' => $disease->disease, 'assertion' => $assertion, 'key' => $key++]);

				$validity_collection->push($node);
                $mims = array_merge($mims, $assertion->las_included, $assertion->las_excluded);
                if (isset($assertion->las_rationale['pmids']))
                    $pmids = array_merge($pmids, $assertion->las_rationale['pmids']);
			}

			// dosage
			foreach ($disease->gene_dosage_assertions as $assertion)
			{
				$node = new Nodal([	'order' => $assertion->dosage_classification->oridinal ?? 0,
									'disease' => $disease->disease, 'assertion' => $assertion]);
				$dosage_collection->push($node);
			}
		}
       // dd($actionability_collection);
        // get the mim names
        $mim_names = MIM::whereIn('mim', $mims)->get();

        $msave = $mims;
        $mims = [];

        foreach ($mim_names as $mim)
            $mims[$mim->mim] = $mim->title;

        foreach ($msave as $value)
        {
            if (!isset($mims[intval($value)]))
            {
                $omim = Omim::omimid($value)->first();

                if ($omim !== null)
                    $mims[$omim->omimid] = $omim->titles;
            }
        }

        // get the pmids
        $pmid_names = Pmid::whereIn('pmid', $pmids)->get();

        $pmids = [];

        foreach($pmid_names as $pmid)
            $pmids[$pmid->pmid] = ['title' => $pmid->sortfirstauthor . ', et al, ' . $pmid->pubdate . ', ' . $pmid->title,
                               //     'author' => $pmid->sortfirstauthor,
                                //    'published' =>  $pmid->pubdate,
                                    'abstract' => $pmid->abstract];

        //dd($pmids);

		// reapply any sorting requirements
		$validity_collection = $validity_collection->sortByDesc('order');

        $validity_panels = [];
        $validity_collection->each(function ($item) use (&$validity_panels){
            if (!in_array($item->assertion->attributed_to->label, $validity_panels))
                array_push($validity_panels, $item->assertion->attributed_to->label);
        });

        $validity_eps = count($validity_panels);
		$actionability_collection = $actionability_collection->sortByDesc('order');
//dd($validity_collection);
		if ($record->nvariant > 0)
			$variant_collection = collect($record->variant);
//dd($variant_collection);
        // collect all the unique panels
        $variant_panels = [];
        $variant_collection->each(function ($item) use (&$variant_panels){
            $variant_panels = array_merge($variant_panels, array_column($item['panels'], 'affiliation'));
        });

        $variant_panels = array_unique($variant_panels);

		$vceps = Gene::hgnc($id)->first()->panels->where('type', PANEL::TYPE_VCEP);
		$gceps = Gene::hgnc($id)->first()->panels->where('type', PANEL::TYPE_GCEP);
        $pregceps = collect();

		if ($record->curation_status !== null)
		{
			foreach ($record->curation_status as $precuration)
			{
				if ($precuration['status'] == "Retired Assignment" || $precuration['status'] == "Published")
					continue;

				if (empty($precuration['group_id']))
					$panel = Panel::where('title_abbreviated', $precuration['group'])->first();
				else
					$panel = Panel::allids($precuration['group_id'])->first();

				if ($panel == null)
					continue;

                // blacklist panels we don't want displayed
                if ($panel->affiliate_id == "40018" || $panel->affiliate_id == "40019")
                    continue;

				$pregceps->push($panel);
			}

            $remids = $gceps->pluck('id');
			$pregceps = $pregceps->whereNotIn('id', $remids);
		}

		$pregceps = $pregceps->unique();

        $total_panels = /*$validity_eps + count($variant_panels)
                        + ($record->ndosage > 0 ? 1 : 0)
                        + ($actionability_collection->isEmpty() ? 0 : 1)
                        + */ count($pregceps);

		//dd($record->curation_status);
		// set display context for view
		$display_tabs = collect([
			'active' => "gene",
			'title' => $record->label . " curation results"
		]);

        //dd($record);
        //dd($variant_collection);
		return view('gene.by-activity', compact('display_tabs', 'record', 'follow', 'email', 'user',
												'validity_collection', 'actionability_collection', 'pmids',
												'variant_collection', 'validity_eps', 'variant_panels',
                                                'pregceps', 'total_panels', 'mimflag', 'mims',
            'vceps',
			'gceps'))
												->with('user', $this->user);
	}



	/**
	 * Display the specified gene, organized by condition.
	 *
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function show_groups(Request $request, $id = null)
	{
		if ($id === null)
			return view('error.message-standard')
			->with('title', 'Error retrieving Gene details')
			->with('message', 'The system was not able to retrieve details for this Gene. Please return to the previous page and try again.')
			->with('back', url()->previous())
				->with('user', $this->user);

		// check if the condition came in as an OMIM ID, and if so convert it.
		if (strpos($id, "HGNC:") !== 0) {
			if (is_numeric($id))
				$check = Gene::omim($id)->first();
			else
				$check = Gene::name($id)->first();

			if ($check !== null)
				$id = $check->hgnc_id;
		}

		$record = GeneLib::geneDetail([
			'gene' => $id,
			'curations' => true,
			'action_scores' => true,
			'validity' => true,
			'dosage' => true,
			'pharma' => true,
			'variant' => true
		]);

		if ($record === null)
			return view('error.message-standard')
			->with('title', 'Error retrieving Gene details')
			->with('message', 'The system was not able to retrieve details for this Gene.  Error message was: ' . GeneLib::getError() . '. Please return to the previous page and try again.')
			->with('back', url()->previous())
				->with('user', $this->user);

		// the new follow stuff.  protptype wip
		$follow = false;
		$email = '';
		$user = Auth::guard('api')->user();

		if (Auth::guard('api')->check()) {
			$user = Auth::guard('api')->user();
			//dd($user);
			$follow = $user->genes->contains('hgnc_id', $id);
		} else {

			$cookie = $request->cookie('clingenfollow');

			if ($cookie !== null) {
				$user = User::cookie($cookie)->first();

				if ($user !== null) {
					$follow = $user->genes->contains('hgnc_id', $id);
					$email = $user->email;
				}
			}
		}
		// end follow

		//reformat the response structure for view by activity
		$actionability_collection = collect();
		$validity_collection = collect();
		$dosage_collection = collect();
		$variant_collection = collect();
		$pharma_collection = collect();

		foreach ($record->genetic_conditions as $key => $disease) {
			// actionability
			if (!empty($disease->actionability_assertions)) {
				$adult = null;
				$pediatric = null;
				$order = 0;

				// regroup the adult and pediatric assertions
				foreach ($disease->actionability_assertions as $assertion) {
					if ($assertion->attributed_to !== null) {
						if ($assertion->attributed_to->label == "Adult Actionability Working Group") {
							$adult = $assertion;
						}
						if ($assertion->attributed_to->label == "Pediatric Actionability Working Group") {
							$pediatric = $assertion;
						}
					} else {
						// workaround for genegraph bug 5/11/2021
						if (strpos($assertion->source, "Adult") !== false) {
							$adult = $assertion;
						}
						if (strpos($assertion->source, "Pediatric") !== false) {
							$pediatric = $assertion;
						}
					}

					$aorder = $this->actionability_sort_order[$assertion->classification->label] ?? 0;
					if ($aorder > $order)
						$order = $aorder;
				}

				$node = new Nodal([
					'order' => $order,
					'disease' => $disease->disease,
					'adult_assertion' => $adult,
					'pediatric_assertion' => $pediatric
				]);

				$actionability_collection->push($node);
			}
			//dd($actionability_collection);

			// validity
			foreach ($disease->gene_validity_assertions as $assertion) {
				$node = new Nodal([
					'order' => $this->validity_sort_order[$assertion->classification->curie] ?? 0,
					'disease' => $disease->disease, 'assertion' => $assertion
				]);
				$validity_collection->push($node);
			}

			// dosage
			foreach ($disease->gene_dosage_assertions as $assertion) {
				$node = new Nodal([
					'order' => $assertion->dosage_classification->oridinal ?? 0,
					'disease' => $disease->disease, 'assertion' => $assertion
				]);
				$dosage_collection->push($node);
			}
		}

		// reapply any sorting requirements
        $validity_collection = $validity_collection->sortByDesc('order');

        $validity_panels = [];
        $validity_collection->each(function ($item) use (&$validity_panels){
            if (!in_array($item->assertion->attributed_to->label, $validity_panels))
                array_push($validity_panels, $item->assertion->attributed_to->label);
        });

        $validity_eps = count($validity_panels);
		$actionability_collection = $actionability_collection->sortByDesc('order');

		if ($record->nvariant > 0)
			$variant_collection = collect($record->variant);

        // collect all the unique panels
        $variant_panels = [];
        $variant_collection->each(function ($item) use (&$variant_panels){
            $variant_panels = array_merge($variant_panels, array_column($item['panels'], 'affiliation'));
        });

        $variant_panels = array_unique($variant_panels);

		$vceps = Gene::hgnc($id)->first()->panels->where('type', PANEL::TYPE_VCEP);
		$gceps = Gene::hgnc($id)->first()->panels->where('type', PANEL::TYPE_GCEP);
        $pregceps = collect();
//dd($record);
		if ($record->curation_status !== null)
		{
			foreach ($record->curation_status as $precuration)
			{
                switch ($precuration['status'])
				{
					case 'Uploaded':
                        $bucket = 3;
                        break;
                    case "Precuration":
                    case "Disease Entity Assigned":
                    case "Disease entity assigned":
                    case "Precuration Complete":
                        $bucket = 1;
                        break;
                    case "Curation Provisional":
                    case "Curation Approved":
                        $bucket = 2;
                        break;
                    case "Retired Assignment":
                    case "Published":
                    default:
                        continue 2;
				}
//dd($precuration);
				//if ($precuration['status'] == "Retired Assignment" || $precuration['status'] == "Published")
				//	continue;

				if (empty($precuration['group_id']))
					$panel = Panel::where('title_abbreviated', $precuration['group'])->first();
				else
					$panel = Panel::allids($precuration['group_id'])->first();

				if ($panel == null)
					continue;

                // blacklist panels we don't want displayed
                if ($panel->affiliate_id == "40018" || $panel->affiliate_id == "40019")
                    continue;
//dd($bucket);
                $panel->bucket = $bucket;

				$pregceps->push($panel);
			}

            $remids = $gceps->pluck('id');
			$pregceps = $pregceps->whereNotIn('id', $remids);
		}

		$pregceps = $pregceps->unique();

		// set display context for view
		$display_tabs = collect([
			'active' => "gene",
			'title' => $record->label . " curation results"
		]);

        $total_panels = /*$validity_eps + count($variant_panels)
                        + ($record->ndosage > 0 ? 1 : 0)
                        + ($actionability_collection->isEmpty() ? 0 : 1)
                        +*/ count($pregceps);

		return view('gene.show-groups', compact(
			'display_tabs',
			'record',
			'follow',
			'email',
			'user',
			'validity_collection',
			'actionability_collection',
			'variant_collection',
            'variant_panels',
			//'group_collection',
			'gceps',
			'vceps',
            'pregceps',
            'total_panels'
		))
			->with('user', $this->user);
	}


	/**
	 * Display the external resources section.  Since this is mostly static
	 * it wuld just get displayed in a tab with the gene.
	 *
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function external(Request $request, $id = null)
	{
		if ($id === null)
			return view('error.message-standard')
				->with('title', 'Error retrieving Gene details')
				->with('message', 'The system was not able to retrieve details for this Gene. Please return to the previous page and try again.')
				->with('back', url()->previous())
				->with('user', $this->user);


		$record = GeneLib::geneDetail([
										'gene' => $id,
										'curations' => true,
										'action_scores' => true,
										'validity' => true,
										'dosage' => true
									]);

		if ($record === null)
			return view('error.message-standard')
						->with('title', 'Error retrieving Gene details')
						->with('message', 'The system was not able to retrieve details for this Gene.  Error message was: ' . GeneLib::getError() . '. Please return to the previous page and try again.')
						->with('back', url()->previous())
                        ->with('user', $this->user);

		// set display context for view
		$display_tabs = collect([
			'active' => "gene",
			'title' => $record->label . " external resources"
		]);

		return view('gene.show-external-resources', compact('display_tabs', 'record'))
						->with('user', $this->user);
	}


	/**
	* Display a listing of all genes.
	*
	* @return \Illuminate\Http\Response
	*/
	public function search(Request $request)
	{

		// process request args
		foreach ($request->only(['search']) as $key => $value)
			$$key = $value;

		// the way layouts is set up, everything is named search.  Gene is the first

		return redirect()->route('gene-index', ['page' => 1, 'size' => 50, 'search' => $search[0] ]);
	}
}
