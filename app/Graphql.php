<?php

namespace App;

use Illuminate\Support\Facades\Log;

use App\Traits\Query;

use App\Metric;
use App\Jira;
use App\Variant;
use App\Cpic;

use Carbon\Carbon;

/**
 *
 * @category   Library
 * @package    Search
 * @author     P. Weller <pweller1@geisinger.edu>
 * @author     S. Goehringer <scottg@creationproject.com>
 * @copyright  2020 ClinGen
 * @license    http://www.php.net/license/3_01.txt  PHP License 3.01
 * @version    Release: @package_version@
 * @link       http://pear.php.net/package/PackageName
 * @see        NetOther, Net_Sample::Net_Sample()
 * @since      Class available since Release 1.0.0
 *
 * */
class Graphql
{
	use Query;

	protected static $prefix = "https://search.clinicalgenome.org/kb/agents/";

	/**
     * This class is designed to be used statically.
     */


    /**
     * Get gene list with curation flags and last update
     *
     * @return Illuminate\Database\Eloquent\Collection
     */
    static function geneList($args, $curated = false, $page = 0, $pagesize = 20000)
    {
		// break out the args
		foreach ($args as $key => $value)
			$$key = $value;

		// initialize the collection
		$collection = collect();

		// set up query for either all genes or just curated ones
		if ($curated === true)
		{
			// note:  we don't currently use last_curated_date
			$query = '{
					genes('
					. self::optionList($page, $pagesize, $sort, $direction, $search, 'ALL')
					. ') {
						count
						gene_list {
							label
							hgnc_id
							last_curated_date
							curation_activities
							dosage_curation {
								triplosensitivity_assertion {
									dosage_classification {
										ordinal
									  }
								}
								haploinsufficiency_assertion {
									dosage_classification {
										ordinal
									  }
								}
							}
						}
					}
				}';
		}
		else
		{
			/*
			$genes = Gene::get(['name as symbol', 'description as name', 'hgnc_id', 'date_last_curated as last_curated_date', 'activity as curation_activities']);
			// add each gene to the collection


				$naction = 0;
			$nvalid = 0;
			$ndosage = 0;

		return (object) ['count' => $genes->count(), 'collection' => $genes,
						'naction' => $naction, 'nvalid' => $nvalid, 'ndosage' => $ndosage];
*/
			$query = '{
					genes('
					. self::optionList($page, $pagesize, $sort, $direction, $search, $curated)
					. ') {
						count
						gene_list {
							label
							alternative_label
							hgnc_id
							last_curated_date
							curation_activities
						}
					}
				}';
		}

		/*
		type {
			label
			curie
		}*/

		// query genegraph
		$response = self::query($query, __METHOD__);

		if (empty($response))
			return $response;

		// get the list of acmg59 genes
		$acmg59s = Gene::select('hgnc_id')->where('acmg59', 1)->get()->pluck('hgnc_id')->toArray();

		// get list of pharma and variant pathogenicity genes
		$extras = Gene::select('name', 'hgnc_id', 'acmg59', 'activity')->where('has_varpath', 1)->orWhere('has_pharma', 1)->get();
		
		// build list of genes not known by genegraph
		$excludes = [];

		// create node list and add pharma and variant curation indicators to the current gene list
		foreach($response->genes->gene_list as $record)
		{
			$node = new Nodal((array) $record);
			$extra = $extras->where('hgnc_id', $node->hgnc_id)->first();
			if ($extra !== null)
			{
				$t = $node->curation_activities;
				if (!empty($extra->activity["pharma"]))
					array_push($t, "GENE_PHARMA");
				if (!empty($extra->activity["varpath"]))
					array_push($t, "VAR_PATH");
				$node->curation_activities = $t;
				$excludes[] = $node->hgnc_id;
			}

			$node->acmg59 = in_array($node->hgnc_id, $acmg59s);

			$collection->push($node);
		}

		// add genes not tagged by genegraph
		foreach($extras as $extra)
		{
			if (in_array($extra->hgnc_id, $excludes))
				continue;

			$t = [];
			if (!empty($extra->activity["pharma"]))
				array_push($t, "GENE_PHARMA");
			if (!empty($extra->activity["varpath"]))
				array_push($t, "VAR_PATH");

			$node = new Nodal(['label' => $extra->name, 'hgnc_id' => $extra->hgnc_id, 'curation_activities' => $t]);
			
			$node->acmg59 = in_array($node->hgnc_id, $acmg59s);
			
			$collection->push($node);
		}

		if ($curated)
		{
			$naction = $collection->where('has_actionability', true)->count();
			$nvalid = $collection->where('has_validity', true)->count();
			$ndosage = $collection->where('has_dosage', true)->count();
			$npharma = $collection->where('has_pharma', true)->count();
			$nvariant = $collection->where('has_variant', true)->count();;
		}
		else
		{
			// right now we only use these counts on the curated page.  Probably should get triggered
			// by a call option so as not to bury things to deep.
			$naction = 0;
			$nvalid = 0;
			$ndosage = 0;
			$npharma = 0;
			$nvariant = 0;
		}

		return (object) ['count' => $collection->count(), 	//$response->genes->count,
						'collection' => $collection,
						'naction' => $naction, 'nvalid' => $nvalid, 'ndosage' => $ndosage,
						'nvariant' => $nvariant, 'npharma' => $npharma];
	}


	/**
     * Get details of a specific gene
     *
     * @return Illuminate\Database\Eloquent\Collection
     */
    static function geneDetail($args, $page = 0, $pagesize = 20)
    {
		// break out the args
		foreach ($args as $key => $value)
			$$key = $value;

		// set up query for gene details
		$query = '{
				gene('
				. 'iri: "' . $gene
				. '") {
					label
					alternative_label
					hgnc_id
					chromosome_band
					curation_activities
					last_curated_date
					dosage_curation {
						curie
						report_date
						triplosensitivity_assertion {
							dosage_classification {
								ordinal
							  }

						}
						haploinsufficiency_assertion {
							dosage_classification {
								ordinal
							  }

						}
					}
					genetic_conditions {
						disease {
						  label
						  iri
						}
						gene_validity_assertions {
						  mode_of_inheritance {
							  label
							  curie
						  }
						  report_date
						  classification {
							  label
							  curie
						  }
						  curie
						}
						actionability_curations {
						  report_date
						  source
						}
						gene_dosage_assertions {
						  report_date
						  assertion_type
						  dosage_classification {
							ordinal
							}
						  curie
						}
					}
				}
			}';

		// query genegraph
		$response = self::query($query,  __METHOD__);

		if (empty($response))
			return $response;

		$node = new Nodal((array) $response->gene);

		// add additional information from local db
		$localgene = Gene::where('hgnc_id', $gene)->first();

		if ($localgene !== null)
		{
			$node->alias_symbols = $localgene->display_aliases;
			$node->prev_symbols = $localgene->display_previous;
			$node->hi = round($localgene->hi, 2);
			$node->pli = round($localgene->pli, 2);
			$node->plof = round($localgene->plof, 2);
			$node->locus_type = $localgene->locus_type;
			$node->locus_group = $localgene->locus_group;
			$node->ensembl_id = $localgene->ensembl_gene_id;
			$node->entrez_id = $localgene->entrez_id;
			$node->omim_id = $localgene->omim_id;
			$node->ucsc_id = $localgene->ucsc_id;
			$node->uniprot_id = $localgene->uniprot_id;
			$node->function = $localgene->function;
			$node->grch37 = $localgene->grch37;
			$node->grch38 = $localgene->grch38;
			$node->GRCh37_seqid = $localgene->seqid37;
			$node->GRCh38_seqid = $localgene->seqid38;
			$node->mane_select = $localgene->mane_select;
			$node->mane_plus = $localgene->mane_plus;
		}

		$naction = 0;
		$nvalid = 0;
		$ndosage = 0;
		// currently, there is no easy way to track what needs dosage_curation entries belong in
		// the catch all, so we need to process the genetic conditions and add some flags.
		$dosage_curation_map = ["haploinsufficiency_assertion" => true, "triplosensitivity_assertion" => true];

		if (empty($node->dosage_curation->triplosensitivity_assertion))
			unset($dosage_curation_map["triplosensitivity_assertion"]);

		if (empty($node->dosage_curation->haploinsufficiency_assertion))
			unset($dosage_curation_map["haploinsufficiency_assertion"]);

		if (!empty($node->genetic_conditions))
		{
			foreach($node->genetic_conditions as $condition)
			{
				//$nodeCollect = collect($node);
				//dd($nodeCollect);
				//dd(count($condition->gene_validity_assertions));
				$naction = $naction + count($condition->actionability_curations);
				$nvalid = $nvalid + count($condition->gene_validity_assertions);
				$ndosage = $ndosage + count($condition->gene_dosage_assertions);

				//dd($naction);
				//dd($nvalid);
				//dd($ndosage);
				foreach($condition->gene_dosage_assertions as $dosage)
				{
					switch ($dosage->assertion_type)
					{
						case "HAPLOINSUFFICIENCY_ASSERTION":
							unset($dosage_curation_map["haploinsufficiency_assertion"]);
							break;
						case "TRIPLOSENSITIVITY_ASSERTION":
							unset($dosage_curation_map["triplosensitivity_assertion"]);
							break;
					}
				}
			}
		}

		if ($ndosage == 0 && (!empty($dosage_curation_map["haploinsufficiency_assertion"]) || !empty($dosage_curation_map["triplosensitivity_assertion"])))
			$ndosage++;

		// $by_activity = ['gene_validity' => [], 'dosage_curation' => [], 'actionability' => []];
		// 	if (!empty($node->genetic_conditions))
		// 	{
		// 		//dd($node->genetic_conditions);
		// 		$i = -1;
		// 		foreach($node->genetic_conditions as $genetic_condition) {
		// 			$i++;
		// 			$ii = -1;
		// 			foreach ($genetic_condition->gene_validity_assertions as $gene_validity_assertion) {
		// 				$ii++;
		// 				$curie = explode("/", $genetic_condition->disease->iri);
		// 				$by_activity['gene_validity'][end($curie)][$ii]['disease'] = $genetic_condition->disease;
		// 				$by_activity['gene_validity'][end($curie)][$ii]['curation'] = $gene_validity_assertion;
		// 			}
		// 			$ii = -1;
		// 			foreach ($genetic_condition->gene_dosage_assertions as $gene_dosage_assertion) {
		// 				$ii++;
		// 				$curie = explode("/", $genetic_condition->disease->iri);
		// 				$by_activity['dosage_curation'][end($curie)][$ii]['disease'] = $genetic_condition->disease;
		// 				$by_activity['dosage_curation'][end($curie)][$ii]['curation'] = $gene_dosage_assertion;
		// 			}
		// 			$ii = -1;
		// 			foreach ($genetic_condition->actionability_curations as $actionability_curation) {
		// 				$ii++;
		// 				$curie = explode("/", $genetic_condition->disease->iri);
		// 				$by_activity['actionability'][end($curie)][$ii]['disease'] = $genetic_condition->disease;
		// 				$by_activity['actionability'][end($curie)][$ii]['curation'] = $actionability_curation;
		// 			}
		// 			//$i++;
		// 			//$curations_by_activity[$i]	=	$by_activity;
		// 		}
		// 		$ii++;
		// 		if($node->dosage_curation){
		// 			$by_activity['dosage_curation']['null'][$ii]['curation'] = $node->dosage_curation;
		// 		}


		// 	} elseif ($node->dosage_curation) {
		// 		$by_activity 							= [];
		// 		$by_activity['dosage_curation']['null'][0]['curation'] = $node->dosage_curation;
		// 	}
		// 	//dd($by_activity);
		// 	$curations_by_activity = json_decode(json_encode($by_activity));
		// 	//dd($curations_by_activity);
		// 	$node->curations_by_activity = $curations_by_activity;


		$node->naction = $naction;
		$node->nvalid = $nvalid;
		$node->ndosage = $ndosage;

		//dd($node);

		if (!empty($pharma))
		{
			$entries = Cpic::gene($node->label)->get();
			$node->pharma = $entries->toArray();
		}




		$node->dosage_curation_map = $dosage_curation_map;

		//dd($node);
		return $node;
	}


	/**
     * Get details of a specific gene by activity
     *
     * @return Illuminate\Database\Eloquent\Collection
     */
    static function geneActivityDetail($args, $page = 0, $pagesize = 20)
    {
		// break out the args
		foreach ($args as $key => $value)
			$$key = $value;

		// set up query for gene details
		$query = '{
				gene('
				. 'iri: "' . $gene
				. '") {
					label
					alternative_label
					hgnc_id
					chromosome_band
					curation_activities
					last_curated_date
					dosage_curation {
						curie
						report_date
						triplosensitivity_assertion {
							dosage_classification {
								ordinal
							  }

						}
						haploinsufficiency_assertion {
							dosage_classification {
								ordinal
							  }

						}
					}
					genetic_conditions {
						disease {
						  label
						  iri
						}
						gene_validity_assertions {
						  mode_of_inheritance {
							  label
							  curie
						  }
						  report_date
						  classification {
							  label
							  curie
						  }
						  curie
						}
						actionability_curations {
						  report_date
						  source
						}
						gene_dosage_assertions {
						  report_date
						  assertion_type
						  dosage_classification {
							ordinal
							}
						  curie
						}
					}
				}
			}';

		// query genegraph
		$response = self::query($query,  __METHOD__);

		if (empty($response))
			return $response;

		$node = new Nodal((array) $response->gene);

		// add additional information from local db
		$localgene = Gene::where('hgnc_id', $gene)->first();
		if ($localgene !== null)
		{
			$node->alias_symbols = $localgene->display_aliases;
			$node->prev_symbols = $localgene->display_previous;
			$node->hi = round($localgene->hi, 2);
			$node->pli = round($localgene->pli, 2);
			$node->plof = round($localgene->plof, 2);
			$node->locus_type = $localgene->locus_type;
			$node->locus_group = $localgene->locus_group;
			$node->ensembl_id = $localgene->ensembl_gene_id;
			$node->entrez_id = $localgene->entrez_id;
			$node->omim_id = $localgene->omim_id;
			$node->ucsc_id = $localgene->ucsc_id;
			$node->uniprot_id = $localgene->uniprot_id;
			$node->function = $localgene->function;
		}

		// currently, there is no easy way to track what needs dosage_curation entries belong in
		// the catch all, so we need to process the genetic conditions and add some flags.
		$dosage_curation_map = ["haploinsufficiency_assertion" => true, "triplosensitivity_assertion" => true];

		if (empty($node->dosage_curation->triplosensitivity_assertion))
			unset($dosage_curation_map["triplosensitivity_assertion"]);

		if (empty($node->dosage_curation->haploinsufficiency_assertion))
			unset($dosage_curation_map["haploinsufficiency_assertion"]);

		if (!empty($node->genetic_conditions))
		{
			foreach($node->genetic_conditions as $condition)
			{
				foreach($condition->gene_dosage_assertions as $dosage)
				{
					switch ($dosage->assertion_type)
					{
						case "HAPLOINSUFFICIENCY_ASSERTION":
							unset($dosage_curation_map["haploinsufficiency_assertion"]);
							break;
						case "TRIPLOSENSITIVITY_ASSERTION":
							unset($dosage_curation_map["triplosensitivity_assertion"]);
							break;
					}
				}
			}
		}
		//dd($node);
		$by_activity = ['gene_validity' => [], 'dosage_curation' => [], 'actionability' => []];
			if (!empty($node->genetic_conditions))
			{
				//dd($node->genetic_conditions);
				$i = -1;
				foreach($node->genetic_conditions as $genetic_condition) {
					$i++;
					$ii = -1;
					foreach ($genetic_condition->gene_validity_assertions as $gene_validity_assertion) {
						$ii++;
						$curie = explode("/", $genetic_condition->disease->iri);
						$by_activity['gene_validity'][end($curie)][$ii]['disease'] = $genetic_condition->disease;
						$by_activity['gene_validity'][end($curie)][$ii]['curation'] = $gene_validity_assertion;
					}
					$ii = -1;
					foreach ($genetic_condition->gene_dosage_assertions as $gene_dosage_assertion) {
						$ii++;
						$curie = explode("/", $genetic_condition->disease->iri);
						$by_activity['dosage_curation'][end($curie)][$ii]['disease'] = $genetic_condition->disease;
						$by_activity['dosage_curation'][end($curie)][$ii]['curation'] = $gene_dosage_assertion;
					}
					$ii = -1;
					foreach ($genetic_condition->actionability_curations as $actionability_curation) {
						$ii++;
						$curie = explode("/", $genetic_condition->disease->iri);
						$by_activity['actionability'][end($curie)][$ii]['disease'] = $genetic_condition->disease;
						$by_activity['actionability'][end($curie)][$ii]['curation'] = $actionability_curation;
					}
					//$i++;
					//$curations_by_activity[$i]	=	$by_activity;
				}
				$ii++;
				if($node->dosage_curation){
					$by_activity['dosage_curation']['null'][$ii]['curation'] = $node->dosage_curation;
				}


			} elseif ($node->dosage_curation) {
				$by_activity 							= [];
				$by_activity['dosage_curation']['null'][0]['curation'] = $node->dosage_curation;
			}
			//dd($by_activity);
			$curations_by_activity = json_decode(json_encode($by_activity));
			//dd($curations_by_activity);
			$node->curations_by_activity = $curations_by_activity;


		$node->dosage_curation_map = $dosage_curation_map;

		//dd($node);
		return $node;
	}


	/**
     * Suggester for Drug names
     *
     * @return Illuminate\Database\Eloquent\Collection
     */
    static function drugLook($args, $page = 0, $pagesize = 20)
    {
		// break out the args
		foreach ($args as $key => $value)
			$$key = $value;

		$collection = collect();

		$query = '{
				suggest(contexts: ALL, suggest: DRUG, text: "'
				. $search . '") {
						curie
						curations
						highlighted
						iri
						text
						type
						weight
					}
				}
			}';

		// query genegraph
		$response = self::query($query,  __METHOD__);

		if (empty($response))
			return $response;

		// add each gene to the collection
		/*foreach($response->suggest as $record)
		{
			$node = new Nodal((array) $record);
			$node->label = $record->highlighted . '  (' . $record->curie . ')';
			$node->href = route('drug-show', $record->curie);

			$collection->push($node);
		}*/

		$array = [];
		foreach($response->suggest as $record)
		{
			$ctag = (empty($record->curations) ? '' : '        CURATED');
			$short = "RXNORM:" . basename($record->curie);
			$array[] = ['label' => $record->text . '  (' . $short . ')'
							. $ctag,
						'url' => route('drug-show', $short)];
		}


		//return (object) ['count' => count($collection), 'collection' => $collection];
		return json_encode($array);
	}


    /**
     * Get actionability details for a specific gene
     *
     * @return Illuminate\Database\Eloquent\Collection
     */
    static function actionabilityList($args, $page = 0, $pagesize = 20)
    {
		// break out the args
		foreach ($args as $key => $value)
			$$key = $value;

		$query = '{
			gene(iri: "' . $iri . '") {
				label
				conditions {
					iri
					label
					actionability_curations {
						report_date
						source
					}
				}
			  }
			}';

		// query genegraph
		$response = self::query($query,  __METHOD__);

		if (empty($response))
			return $response;

		$node = new Nodal((array) $response->gene);

		return $node;
	}


	/**
     * Get listing of all genes with dosage sensitivity.
     *
     * @return Illuminate\Database\Eloquent\Collection
     */
    static function dosageList($args, $page = 0, $pagesize = 20)
    {
		// break out the args
		foreach ($args as $key => $value)
			$$key = $value;

		// initialize the collection
		$collection = collect();

		$query = '{
				genes('
				. self::optionList($page, $pagesize, $sort, $direction, $search, "GENE_DOSAGE")
				. ') {
					count
					gene_list {
						label
						hgnc_id
						chromosome_band
						dosage_curation {
							report_date
							triplosensitivity_assertion {
								dosage_classification {
									ordinal
								}
							}
							haploinsufficiency_assertion {
								dosage_classification {
									ordinal
								}
							}
						}
					}
				}
			}';

		// query genegraph
		$response = self::query($query,  __METHOD__);

		if (empty($response))
			return $response;

		// add each gene to the collection
		foreach($response->genes->gene_list as $record)
		{
			$node = new Nodal((array) $record);

			// query local db for additional information
			$gene = Gene::where('hgnc_id', $node->hgnc_id)->first();

			if ($gene !== null)
			{
				$node->grch37 = $gene->grch37;
            	$node->grch38 = $gene->grch38;
				$node->hi = $gene->hi;
				$node->pli = $gene->pli;
				$node->plof = $gene->plof;
				$node->omimlink = $gene->display_omim;
				$node->morbid = $gene->morbid;
				$node->locus = $gene->locus_group;
				if ($gene->history !== null)
				{
					//dd($gene->history);
					foreach ($gene->history as $item)
					{
						//dd($item["what"]);
						if ($item['what'] == 'Triplosensitivity Score')
							$node->triplo_history = $item['what'] . ' changed from ' . $item['from']
													. ' to ' . $item['to'] . ' on ' . $item['when'];
						else if ($item['what'] == 'Haploinsufficiency Score')
							$node->haplo_history = $item['what'] . ' changed from ' . $item['from']
													. ' to ' . $item['to'] . ' on ' . $item['when'];
					}
				}
			}

			$node->type = 0;

			$collection->push($node);
		}

		return (object) ['count' => $response->genes->count, 'collection' => $collection,
						'ngenes' => $response->genes->count, 'nregions' => 0];
	}


	/**
     * Get details of a specific gene
     *
     * @return Illuminate\Database\Eloquent\Collection
     */
    static function dosageDetail($args, $page = 0, $pagesize = 20)
    {
		// break out the args
		foreach ($args as $key => $value)
			$$key = $value;

		$query = '{
				gene('
				. 'iri: "' . $gene
				. '") {
					label
					alternative_label
					hgnc_id
					chromosome_band
					curation_activities
					dosage_curation {
						curie
						report_date
						triplosensitivity_assertion {
							dosage_classification {
								ordinal
							}
						}
						haploinsufficiency_assertion {
							dosage_classification {
								ordinal
							}
						}
					}
					genetic_conditions {
						disease {
						  label
						  iri
						}
						gene_validity_assertions {
							mode_of_inheritance {
								label
								curie
							}
						  report_date
						  classification {
								label
								curie
							}
						  curie
						}
						actionability_curations {
						  report_date
						  source
						}
						gene_dosage_assertions {
						  report_date
						  assertion_type
						  dosage_classification {
							ordinal
							}
						  curie
						}
					}
				}
			}';

		// query genegraph
		$response = self::query($query,  __METHOD__);

		if (empty($response))
			return $response;

		$node = new Nodal((array) $response->gene);

		// add additional information from local db
		$localgene = Gene::where('hgnc_id', $gene)->first();
		if ($localgene !== null)
		{
			$node->alias_symbols = $localgene->display_aliases;
			$node->prev_symbols = $localgene->display_previous;
			$node->hi = round($localgene->hi, 2);
			$node->pli = round($localgene->pli, 2);
			$node->plof = round($localgene->plof, 2);
			$node->locus_type = $localgene->locus_type;
			$node->locus_group = $localgene->locus_group;
			$node->ensembl_id = $localgene->ensembl_gene_id;
			$node->entrez_id = $localgene->entrez_id;
			$node->omim_id = $localgene->omim_id;
			$node->ucsc_id = $localgene->ucsc_id;
			$node->uniprot_id = $localgene->uniprot_id;
			$node->function = $localgene->function;
			$node->grch37 = $localgene->grch37;
			$node->grch38 = $localgene->grch38;
			$node->mane_select = $localgene->mane_select;
            $node->mane_plus = $localgene->mane_plus;
		}

		// currently, there is no easy way to track what needs dosage_curation entries belong in
		// the catch all, so we need to process the genetic conditions and add some flags.
		$dosage_curation_map = ["haploinsufficiency_assertion" => true, "triplosensitivity_assertion" => true];

		if (empty($node->dosage_curation->triplosensitivity_assertion))
			unset($dosage_curation_map["triplosensitivity_assertion"]);

		if (empty($node->dosage_curation->haploinsufficiency_assertion))
			unset($dosage_curation_map["haploinsufficiency_assertion"]);

		if (!empty($node->genetic_conditions))
		{
			foreach($node->genetic_conditions as $condition)
			{
				foreach($condition->gene_dosage_assertions as $dosage)
				{
					switch ($dosage->assertion_type)
					{
						case "HAPLOINSUFFICIENCY_ASSERTION":
							unset($dosage_curation_map["haploinsufficiency_assertion"]);
							break;
						case "TRIPLOSENSITIVITY_ASSERTION":
							unset($dosage_curation_map["triplosensitivity_assertion"]);
							break;
					}
				}
			}

		}

		$node->dosage_curation_map = $dosage_curation_map;

		return $node;
	}


	/**
     * Get listing of all genes with validity assertions.
     *
     * @return Illuminate\Database\Eloquent\Collection
     */
    static function validityList($args, $page = 0, $pagesize = 20)
    {
		// break out the args
		foreach ($args as $key => $value)
			$$key = $value;


		//$search = null;

		// initialize the collection
		$collection = collect();

		$query = '{
				gene_validity_assertions('
					. self::optionList($page, $pagesize, $sort, $direction, $search)
				. ') {
					count
					curation_list {
						report_date
						curie
						disease {
							label
							curie
						}
						gene {
							label
							hgnc_id
						}
						mode_of_inheritance {
							label
							curie
						}
						classification {
							label
						}
						specified_by {
							label
						}
						attributed_to {
							label
						}
					}
				}
			}';

		// query genegraph
		$response = self::query($query,  __METHOD__);

		if (empty($response))
			return $response;

		// add each gene to the collection
		foreach($response->gene_validity_assertions->curation_list as $record)
		{
			if ($record->gene === null || $record->disease === null)
				continue;	// TODO:  Log as gg error

			$collection->push(new Nodal((array) $record));
		}

		$ngenes = $collection->unique('gene')->count();
		$npanels = $collection->unique('attributed_to')->count();

		return (object) ['count' => $response->gene_validity_assertions->count,
						'collection' => $collection,
						'ngenes' => $ngenes,
						'npanels' => $npanels
						];
	}


	/**
     * Get validity report for a specific gene-disease pair
     *
     * @return Illuminate\Database\Eloquent\Collection
     */
    static function validityDetail($args, $page = 0, $pagesize = 20)
    {
		// break out the args
		foreach ($args as $key => $value)
			$$key = $value;

		// special case where legacy perm value is passed
		if (is_numeric($perm))
			$perm = "CGGCIEX:assertion_" . $perm;

		$query = '{
			gene_validity_assertion('
			. 'iri: "' . $perm
			. '") {
				curie
				report_date
				gene {
					label
					hgnc_id
					curie
				}
				disease {
					label
					curie
				}
				mode_of_inheritance {
					label
					curie
				}
				attributed_to {
					label
					curie
				}
				classification {
					label
					curie
				}
				specified_by {
					label
					curie
				}
				legacy_json
			}
		}';

		// query genegraph
		$response = self::query($query,  __METHOD__);

		if (empty($response))
			return $response;

		// genegraph does return an error condition on an invalid assertion id, so handle it here
		if (empty($response->gene_validity_assertion->specified_by))
		{
			Log::info("Validty Detail Error:  No specified by field in iri: " . $perm);
			GeneLib::putError("Invalid gene validity assertion identifier");
			return null;
		}

		$node = new Nodal((array) $response->gene_validity_assertion);
		$node->json = json_decode($node->legacy_json, false);
		$node->score_data = $node->json->scoreJson ?? $node->json;

		// genegraph is not distinguishing gene express origin from others
		$node->origin = ($node->specified_by->label == "ClinGen Gene Validity Evaluation Criteria SOP5" && isset($node->json->jsonMessageVersion) 
							&& $node->json->jsonMessageVersion == "GCILite.5" ? true : false);

		return $node;

	}


	/**
     * Get listing of all affiliates
     *
     * @return Illuminate\Database\Eloquent\Collection
     */
    static function affiliateList($args, $page = 0, $pagesize = 20)
    {
		// break out the args
		foreach ($args as $key => $value)
			$key = $value;

		// initialize the collection
		$collection = collect();

		$query = '{
			affiliations (limit: null)
			{
				count
				agent_list {
					iri
					curie
					label
					gene_validity_assertions{
						count
					}
				}
			}
		}';

		// query genegraph
		$response = self::query($query,  __METHOD__);

		if (empty($response))
			return $response;

		$ncurations = 0;

		// add each gene to the collection
		foreach($response->affiliations->agent_list as $record)
		{
			$node = new Nodal((array) $record);
			$ncurations += $node->gene_validity_assertions->count;

			$collection->push(new Nodal((array) $record));
		}

		// genegraph currently provides no sort capablility
		$collection = $collection->sortBy('label');

		return (object) ['count' => $response->affiliations->count, 'collection' => $collection,
						'ncurations' => $ncurations];
	}


	/**
     * Get details for an affiliate
     *
     * @return Illuminate\Database\Eloquent\Collection
     */
    static function affiliateDetail($args, $page = 0, $pagesize = 20)
    {
		// break out the args
		foreach ($args as $key => $value)
			$$key = $value;

		// the affiliate ID is expected to be numeric, handle gracefully if not
		if (!ctype_digit($affiliate))
			$affiliate = "0";

		// initialize the collection
		$collection = collect();

		$query = '{
			affiliation('
				. 'iri: "CGAGENT:' . $affiliate
				. '") {
				curie
				iri
				label
				gene_validity_assertions(limit: null, sort: {field: GENE_LABEL, direction: ASC}) {
					count
					curation_list {
						curie
						iri
						label
						legacy_json
						gene {
							label
							hgnc_id
							curie
						}
						disease {
							label
							curie
						}
						mode_of_inheritance {
							label
							curie
						}
						attributed_to {
							label
							curie
						}
						classification {
							label
							curie
						}
						specified_by {
							label
							curie
						}
						report_date
					}
				}
			}
		}';

		// query genegraph
		$response = self::query($query,  __METHOD__);

		if (empty($response))
			return $response;

		// add each gene to the collection
		foreach($response->affiliation->gene_validity_assertions->curation_list as $record)
		{
			// make sure the record is properly formed
			if ($record->gene === null || $record->disease === null)
				continue;	// TODO:  Log this as a gg error

			$collection->push(new Nodal((array) $record));
		}

		return (object) ['count' => $response->affiliation->gene_validity_assertions->count,
						 'collection' => $collection, 'label' => $response->affiliation->label];
	}


	/**
     * Get details of a conditions
     *
     * @return Illuminate\Database\Eloquent\Collection
     */
    static function conditionDetail($args)
    {
		// break out the args
		foreach ($args as $key => $value)
			$$key = $value;

		// genegraph doesn't like when the mondo prefix is missing, handle gracefully

		if (strpos($condition, 'MONDO:') === false && strpos($condition, 'MONDO_') === false)
			$condition = 'MONDO:' . $condition;

		$query = '{
			disease('
			. 'iri: "' . $condition
			. '") {
				label
				iri
				curation_activities
				genetic_conditions {
					gene {
					label
					hgnc_id
					}
					gene_validity_assertions {
					mode_of_inheritance {
						label
						curie
					}
					report_date
					classification {
						label
						curie
					}
					curie
					}
					actionability_curations {
						report_date
						source
					}
					gene_dosage_assertions {
						report_date
						dosage_classification {
							ordinal
						}
						curie
					}
				}
			}
		}';

		// query genegraph
		$response = self::query($query,  __METHOD__);

		if (empty($response))
			return $response;

		$node = new Nodal((array) $response->disease);

		$naction = 0;
		$nvalid = 0;
		$ndosage = 0;

		// currently, there is no easy way to track what needs dosage_curation entries belong in
		// the catch all, so we need to process the genetic conditions and add some flags.
		$dosage_curation_map = ["haploinsufficiency_assertion" => true, "triplosensitivity_assertion" => true];

		if (empty($node->dosage_curation->triplosensitivity_assertion))
		unset($dosage_curation_map["triplosensitivity_assertion"]);

		if (empty($node->dosage_curation->haploinsufficiency_assertion))
		unset($dosage_curation_map["haploinsufficiency_assertion"]);

		if (!empty($node->genetic_conditions)) {
			foreach ($node->genetic_conditions as $condition) {
				//$nodeCollect = collect($node);
				//dd($nodeCollect);
				//dd(count($condition->gene_validity_assertions));
				$naction = $naction + count($condition->actionability_curations);
				$nvalid = $nvalid + count($condition->gene_validity_assertions);
				$ndosage = $ndosage + count($condition->gene_dosage_assertions);

				//dd($naction);
				//dd($nvalid);
				//dd($ndosage);
				foreach ($condition->gene_dosage_assertions as $dosage) {
					if(!empty($dosage->assertion_type)) {
						switch ($dosage->assertion_type) {
							case "HAPLOINSUFFICIENCY_ASSERTION":
								unset($dosage_curation_map["haploinsufficiency_assertion"]);
								break;
							case "TRIPLOSENSITIVITY_ASSERTION":
								unset($dosage_curation_map["triplosensitivity_assertion"]);
								break;
						}
					}
				}
			}
		}


		$node->naction = $naction;
		$node->nvalid = $nvalid;
		$node->ndosage = $ndosage;

		//dd($node);

		$node->dosage_curation_map = $dosage_curation_map;

		//dd($node);
		return $node;
	}


	/**
     * Suggester for Condition names
     *
     * @return Illuminate\Database\Eloquent\Collection
     */
    static function conditionLook($args, $page = 0, $pagesize = 20)
    {
		// break out the args
		foreach ($args as $key => $value)
			$$key = $value;

		$collection = collect();

		$query = '{
				suggest(contexts: ALL, suggest: DISEASE, text: "'
				. $search . '") {
						curie
						curations
						highlighted
						iri
						text
						type
						weight
					}
				}
			}';

		// query genegraph
		$response = self::query($query,  __METHOD__);

		if (empty($response))
			return $response;

		// add each gene to the collection
		/*foreach($response->suggest as $record)
		{
			$node = new Nodal((array) $record);
			$node->label = $record->highlighted . '  (' . $record->curie . ')';
			$node->href = route('condition-show', $record->curie);

			$collection->push($node);
		}*/

		$array = [];
		foreach($response->suggest as $record)
		{
			$ctag = (empty($record->curations) ? '' : '        CURATED');
			$array[] = ['label' => $record->text . '  (' . $record->curie . ')'
							. $ctag,
						'url' => route('condition-show', $record->curie)];
		}

		//return (object) ['count' => count($collection), 'collection' => $collection];
		return json_encode($array);
	}


	/**
     * Get listing of all conditions
     *
     * @return Illuminate\Database\Eloquent\Collection
     */
    static function conditionList($args, $curated = false, $page = 0, $pagesize = 20)
    {
		// break out the args
		foreach ($args as $key => $value)
			$$key = $value;

		// initialize the collection
		$collection = collect();

		if ($curated === true) {

			// note:  we don't currently use last_curated_date
			$query = '{
					diseases('
			. self::optionList($page, $pagesize, $sort, $direction, $search, 'ALL')
				. ') {
						count
						disease_list {
							label
							hgnc_id
							synonyms
							last_curated_date
							curation_activities
							dosage_curation {
								triplosensitivity_assertion {
									dosage_classification {
										ordinal
									  }
								}
								haploinsufficiency_assertion {
									dosage_classification {
										ordinal
									  }
								}
							}
						}
					}
				}';
		}
		else {
			$query = '{
				diseases('
			. self::optionList($page, $pagesize, $sort, $direction, $search, $curated)
				. ') {
					count
					disease_list {
						curie
						label
						description
						synonyms
						last_curated_date
						curation_activities
					}
				}
			}';
		}

		// query genegraph
		$response = self::query($query,  __METHOD__);

		if (empty($response))
			return $response;

		// add each gene to the collection
		foreach($response->diseases->disease_list as $record)
			$collection->push(new Nodal((array) $record));

		$ncurated = $collection->where('last_curated_date', '!=', null)->count();

		if ($curated) {
			$naction = $collection->where('has_actionability', true)->count();
			$nvalid = $collection->where('has_validity', true)->count();
			$ndosage = $collection->where('has_dosage', true)->count();

			Metric::store(Metric::KEY_TOTAL_CURATED_DISEASE, $response->diseases->count);
			//$ndosage = $collection->whereNotNull('dosage_curation')->count();
		} else {
			// right now we only use these counts on the curated page.  Probably should get triggered
			// by a call option so as not to bury things to deep.
			$naction = 0;
			$nvalid = 0;
			$ndosage = 0;
		}

		return (object) ['count' => $response->diseases->count, 'collection' => $collection,
						'ncurated' => $ncurated, 'naction' => $naction, 'nvalid' => $nvalid, 'ndosage' => $ndosage];
	}


	/**
     * Get listing of all drugs
     *
     * @return Illuminate\Database\Eloquent\Collection
     */
    static function drugList($args, $page = 0, $pagesize = 2000)
    {
		// break out the args
		foreach ($args as $key => $value)
			$$key = $value;

		// initialize the collection
		$collection = collect();

		$query = '{
				drugs('
				. self::optionList($page, $pagesize, $sort, $direction, $search)
				. ') {
					count
					drug_list {
						label
						curie
					}
				}
			}';

		// query genegraph
		$response = self::query($query,  __METHOD__);

		if (empty($response))
			return $response;

		// add each gene to the collection
		foreach($response->drugs->drug_list as $record)
			$collection->push(new Nodal((array) $record));

		//$collection = $collection->SortBy('label');

		return (object) ['count' => $response->drugs->count, 'collection' => $collection];
	}


	/**
     * Get details of a drug
     *
     * @return Illuminate\Database\Eloquent\Collection
     */
    static function drugDetail($args, $page = 0, $pagesize = 20)
    {
		// break out the args
		foreach ($args as $key => $value)
			$$key = $value;

		// remap drug id back to ontology format
		$drug = str_replace(':', '/', $drug);

		$query = '{
				drug(iri: "http://purl.bioontology.org/ontology/'
				. $drug
				. '") {
						label
						iri
						curie
						aliases
					}
				}
			}';

		// query genegraph
		$response = self::query($query,  __METHOD__);

		if (empty($response))
			return $response;

		$node = new Nodal((array) $response->drug);

		return $node;
	}


	/**
     * Get gene list with curation flags and last update
     *
     * @return Illuminate\Database\Eloquent\Collection
     */
    static function geneMetrics($args)
    {
		// break out the args
		foreach ($args as $key => $value)
			$$key = $value;

		// initialize the collection
		$collection = collect();

		$query = '{
				genes(limit: null, curation_activity: ALL) {
					count
					gene_list {
						curation_activities
						dosage_curation {
							triplosensitivity_assertion {
								dosage_classification {
									ordinal
								}
							}
							haploinsufficiency_assertion {
								dosage_classification {
									ordinal
								}
							}
						}
						genetic_conditions {
							actionability_curations {
								classification_description
								curie
								iri
								label
								report_date
								report_id
								source
								wg_label
							}
						}
					}
				}
			}';

		// query genegraph
		$response = self::query($query, __METHOD__);

		if (empty($response))
			return $response;

		$total_genes = $response->genes->count;

		$hapcounters = ['0' => 0, '1' => 0, '2' => 0, '3' => 0,
					'30' => 0, '40' => 0];

		$tripcounters = ['0' => 0, '1' => 0, '2' => 0, '3' => 0,
					'30' => 0, '40' => 0];

		$action_curations = 0;
		$adultcounter = 0;
		$pedscounter = 0;

		foreach($response->genes->gene_list as $record)
		{
			if (!empty($record->dosage_curation))
			{
				if (isset($record->dosage_curation->triplosensitivity_assertion->dosage_classification->ordinal))
					$tripcounters[$record->dosage_curation->triplosensitivity_assertion->dosage_classification->ordinal]++;
				if (isset($record->dosage_curation->haploinsufficiency_assertion->dosage_classification->ordinal))
					$hapcounters[$record->dosage_curation->haploinsufficiency_assertion->dosage_classification->ordinal]++;
			}

			foreach ($record->genetic_conditions as $condition)
			{
				if (!empty($condition->actionability_curations))
				{
					$action_curations += count($condition->actionability_curations);

					foreach ($condition->actionability_curations as $ac)
					{
						if (strpos($ac->iri, 'Adult') > 0)
							$adultcounter++;

						if (strpos($ac->iri, 'Pediatric') > 0)
							$pedscounter++;
					}
				}
			}
			$collection->push(new Nodal((array) $record));
		}

		$actionability_genes = $collection->where('has_actionability', true)->count();
		$validity_genes = $collection->where('has_validity', true)->count();
		$dosage_genes = $collection->where('has_dosage', true)->count();

		$values = [	Metric::KEY_TOTAL_CURATED_GENES => $response->genes->count,
					Metric::KEY_TOTAL_ACTIONABILITY_GENES => $actionability_genes,
					Metric::KEY_TOTAL_VALIDITY_GENES => $validity_genes,
				  	Metric::KEY_TOTAL_DOSAGE_GENES => $dosage_genes,
					Metric::KEY_TOTAL_DOSAGE_HAP_AR => $hapcounters['30'],
					Metric::KEY_TOTAL_DOSAGE_HAP_EMERGING => $hapcounters['2'],
					Metric::KEY_TOTAL_DOSAGE_HAP_LITTLE => $hapcounters['1'],
					Metric::KEY_TOTAL_DOSAGE_HAP_NONE => $hapcounters['0'],
					Metric::KEY_TOTAL_DOSAGE_HAP_SUFFICIENT => $hapcounters['3'],
					Metric::KEY_TOTAL_DOSAGE_HAP_UNLIKELY => $hapcounters['40'],
					Metric::KEY_TOTAL_DOSAGE_TRIP_AR => $tripcounters['30'],
					Metric::KEY_TOTAL_DOSAGE_TRIP_EMERGING => $tripcounters['2'],
					Metric::KEY_TOTAL_DOSAGE_TRIP_LITTLE => $tripcounters['1'],
					Metric::KEY_TOTAL_DOSAGE_TRIP_NONE => $tripcounters['0'],
					Metric::KEY_TOTAL_DOSAGE_TRIP_SUFFICIENT => $tripcounters['3'],
					Metric::KEY_TOTAL_DOSAGE_TRIP_UNLIKELY => $tripcounters['40'],
					Metric::KEY_TOTAL_DOSAGE_TRIP_AR => $tripcounters['30'],
					Metric::KEY_TOTAL_DOSAGE_CURATIONS => array_sum($hapcounters) + array_sum($tripcounters),
					Metric::KEY_TOTAL_ACTIONABILITY_CURATIONS => $action_curations,
					Metric::KEY_TOTAL_ACTIONABILITY_ADULT_CURATIONS => $adultcounter,
					Metric::KEY_TOTAL_ACTIONABILITY_PED_CURATIONS => $pedscounter
				];
//dd($values);
		$query = '{
			gene_validity_assertions(limit: null) {
				count
				curation_list {
					curie
					classification {
						label
					}
					specified_by {
						label
					}
					attributed_to {
						curie
						label
					}
				}
			}
		}';

		// query genegraph
		$response = self::query($query, __METHOD__);

		if (empty($response))
			return $response;

		$values[Metric::KEY_TOTAL_VALIDITY_CURATIONS] =
									$response->gene_validity_assertions->count;

		$template = ['definitive evidence' => 0,
					'strong evidence' => 0, 'moderate evidence' => 0,
					'limited evidence' => 0, 'disputing' => 0,
					'refuting evidence' => 0, 'no evidence' => 0,
					'no known disease relationship' => 0];

		$counters = $template;

		$panelcounters = [];

		foreach($response->gene_validity_assertions->curation_list as $record)
		{
			// deal with the corrupted record bug in genegraph
			if (!isset($record->classification->label))
				continue;

			if (isset($panelcounters[$record->attributed_to->curie]))
			{
				$panelcounters[$record->attributed_to->curie]['count']++;
				$panelcounters[$record->attributed_to->curie]['classtotals'][$record->classification->label]++;
			}
			else
			{
				$panelcounters[$record->attributed_to->curie] = ['count' => 1,
									'label' => $record->attributed_to->label,
									'classtotals' => $template,
									'classoffsets' => $template,
									'classlength' => $template];
				$panelcounters[$record->attributed_to->curie]['classtotals'][$record->classification->label] = 1;
			}

			if (isset($counters[$record->classification->label]))
				$counters[$record->classification->label]++;
		}

		// calculate the segment size and offsets
		foreach ($panelcounters as &$panel)
		{
			foreach ($panel['classlength'] as $key => &$value)
			{
				$value = round($panel['classtotals'][$key] / $panel['count'] * 100, 2);
			}
		}

		$values[Metric::KEY_EXPERT_PANELS] = $panelcounters;

		$values[Metric::KEY_TOTAL_VALIDITY_DEFINITIVE] = $counters['definitive evidence'];
		$values[Metric::KEY_TOTAL_VALIDITY_STRONG] = $counters['strong evidence'];
		$values[Metric::KEY_TOTAL_VALIDITY_MODERATE] = $counters['moderate evidence'];
		$values[Metric::KEY_TOTAL_VALIDITY_LIMITED] = $counters['limited evidence'];
		$values[Metric::KEY_TOTAL_VALIDITY_DISPUTED] = $counters['disputing'];
		$values[Metric::KEY_TOTAL_VALIDITY_REFUTED] = $counters['refuting evidence'];
		$values[Metric::KEY_TOTAL_VALIDITY_NONE] = $counters['no evidence'];

		$values[Metric::KEY_TOTAL_GENE_LEVEL_CURATIONS] =
						$values[Metric::KEY_TOTAL_ACTIONABILITY_CURATIONS] +
						$values[Metric::KEY_TOTAL_VALIDITY_CURATIONS] +
						$values[Metric::KEY_TOTAL_DOSAGE_CURATIONS];
dd($values);

		// this should not be here, but its late
		// pull all the regions from jira
		$regions = Jira::regionList($args);

		foreach ($regions->collection as $region)
		{
			if (isset($region->triplo_assertion) && isset($tripcounters[$region->triplo_assertion]))
				$tripcounters[$region->triplo_assertion]++;

			if (isset($region->haplo_assertion) && isset($hapcounters[$region->haplo_assertion]))
				$hapcounters[$region->haplo_assertion]++;
		}

		$values[Metric::KEY_TOTAL_DOSAGE_REGIONS] = $regions->count;
		$values[Metric::KEY_TOTAL_DOSAGE_HAP_AR] = $hapcounters['30'];
		$values[Metric::KEY_TOTAL_DOSAGE_HAP_EMERGING] = $hapcounters['2'];
		$values[Metric::KEY_TOTAL_DOSAGE_HAP_LITTLE] = $hapcounters['1'];
		$values[Metric::KEY_TOTAL_DOSAGE_HAP_NONE] = $hapcounters['0'];
		$values[Metric::KEY_TOTAL_DOSAGE_HAP_SUFFICIENT] = $hapcounters['3'];
		$values[Metric::KEY_TOTAL_DOSAGE_HAP_UNLIKELY] = $hapcounters['40'];
		$values[Metric::KEY_TOTAL_DOSAGE_TRIP_AR] = $tripcounters['30'];
		$values[Metric::KEY_TOTAL_DOSAGE_TRIP_EMERGING] = $tripcounters['2'];
		$values[Metric::KEY_TOTAL_DOSAGE_TRIP_LITTLE] = $tripcounters['1'];
		$values[Metric::KEY_TOTAL_DOSAGE_TRIP_NONE] = $tripcounters['0'];
		$values[Metric::KEY_TOTAL_DOSAGE_TRIP_SUFFICIENT] = $tripcounters['3'];
		$values[Metric::KEY_TOTAL_DOSAGE_TRIP_UNLIKELY] = $tripcounters['40'];
		$values[Metric::KEY_TOTAL_DOSAGE_TRIP_AR] = $tripcounters['30'];
		$values[Metric::KEY_TOTAL_DOSAGE_CURATIONS] = array_sum($hapcounters) + array_sum($tripcounters);

		$paths = Variant::all();

		$npathogenic = 0;
		$nlikely = 0;
		$nuncertain = 0;
		$nbenign = 0;
		$nlikelybenign = 0;
		$epanels = [];
		$varunique = [];

		foreach($paths as $variant)
		{
			switch ($variant->guidelines[0]["outcome"]["label"])
			{
				case 'Pathogenic':
					$npathogenic++;
					break;
				case 'Likely Pathogenic':
					$nlikely++;
					break;
				case 'Uncertain Significance':
					$nuncertain++;
					break;
				case 'Likely Benign':
					$nlikelybenign++;
					break;
				case 'Benign':
					$nbenign++;
					break;
			}

			// count the expertpanels (@id would yield the ep number but for now just use string)
			$ep = $variant->guidelines[0]["agents"][0]["affiliation"];
			$ep = str_replace(' VCEP', '', $ep);
			if (isset($epanels[$ep]))
				$epanels[$ep]++;
			else
				$epanels[$ep] = 1;

		}

		ksort($epanels);

		$values[Metric::KEY_TOTAL_PATHOGENICITY_CURATIONS] = $paths->count();
		$values[Metric::KEY_TOTAL_PATHOGENICITY_UNIQUE] = $paths->unique(function ($item) {
				return $item['caid'].$item['variant_id'];
		})->count();
		$values[Metric::KEY_TOTAL_PATHOGENICITY_PATHOGENIC] = $npathogenic;
		$values[Metric::KEY_TOTAL_PATHOGENICITY_LIKELY] = $nlikely;
		$values[Metric::KEY_TOTAL_PATHOGENICITY_UNCERTAIN] = $nuncertain;
		$values[Metric::KEY_TOTAL_PATHOGENICITY_BENIGN] = $nbenign;
		$values[Metric::KEY_TOTAL_PATHOGENICITY_LIKELYBENIGN] = $nlikelybenign;
		$values[Metric::KEY_EXPERT_PANELS_PATHOGENICITY] = $epanels;

		// new actionability statistics
		/*
		$query = '{
				statistics {
					actionability_tot_reports
					actionability_tot_updated_reports
					actionability_tot_adult_gene_disease_pairs
					actionability_tot_pediatric_gene_disease_pairs
					actionability_tot_adult_outcome_intervention_pairs
					actionability_tot_outcome_intervention_pairs
					actionability_tot_pediatric_outcome_intervention_pairs
					actionability_tot_adult_score_counts
					actionability_tot_pediatric_score_counts
			 	}
		 }';

		// query genegraph
		$response = self::query($query, __METHOD__);

		if (empty($response))
			return $response;
		 */
		
		$metric = new Metric([	'values' => $values,
								'type' => Metric::TYPE_SYSTEM,
								'status' => 1,
								] );

		$metric->save();

		return true;
	}


	/**
     * Suggester for Gene names
     *
     * @return Illuminate\Database\Eloquent\Collection
     */
    static function geneLook($args, $page = 0, $pagesize = 20)
    {
		// break out the args
		foreach ($args as $key => $value)
			$$key = $value;

		$collection = collect();

		$query = '{
				suggest(contexts: ALL, suggest: GENE, text: "'
				. $search . '") {
						curations
						highlighted
						alternative_curie
						text
					}
				}
			}';

		// query genegraph
		$response = self::query($query,  __METHOD__);

		if (empty($response))
			return $response;

		// add each gene to the collection
		/*foreach($response->suggest as $record)
		{
			$node = new Nodal((array) $record);
			$node->label = $record->highlighted . '  (' . $record->alternative_curie . ')';
			$node->href = route('gene-show', $record->alternative_curie);

			$collection->push($node);
		}*/

		$array = [];
		foreach($response->suggest as $record)
		{
			$ctag = (empty($record->curations) ? '' : '        CURATED');
			$array[] = ['label' => $record->text . '  (' . $record->alternative_curie . ')'
							. $ctag,
						'url' => route('gene-show', $record->alternative_curie)];
		}

		//return (object) ['count' => count($collection), 'collection' => $collection];
		return json_encode($array);
	}


	/**
     * Build the option list for the GraphQL call
     *
     * @return Illuminate\Database\Eloquent\Collection
     */
    static function optionList($page = 0, $pagesize = null, $sort=null, $sortdir='ASC', $search = null, $curated = false)
    {
		$options = [];

		if (!is_null($pagesize))
			$options[] = 'limit: ' . $pagesize;
		else
			$options[] = 'limit: null';

		if (!empty($page))
			$options[] = 'offset: ' . $page; // ($page * $pagesize);

		if ($curated !== false)
			$options[] = 'curation_activity: ' . $curated;

		if (!empty($sort))
			$options[] = 'sort: {field: ' . $sort . ', direction: ' . strtoupper($sortdir) . '}';

		if (!empty($search))
			$options[] = 'text: "*' . $search . '*"';

		return implode(', ', $options);
	}
}
