<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Variant;
use App\Gene;
use App\Disease;

class UpdateErepo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:erepo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update variant pathogenicity data from Erepo';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
      echo "Updating Variant Pathogenicity data from Erepo ...";


      try {

        $results = file_get_contents("http://erepo.genome.network/evrepo/api/interpretations?matchLogic=and&matchMode=keyword&matchLimit=all");

      } catch (\Exception $e) {

        echo "\n(E001) Error retreiving erepo data\n";
        exit;
      }

      $dd = json_decode($results);

      Variant::query()->forceDelete();

      foreach($dd->variantInterpretations as $variant)
      {
        //echo $variant->{'@id'} . " " . $variant->guidelines[0]["outcome"]["label"] . "\n";
        Variant::create(['iri' => $variant->{'@id'}, 'variant_id' => $variant->variationId,
                    'caid' => $variant->caid,
                    'condition' => $variant->condition,
                    'published_date' => $variant->publishedDate ?? null,
                    'evidence_links' => $variant->evidenceLinks,
                    'gene' => $variant->gene,
                    'guidelines' => $variant->guidelines,
                    'hgvs' => $variant->hgvs]);

        // update the main gene table
        $gene = Gene::name($variant->gene->label)->first();

        if ($gene !== null)
        {
            $activity = $gene->activity;
            $activity['varpath'] = true;
            $gene->activity = $activity;
            $gene->save();
        }

        $disease = Disease::curie($variant->condition->{'@id'})->first();

        if ($disease !== null)
        {
            $activity = $disease->curation_activities;
            if (empty($activity) || !isset($activity['dosage']))
                $activity = ['dosage' => false, 'validity' => false, 'actionability' => 'false'];
            $activity['varpath'] = true;
            $disease->curation_activities = $activity;
            $disease->save();
        }
      }

      echo "DONE\n";

    }
}
