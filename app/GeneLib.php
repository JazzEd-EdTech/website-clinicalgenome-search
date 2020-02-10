<?php

namespace App;

//use App\Traits\Display;

use App\Nodal;

// the various database access models
use App\Neo4j;
use App\Graphql;


/**
 * 
 * @category   Library
 * @package    Search
 * @author     P. Weller <pweller1@geisinger.edu>
 * @author     S. Goehringer <scottg@creationproject.com>
 * @copyright  2019 ClinGen
 * @license    http://www.php.net/license/3_01.txt  PHP License 3.01
 * @version    Release: @package_version@
 * @link       http://pear.php.net/package/PackageName
 * @see        NetOther, Net_Sample::Net_Sample()
 * @since      Class available since Release 1.0.0
 * 
 * */
class GeneLib
{
    //use Display;
    
    /**
     * This class is designed to be used statically.  
     */
	 
    
    /**
     * Get a list of actionability curations from Genegraph
     * 
     * @return Illuminate\Database\Eloquent\Collection
     */
    static function actionabilityList($args)
    {
		// check args
		if (is_null($args) || !is_array($args))
			return collect([]);
			
		// Gene data is currently in neo4j
		$response = Graphql::actionabilityList($args);
			
		return $response;
	}
    
    
    /**
     * Get a list of all the curated genes
     * 
     * @return Illuminate\Database\Eloquent\Collection
     */
    static function geneList($args)
    {
		if (is_null($args) || !is_array($args))
			return collect([]);
			
		// Gene data is currently in neo4j
		$response = Neo4j::geneList($args);

		// TODO:  error return?
		if ($response === null)
			return null;
		
		// morph the graphware structure to a collection
		foreach($response->getRecords() as $record)
		{
			$node = new Nodal(array_combine($record->keys(), $record->values()));
			
			// set some shortcuts for the views
			if (!empty($node->assertions_collection))
			{
				foreach($node->assertions_collection as $assertion)
				{
					if ($assertion->hasLabel('ActionabilityAssertion'))
						$node->hasActionability = true;
					if ($assertion->hasLabel('GeneDiseaseAssertion'))
						$node->hasValidity = true;
					if ($assertion->hasLabel('GeneDosageAssertion'))
						$node->hasDosage = true;
				}
			}
			$records[] = $node;
		}

		return collect($records);
	}
	
	
	/**
     * Get details of a particular gene
     * 
     * @return Illuminate\Database\Eloquent\Collection
     */
    static function geneDetail($args)
    {
		if (is_null($args) || !is_array($args))
			return collect([]);
			
		// Most of the gene and curation data is currently in neo4j...
		$response = Neo4j::geneDetail($args);
		
		//...but actionability is now in genegraph
		//$actionability = Genegraph::actionabilityList($args);
		
		// null means no records found
		if ($response === null)
			return null;
		
		// morph the graphware structure to a collection
		$record = new Nodal($response);
		
		return $record;
	}
	
	
	/**
     * Get a list of all the affiliates and associated curation counts
     * 
     * @return Illuminate\Database\Eloquent\Collection
     */
    static function affiliateList($args)
    {
		if (is_null($args) || !is_array($args))
			return collect([]);
			
		// The affiliate and curation data is currently in neo4j
		$response = Neo4j::affiliateList($args);
		
		// null means no records found
		if ($response === null)
			return null;
		
		// morph the graphware structure to a collection
		foreach($response->getRecords() as $record)
			$records[] = new Nodal(array_combine($record->keys(), $record->values()));
		
		return collect($records);
	}
	
	
	/**
     * Get details of a particular affiliate and associated curations
     * 
     * @return Illuminate\Database\Eloquent\Collection
     */
    static function affiliateDetail($args)
    {
		if (is_null($args) || !is_array($args))
			return null;
			
		// The affiliate and curation data is currently in neo4j
		$response = Neo4j::affiliateDetail($args);

		// null means no records found
		if ($response === null)
			return null;
		
		// morph the graphware structure to a collection
		$record = new Nodal($response);
		
		return $record;
	}
	
	
	/**
     * Get a list of all gene validity assertions
     * 
     * @return Illuminate\Database\Eloquent\Collection
     */
    static function validityList($args)
    {
		if (is_null($args) || !is_array($args))
			return collect([]);
			
		// Gene data is currently in neo4j
		$response = Neo4j::validityList($args);

		// TODO:  error return?
		if ($response === null)
			return null;
		
		// morph the graphware structure to a collection
		foreach($response->getRecords() as $record)
		{
			$node = new Nodal($record->value('a'));
			
			// set some shortcuts for the views
			$node->disease = $node->diseases[0]['label'];
			$node->mondo = $node->diseases[0]['curie'];
			$node->classification = $node->interpretation[0]['label'];
			$node->gene_symbol = $node->genes[0]['symbol'];
			$node->gene_hgnc = $node->genes[0]['hgnc_id'];
			
			switch ($node->jsonMessageVersion)
			{
				case 'GCI.5':
					$node->sop = "SOP5";
					break;
				case 'GCI.6':
					$node->sop = "SOP6";
					break;
				case 'GCI.7':
					$node->sop = "SOP7";
					break;
				default:
					$node->sop = "SOP4";
			}
				
			//dd($node);
					
			$records[] = $node;
		}
		
		//dd($records);
		return collect($records);
	}
	
	
	/**
     * Get details of a gene validity assertion
     * 
     * @return Illuminate\Database\Eloquent\Collection
     */
    static function validityDetail($args)
    {
		if (is_null($args) || !is_array($args))
			return collect([]);
			
		// The gene validity data is currently in neo4j...
		$response = Neo4j::validityDetail($args);
		
		// null means no records found
		if ($response === null)
			return null;
		
		// morph the graphware structure to a collection
		//TODO, make this into a better structure.
		$record = new Nodal($response);
		
		return $record;
	}
	
	
	/**
     * Get a list of all the genes with dosage sensitivitiy
     * 
     * @return Illuminate\Database\Eloquent\Collection
     */
    static function dosageList($args)
    {
		if (is_null($args) || !is_array($args))
			return collect([]);
			
		// Gene data is currently in neo4j
		$response = Neo4j::dosageList($args);

		// TODO:  error return?
		if ($response === null)
			return null;
		
		// morph the graphware structure to a collection
		foreach($response->getRecords() as $record)
		{
			//dd($record);
			$node = new Nodal(array_combine($record->keys(), $record->values()));

			//dd($node);
			$records[] = $node;
		}
		
		return collect($records);
	}
	
	
	/**
     * Get details of a particular gene with dosage sensitivitiy
     * 
     * @return Illuminate\Database\Eloquent\Collection
     */
    static function dosageDetail($args)
    {
		/*
		// not needed this will redirect
		if (is_null($args) || !is_array($args))
			return collect([]);
			
		// Most of the gene and curation data is currently in neo4j...
		$response = Neo4j::geneDetail($args);
		
		//...but actionability is now in genegraph
		//$actionability = Genegraph::actionabilityList($args);
		
		// null means no records found
		if ($response === null)
			return null;
		
		// morph the graphware structure to a collection
		$record = new Nodal($response);
		
		return $record;
		* */
		return null;
	}
	
	
	/**
     * Get a list of all the drugs
     * 
     * @return Illuminate\Database\Eloquent\Collection
     */
    static function drugList($args)
    {
		if (is_null($args) || !is_array($args))
			return collect([]);
			
		// Drug data is currently in neo4j
		$response = Neo4j::drugList($args);

		// TODO:  error return?
		if ($response === null)
			return null;
		

		// morph the graphware structure to a collection
		foreach($response->getRecords() as $record)
		{
			$node = new Nodal(array_combine($record->keys(), $record->values()));
			
			// set some shortcuts for the views when curations are added
			/*if (!empty($node->assertions_collection))
			{
				foreach($node->assertions_collection as $assertion)
				{
					if ($assertion->hasLabel('ActionabilityAssertion'))
						$node->hasActionability = true;
					if ($assertion->hasLabel('GeneDiseaseAssertion'))
						$node->hasValidity = true;
					if ($assertion->hasLabel('GeneDosageAssertion'))
						$node->hasDosage = true;
				}
			}*/
			$records[] = $node;
		}

		return collect($records);
	}
	
	
	/**
     * Get details of a particular drug
     * 
     * @return Illuminate\Database\Eloquent\Collection
     */
    static function drugDetail($args)
    {
		if (is_null($args) || !is_array($args))
			return collect([]);
			
		// Drug details are currently in neo4j
		$response = Neo4j::drugDetail($args);
		
		// null means no records found
		if ($response === null)
			return null;
		
		// morph the graphware structure to a collection
		//$record = new Nodal($response);
		$record = new Nodal(array_combine($response->keys(), $response->values()));
		
		return $record;
	}
	
	
	/**
     * Get a list of all the conditions
     * 
     * @return Illuminate\Database\Eloquent\Collection
     */
    static function conditionList($args)
    {
		if (is_null($args) || !is_array($args))
			return collect([]);
			
		// Gene data is currently in neo4j
		$response = Neo4j::conditionList($args);

		// TODO:  error return?
		if ($response === null)
			return null;
		
		// morph the graphware structure to a collection
		foreach($response->getRecords() as $record)
		{
			$node = new Nodal(array_combine($record->keys(), $record->values()));

			// set some shortcuts for the views
			if (!empty($node->assertions_collection))
			{
				foreach($node->assertions_collection as $assertion)
				{
					if ($assertion->hasLabel('ActionabilityAssertion'))
						$node->hasActionability = true;
					if ($assertion->hasLabel('GeneDiseaseAssertion'))
						$node->hasValidity = true;
					if ($assertion->hasLabel('GeneDosageAssertion'))
						$node->hasDosage = true;
				}
			}
			
			$records[] = $node;
		}

		return collect($records);
	}
	
	
	/**
     * Get details of a particular gene
     * 
     * @return Illuminate\Database\Eloquent\Collection
     */
    static function conditionDetail($args)
    {
		if (is_null($args) || !is_array($args))
			return collect([]);
			
		// Most of the gene and curation data is currently in neo4j...
		$response = Neo4j::geneDetail($args);
		
		//...but actionability is now in genegraph
		//$actionability = Genegraph::actionabilityList($args);
		
		// null means no records found
		if ($response === null)
			return null;
		
		// morph the graphware structure to a collection
		$record = new Nodal($response);
		
		return $record;
	}
}