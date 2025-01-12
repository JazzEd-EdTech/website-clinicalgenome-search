<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Display;

use Uuid;
use App\Curation;

/**
 *
 * @category   Library
 * @package    Search
 * @author     P. Weller <pweller1@geisinger.edu>
 * @copyright  2020 ClinGen
 * @license    http://www.php.net/license/3_01.txt  PHP License 3.01
 * @version    Release: @package_version@
 * @link       http://pear.php.net/package/PackageName
 * @see        NetOther, Net_Sample::Net_Sample()
 * @since      Class available since Release 1.0.0
 *
 * */
class Dosage extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Display;

    /**
     * The attributes that should be validity checked.
     *
     * @var array
     */
    public static $rules = [];

    /**
     * Map the json attributes to associative arrays.
     *
     * @var array
     */
	protected $casts = [
              'history' => 'array',
              'gain_pheno_omim' => 'array',
              'loss_pheno_omim' => 'array',
    ];

    /**
     * The attributes that are mass assignable.  Remember to fill it
     * in when all the attributes are known.
     *
     * @var array
     */
     protected $fillable = ['label', 'issue', 'curation', 'description', 'cytoband',
                            'chr', 'start', 'stop', 'start38', 'stop38', 'grch37',
                            'grch38', 'pli', 'omiim', 'haplo', 'triplo', 'history',
                            'haplo_history', 'triplo_history',
                            'gain_pheno_omim', 'gain_pheno_ontology', 'gain_pheno_ontology_id',
                            'gain_pheno_name', 'gain_comments',
                            'loss_pheno_omim', 'loss_pheno_ontology', 'loss_pheno_ontology_id',
                            'loss_pheno_name', 'loss_comments',
                            'workflow', 'resolved', 'notes', 'type', 'status'
                            ];

	  /**
     * Non-persistent storage model attributes.
     *
     * @var array
     */
    protected $appends = [];

    protected static $field_map = [
        'description' => 'description',
        'ISCA Region Name' => false,
        'Gene Symbol' => false,
        'HGNC ID' => false,
        'CytoBand' => false,
        'Genome Position' => false,
        'Genome SeqID' => false,
        'labels' => false,
        'Attachment' => false,
        'Targeting decision comment' => false,
        'Phenotype comment' => false,
        'gnomAD Allele Frequency' => false,
        'Loss phenotype comments' => 'loss_comments',
        'Triplosensitive phenotype comments' => 'gain_comments',
        'Breakpoint Type' => false,
        'Do Population Variants Overlap This Region?' => false,
        'Triplosensitive phenotype name' => 'gain_pheno_name',
        'Reduced Penetrance Comment' => 'reduced_penetrance_comment',
        'Epic Link' => false,
        'Associated with Reduced Penetrance' => 'reduced_penetrance',
        'Loss phenotype name' => 'loss_pheno_name',
        'Loss Phenotype OMIM ID Specificity' => false,
        'Loss Specificity' => false,

        'Link' => ['key' => 'attributes', 'value' => 'linked_issues'],

        'on 180K Chip' => false,
        'Contains Known HI/TS Region?' => false,
        'Loss phenotype OMIM ID' => ['key' => 'loss_pheno_omim', 'value' => 'id', 'type' => Disease::TYPE_OMIM],

        'Haploinsufficiency Disease ID' => ['key' => 'loss_pheno_omim', 'value' => 'id', 'type' => 0],

        'Number of probands with a loss' => false,
        'Triplosensitive phenotype OMIM ID' => ['key' => 'gain_pheno_omim', 'value' => 'id', 'type' => Disease::TYPE_OMIM],

        'Triplosensitive Disease ID' => ['key' => 'attributes', 'value' => 'gain_pheno_omim'],

        'Number of probands with a gain' => false,
        'Targeting decision based on' => false,
        'Inheritance Pattern' => false,
        'Should be targeted?' => false,
        'Triplosensitive phenotype ontology' => ['key' => 'gain_pheno_ontology', 'value' => 'id'],
        'Triplosensitive phenotype ontology identifier' => ['key' => 'gain_pheno_ontology_id', 'value' => 'id'],
        'Loss phenotype ontology' => ['key' => 'loss_pheno_ontology', 'value' => 'id'],
        'Loss phenotype ontology identifier' => ['key' => 'loss_pheno_ontology_id', 'value' => 'id'],
        'CGD Inheritance' => false,
        'CGD Condition' => false,
        'CGD References' => false,
        'Population Variants Description' => false,
        'Population Variants Frequency' => false,
        'Population Variants Data Source' => false,
        'ISCA Haploinsufficiency score' => ['key' => 'haplo score', 'value' => 'id'],
        'ISCA Loss of function score' => ['key' => 'haplo score', 'value' => 'id'],
        'ISCA Triplosensitivity score' => ['key' => 'triplo score', 'value' => 'id'],
        'Summary' => "summary",
        'assignee' => false,
        'Reporter' => false,
        'Creator' => false,
        'DDG2P Status' => false,
        'DDG2P Inheritance' => false,
        'DDG2P Details' => false,
        'DDG2P Consequences' => false,
        'DDG2P Gene' => false,
        'GeneReviews Link' => ['key' => 'genereviews', 'value' => 'id'],
        'dbVar ID' => false,
        'Link to Gene' => false,
        'OMIM Link' => false,
        'GRCh37 strand' => false,
        'GRCh38 strand' => false,
        'GRCh38 Minimum position' => false,
        'GRCh38 annotation run' => false,
        'GRCh38 SeqID' => false,
        'GRCh38 Genome Position' => false,
        'ExAC pLI score' => false,
        'gnomAD pLI score' => false,
        'Previous Gene Symbol' => false,
        'Original Loss Phenotype ID' => false,
        'Original Loss phenotype name' => false,
        'Minimum Genome Position' => false,
        'Comment' => "comment",
        'resolution' => 'resolution',
        'status' => 'jira_status',
        'Component/s' => false,
        'Component' => false,
        'GRCh37 Genome Position' => false,
        'WatcherField' => false,
        'Workflow' => false,
        'Loss PMID 1' => ['key' => 'loss_pmids', 'value' => 'pmid', 'sid' => 1],
        'Loss PMID 1 Description' => ['key' => 'loss_pmids', 'value' => 'desc', 'sid' => 1],
        'Type of Evidence Loss PMID 1' => false,
        'Loss PMID 2' => ['key' => 'loss_pmids', 'value' => 'pmid', 'sid' => 2],
        'Loss PMID 2 Description' => ['key' => 'loss_pmids', 'value' => 'desc', 'sid' => 2],
        'Type of Evidence Loss PMID 2' => false,
        'Loss PMID 3' => ['key' => 'loss_pmids', 'value' => 'pmid', 'sid' => 3],
        'Loss PMID 3 Description' => ['key' => 'loss_pmids', 'value' => 'desc', 'sid' => 3],
        'Type of Evidence Loss PMID 3' => false,
        'Loss PMID 4' => ['key' => 'loss_pmids', 'value' => 'pmid', 'sid' => 4],
        'Loss PMID 4 Description' => ['key' => 'loss_pmids', 'value' => 'desc', 'sid' => 4],
        'Type of Evidence Loss PMID 4' => false,
        'Loss PMID 5' => ['key' => 'loss_pmids', 'value' => 'pmid', 'sid' => 5],
        'Loss PMID 5 Description' => ['key' => 'loss_pmids', 'value' => 'desc', 'sid' => 5],
        'Type of Evidence Loss PMID 5' => false,
        'Loss PMID 6' => ['key' => 'loss_pmids', 'value' => 'pmid', 'sid' => 6],
        'Loss PMID 6 Description' => ['key' => 'loss_pmids', 'value' => 'desc', 'sid' => 6],
        'Type of Evidence Loss PMID 6' => false,
        'Gain PMID 1' => ['key' => 'gain_pmids', 'value' => 'pmid', 'sid' => 1],
        'Gain PMID 1 Description' => ['key' => 'gain_pmids', 'value' => 'desc', 'sid' => 1],
        'Type of Evidence Gain PMID 1' => false,
        'Gain PMID 1 Desc' => ['key' => 'gain_pmids', 'value' => 'desc', 'sid' => 1],
        'Gain PMID 2' => ['key' => 'gain_pmids', 'value' => 'pmid', 'sid' => 2],
        'Gain PMID 2 Description' => ['key' => 'gain_pmids', 'value' => 'desc', 'sid' => 2],
        'Type of Evidence Gain PMID 2' => false,
        'Gain PMID 2 Desc' => ['key' => 'gain_pmids', 'value' => 'desc', 'sid' => 2],
        'Gain PMID 3' => ['key' => 'gain_pmids', 'value' => 'pmid', 'sid' => 3],
        'Gain PMID 3 Description' => ['key' => 'gain_pmids', 'value' => 'desc', 'sid' => 3],
        'Type of Evidence Gain PMID 3' => false,
        'Gain PMID 3 Desc' => ['key' => 'gain_pmids', 'value' => 'desc', 'sid' => 3],
        'Gain PMID 4' => ['key' => 'gain_pmids', 'value' => 'pmid', 'sid' => 4],
        'Gain PMID 4 Description' => ['key' => 'gain_pmids', 'value' => 'desc', 'sid' => 4],
        'Type of Evidence Gain PMID 4' => false,
        'Gain PMID 4 Desc' => ['key' => 'gain_pmids', 'value' => 'desc', 'sid' => 4],
        'Gain PMID 5' => ['key' => 'gain_pmids', 'value' => 'pmid', 'sid' => 5],
        'Gain PMID 5 Description' => ['key' => 'gain_pmids', 'value' => 'desc', 'sid' => 5],
        'Type of Evidence Gain PMID 5' => false,
        'Gain PMID 5 Desc' => ['key' => 'gain_pmids', 'value' => 'desc', 'sid' => 5],
        'Gain PMID 6' => ['key' => 'gain_pmids', 'value' => 'pmid', 'sid' => 6],
        'Gain PMID 6 Description' => ['key' => 'gain_pmids', 'value' => 'desc', 'sid' => 6],
        'Type of Evidence Gain PMID 6' => false,
        'Gain PMID 6 Desc' => ['key' => 'gain_pmids', 'value' => 'desc', 'sid' => 6],
        'GRCh37 Minimum Genome Position' => false,
        'Gene Type' => false,
        'Loss phenotype ontology' => false,
        'Loss phenotype ontology ' => false,
        'Loss phenotype ontology name'  => false,
        'Original Gain phenotype Name' => false,
        'PMID' => false,
        'Original Gain Phenotype ID' => false,
        'Locus Specific DB link' => false
    ];


    protected static $pmid_fields = [
        'Loss 1' => [
            'evidence_id' => 'customfield_10183',
            'evidence_type' => 'customfield_12331',
            'description' => 'customfield_10184',
        ],
        'Loss 2' => [
            'evidence_id' => 'customfield_10185',
            'evidence_type' => '',
            'description' => 'customfield_10186',
        ],
        'Loss 3' => [
            'evidence_id' => 'customfield_10187',
            'evidence_type' => 'customfield_12333',
            'description' => 'customfield_10188',
        ],
        'Loss 4' => [
            'evidence_id' => 'customfield_12231',
            'evidence_type' => 'customfield_12334',
            'description' => 'customfield_12237',
        ],
        'Loss 5' => [
            'evidence_id' => 'customfield_12232',
            'evidence_type' => 'customfield_12335',
            'description' => 'customfield_12238',
        ],
        'Loss 6' => [
            'evidence_id' => 'customfield_12233',
            'evidence_type' => 'customfield_12336',
            'description' => 'customfield_12239',
        ],
        'Gain 1' => [
            'evidence_id' => 'customfield_10189',
            'evidence_type' => 'customfield_12337',
            'description' => 'customfield_10190',
        ],
        'Gain 2' => [
            'evidence_id' => 'customfield_10191',
            'evidence_type' => 'customfield_12338',
            'description' => 'customfield_10192',
        ],
        'Gain 3' => [
            'evidence_id' => 'customfield_10193',
            'evidence_type' => 'customfield_12339',
            'description' => 'customfield_10194',
        ],
        'Gain 4' => [
            'evidence_id' => 'customfield_12234',
            'evidence_type' => 'customfield_12340',
            'description' => 'customfield_12240',
        ],
        'Gain 5' => [
            'evidence_id' => 'customfield_12235',
            'evidence_type' => 'customfield_12341',
            'description' => 'customfield_12241',
        ],
        'Gain 6' => [
            'evidence_id' => 'customfield_12236',
            'evidence_type' => 'customfield_12342',
            'description' => 'customfield_12242',
        ],
    ];


    /**
     * Automatically assign an ident on instantiation
     *
     * @param	array	$attributes
     * @return 	void
     */
    public function __construct(array $attributes = array())
    {
        $this->attributes['ident'] = (string) Uuid::generate(4);
        parent::__construct($attributes);
  	}


	  /**
     * Query scope by ident
     *
     * @@param	string	$ident
     * @return Illuminate\Database\Eloquent\Collection
     */
    public function scopeIdent($query, $ident)
    {
      return $query->where('ident', $ident);
    }


    /**
     * Query scope by iddur
     *
     * @@param	string	$ident
     * @return Illuminate\Database\Eloquent\Collection
     */
    public function scopeIssue($query, $issue)
    {
      return $query->where('issue', $issue);
    }


    /**
     * Query scope by type
     *
     * @@param	string	$ident
     * @return Illuminate\Database\Eloquent\Collection
     */
    public function scopeType($query, $type)
    {
      return $query->where('type', $type);
    }


    /**
     * Return full name of gene
     *
     * @@param
     * @return
     */
    public function getHgncIdAttribute()
    {
		  return $this->issue ?? null;
    }


    /**
     * Return full name of gene
     *
     * @@param
     * @return
     */
    public function getSymbolAttribute()
    {
		  return $this->label ?? null;
    }


    /**
     * Return full name of gene
     *
     * @@param
     * @return
     */
    public function getChromosomeBandAttribute()
    {
		  return $this->cytoband ?? null;
    }


    /**
     * Return full name of gene
     *
     * @@param
     * @return
     */
    public function getOmimlinkAttribute()
    {
		  return $this->omim ?? null;
    }


    /**
     * Return full name of gene
     *
     * @@param
     * @return
     */
    public function getTriploAssertionAttribute()
    {
		  return $this->triplo ?? null;
    }


    /**
     * Return full name of gene
     *
     * @@param
     * @return
     */
    public function getHaploAssertionAttribute()
    {
		  return $this->haplo ?? null;
    }


    /**
     * Return full name of gene
     *
     * @@param
     * @return
     */
    public function getResolvedDateAttribute()
    {
		  return $this->resolved ?? null;
	}


    /**
     * Map a dosage history attribute to a curation field.
     *
     */
    public static function mapHistory($attribute)
    {

        if (isset(self::$field_map[$attribute]))
            return self::$field_map[$attribute];

        return null;
    }


    /**
     * Map a dosage record to a curation
     *
     */
    public static function parser($message)
    {
        $data = json_decode($message->payload);

        $fields = $data->fields;

        // is this a gene or region
        if ($fields->issuetype->name == 'ISCA Gene Curation')
        {
            echo "key=" . $data->key . ", gene=" . $fields->customfield_10030 . "\n";

            if ($message->offset == 15557 || $message->offset == 17941 || $message->offset == 18996 || $message->offset == 19411)
            {
                var_dump($message);
            }

            // older messages wont have an hgnc_id and may be a previous symbol
            if (isset($fields->customfield_12230))
                $record = Gene::hgnc($fields->customfield_12230)->first();
            else
            {
                $record = Gene::name($fields->customfield_10030)->first();

                if ($record === null)
                {
                    // check previous names
                    $record = Gene::previous($fields->customfield_10030)->first();

                    if ($record === null)
                    {
                        echo "Gene not found " . $fields->customfield_10030 . "\n";
                        return;
                    }

                }
            }

            return;

            $morph_type = 'App\Models\Gene';

            /**
             * Need to work in all the miscRNA and other special genes
             */

            if ($record === null)
            {
                echo "parser:  gene not found issue $data->key \n";
                return;
            }
        }
        else if ($fields->issuetype->name == 'ISCA Region Curation')
        {
            return;
            $record = Region::source($data->key)-> first();

            // create if non existant
            if ($record === null)
            {
                $record = new Region(['type' => Region::TYPE_DOSAGE, 'name' => $fields->customfield_10202,
                                    'source_id' => $data->key, 'location' => $fields->customfield_10145,
                                    'grch37' => explode_genomic_coordinates($fields->customfield_10160 ?? null, $fields->customfield_10533 ?? null),
                                    'grch38' => explode_genomic_coordinates($fields->customfield_10532 ?? null),
                                    'curation_status' => null,
                                    'curation_activity' => null,
                                    'date_last_curated' => null, 'status' => Region::STATUS_INITIALIZED

                                ]);
                $record->save();
            }

            $morph_type = 'App\Models\Region';


        }
        else
        {
            // unknown dosage curation record
            die("parsed:  unknown record");
        }

        // eventually we'll want to reload without deletes.
        //$curation = Curation::source($message->topic_name . ':' . $message->key . ':' . $message->timestamp . ':' .  $message->offset)->first();

        $curation = null;

        if ($curation === null)
        {
            // see if there are existing versions
            $old = Curation::source("gene_dosage_raw")->sid($message->key)->orderBy('version', 'desc')->first();

            // build up the new curation record
            $curation = new Curation([
                            'type' => Curation::TYPE_DOSAGE_SENSITIVITY,
                            'type_string' => 'Dosage Sensitivity',
                            'subtype' => $fields->project->id,
                            'subtype_string' => $fields->project->key,
                            'group_id' => 0,
                            'sop_version' => 1,
                            'source' => $message->topic_name,
                            'source_uuid' => $data->key,
                            'assertion_uuid' => $message->topic_name . ':' . $data->key . ':' . $message->timestamp . ':' .  $message->offset,
                            'alternate_uuid' => $message->timestamp,
                            'affiliate_id' => null,
                            'affiliate_details' => ["id" => "", "name" => "Dosage Sensitivity Curation"],
                            'gene_hgnc_id' => $record->hgnc_id,
                            'gene_details' => [],
                            'title' => $fields->issuetype->name,
                            'summary' => $fields->summary,
                            'description' => $fields->description,
                            'comments' => null,
                            'conditions' => null,
                            'condition_details' => null,
                            'evidence' => null,
                            'evidence_details' => [
                                'targeting_decision_comment' => $fields->customfield_10196 ?? null,
                                "phenotype_comment" =>  $fields->customfield_10197 ?? null,
                                "gnomad_allele_frequency" => $fields->customfield_12530 ?? null,
                                "loss_phenotype_comments" => $fields->customfield_10198 ?? null,
                                "triplosensitive_phenotype_comments" => $fields->customfield_10199,
                                "breakpoint_type" => $fields->customfield_12531 ?? null,
                                "do_population_variants_overlap_this_region" => $fields->customfield_12533 ?? null,
                                "triplosensitive_phenotype_name" => $fields->customfield_11831 ?? null,
                                "reduced_penetrance_comment" => $fields->customfield_12246 ?? null,
                                "epic_link" => $fields->customfield_11431 ?? null,
                                "associated_with_reduced_penetrance" => $fields->customfield_12245 ?? null,
                                "loss_phenotype_name" => $fields->customfield_11830 ?? null,
                                "loss_phenotype_omim_id_specificity" => $fields->customfield_12247 ?? null,
                                "linked_issues" => $fields->issuelinks ?? null,
                                "on_180k_chip" => $fields->customfield_10164 ?? null,
                                "contains_known_hits_region" => $fields->customfield_12343 ?? null,
                                "loss_phenotype_omim_id" => $fields->customfield_10200 ?? null,
                                "number_of_probands_with_a_loss" => $fields->customfield_10167 ?? null,
                                "triplosensitive_phenotype_omim_id" => $fields->customfield_10201 ?? null,
                                "number_of_probands_with_a_gain" => $fields->customfield_10168 ?? null,
                                "targeting_decision_based_on" => $fields->customfield_10169->value ?? null,
                                "inheritance_pattern" => $fields->customfield_12330 ?? null,
                                "should_be_targeted" => $fields->customfield_10152->value ?? null,
                                "triplosensitive_phenotype_ontology_identifier" => $fields->customfield_11633 ?? null,
                                "loss_phenotype_ontology " => $fields->customfield_11630 ?? null,
                                "triplosensitive_phenotype_ontology" => $fields->customfield_11632 ?? null,
                                "loss phenotype_ontology_identifier" => $fields->customfield_11631 ?? null,
                                "cgd_inheritance" => $fields->customfield_11331 ?? null,
                                "cgd_condition" => $fields->customfield_11330 ?? null,
                                "cgd_references" => $fields->customfield_11332 ?? null,
                                "population_variants_description" => $fields->customfield_12536 ?? null,
                                "population_variants_frequency" => $fields->customfield_12535 ?? null,
                                "population_variants_data_source" => $fields->customfield_12537 ?? null,
                                "labels" => $fields->labels,
                                "resolution" => $fields->resolution->name ?? "ERROR",
                                'loss1' => [
                                    'evidence_id' => $fields->customfield_10183 ?? null,
                                    'evidence_type' => $fields->customfield_12331 ?? null,
                                    'description' => $fields->customfield_10184 ?? null,
                                ],
                                'loss2' => [
                                    'evidence_id' => $fields->customfield_10185 ?? null,
                                    'evidence_type' => '',
                                    'description' => $fields->customfield_10186 ?? null,
                                ],
                                'loss3' => [
                                    'evidence_id' => $fields->customfield_10187 ?? null,
                                    'evidence_type' => $fields->customfield_12333 ?? null,
                                    'description' => $fields->customfield_10188 ?? null,
                                ],
                                'loss4' => [
                                    'evidence_id' => $fields->customfield_12231 ?? null,
                                    'evidence_type' => $fields->customfield_12334 ?? null,
                                    'description' => $fields->customfield_12237 ?? null,
                                ],
                                'loss5' => [
                                    'evidence_id' => $fields->customfield_12232 ?? null,
                                    'evidence_type' => $fields->customfield_12335 ?? null,
                                    'description' => $fields->customfield_12238 ?? null,
                                ],
                                'loss6' => [
                                    'evidence_id' => $fields->customfield_12233 ?? null,
                                    'evidence_type' => $fields->customfield_12336 ?? null,
                                    'description' => $fields->customfield_12239 ?? null,
                                ],
                                'gain1' => [
                                    'evidence_id' => $fields->customfield_10189 ?? null,
                                    'evidence_type' => $fields->customfield_12337 ?? null,
                                    'description' => $fields->customfield_10190 ?? null,
                                ],
                                'gain2' => [
                                    'evidence_id' => $fields->customfield_10191 ?? null,
                                    'evidence_type' => $fields->customfield_12338 ?? null,
                                    'description' => $fields->customfield_10192 ?? null,
                                ],
                                'gain3' => [
                                    'evidence_id' => $fields->customfield_10193 ?? null,
                                    'evidence_type' => $fields->customfield_12339 ?? null,
                                    'description' => $fields->customfield_10194 ?? null,
                                ],
                                'gain4' => [
                                    'evidence_id' => $fields->customfield_12234 ?? null,
                                    'evidence_type' => $fields->customfield_12340 ?? null,
                                    'description' => $fields->customfield_12240 ?? null,
                                ],
                                'gain5' => [
                                    'evidence_id' => $fields->customfield_12235 ?? null,
                                    'evidence_type' => $fields->customfield_12341 ?? null,
                                    'description' => $fields->customfield_12241 ?? null,
                                ],
                                'gain6' => [
                                    'evidence_id' => $fields->customfield_12236 ?? null,
                                    'evidence_type' => $fields->customfield_12342 ?? null,
                                    'description' => $fields->customfield_12242 ?? null,
                                ],
                            ],
                            'scores' => [
                                'Haploinsufficiency score' => $fields->customfield_10165->value ?? null,
                                'Triplosensitivity score' => $fields->customfield_10166->value ?? null
                            ],
                            'score_details' => null,
                            'curators' => [
                                'reporter' => [ 'name' => $fields->reporter->displayName, 'email' => $fields->reporter->emailAddress ],
                                'creator' => [ 'name' => $fields->creator->displayName , 'email' => $fields->creator->emailAddress ],
                                'assingee' => [ 'name' => $fields->assignee->displayName ?? null, 'email' => $fields->assignee->emailAddress ?? null ]
                            ],
                            'published' => !empty($fields->resolutiondate),
                            'animal_model_only' => false,
                            'contributors' => [],
                            'events' => [
                                'created' => $fields->created,
                                'updated' => $fields->updated ?? null,
                                'resolved' => $fields->resolutiondate ?? null
                            ],
                            'version' => ($old->version ?? 0) + 1,
                            'status' => Curation::map_activity_status($fields->status->name, Curation::TYPE_DOSAGE_SENSITIVITY)

                          //  'curation_class' => $fields->issuetype->name,
                          //  'curatable_type' => $morph_type,
                          //  'curatable_id' => $record->id,

                          //  'is_closed' => !empty($fields->resolutiondate),



                         //   'resolution' => $fields->resolution->name  ?? null,
                        ]);
//dd($curation);
            //$record->curations()->save($curation);
            $curation->save();

            // unpublish the old record if necessary
            if ($curation->published && ($old->published ?? false))
                $old->update(['published' => false]);

        }
//dd($curation);
        // Create or attach named labels
        /*foreach ($fields->labels as $label)
        {
            $tag = Tag::dosage()->label($label)->first();

            if ($tag === null)
            {
                $tag = new Tag(['type' => Tag::TYPE_DOSAGE, 'label' => $label, 'status' => Tag::STATUS_INITIALIZED]);

                $tag->save();
            }

            if ($curation->tags()->where('tags.id', $tag->id)->doesntExist())
                $curation->tags()->attach($tag->id);
        }*/

        // Create or attach evidence
        /*
        foreach(self::$pmid_fields as $key => $value)
        {
            if (isset($fields->{$value['evidence_id']}))
            {
                $pmid = $fields->{$value['evidence_id']};

                //$pmid = normalize_pmid();

                $subtype = (strpos($key, 'Loss') === false ? Evidence::SUBTYPE_GAIN :
                                                             Evidence::SUBTYPE_LOSS);

                if ($subtype == Evidence::SUBTYPE_LOSS)
                    $evidence = $curation->evidences()->eid($pmid)->loss()->first();
                else
                    $evidence = $curation->evidences()->eid($pmid)->gain()->first();

                if ($evidence === null)
                {
                    $evidence = new Evidence(['type' => Evidence::TYPE_DOSAGE,
                                            'subtype' => $subtype,
                                            'is_pmid' => true,
                                            'evidence_id' => $pmid,
                                            'evidence_type' => $fields->{$value['evidence_type']}->value ?? null,
                                            'description' => $fields->{$value['description']} ?? null,
                                            'status' => Evidence::STATUS_INITIALIZED
                    ]);

                    $curation->evidences()->save($evidence);
                }

            }

        */

        // update status
        /*
        if ($morph_type == 'App\Models\Gene' && $curation->is_published && $curation->resolution == 'Complete')
        {
            Gene::where('id', $record->id)->update(['curation_status' => ['dosage_sensitivity' => true], 'date_last_curated' => Carbon
            ::parse($fields->updated ?? null)]);
        }
        */

        //dd($curation);

    }
}
