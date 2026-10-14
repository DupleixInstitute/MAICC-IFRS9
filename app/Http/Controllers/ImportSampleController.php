<?php

namespace App\Http\Controllers;

use App\Imports\CreditLossDataImport;
use App\Models\CreditLossDefinition;
use ReflectionClass;

/**
 * The older sample-file route (import-samples.show), kept for the screens and
 * links that use it; the files themselves now come from the import spec
 * registry (App\Support\Imports\ImportSpecs), with a heading row and
 * illustrative rows. creditLossAliases() stays here: it reads the aliases
 * CreditLossDataImport accepts from the importer itself, for the registry
 * and the Credit Loss Data import screen.
 */
class ImportSampleController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * The sample file of a kind, now written from the import spec registry
     * (App\Support\Imports\ImportSpecs, 13 October 2026): the kinds below are
     * spec keys of the same name, so this route and import-templates.download
     * serve the same file.
     */
    public function show(string $kind)
    {
        abort_unless(in_array($kind, ['credit-loss-data', 'sicr-groups', 'sicr-items', 'rbm-policy-rate', 'imf-weo', 'macro-series'], true), 404);

        return app(ImportTemplateController::class)->template($kind, app(\App\Support\Imports\ImportTemplateBuilder::class));
    }

    /**
     * The aliases CreditLossDataImport accepts for each definition code, read
     * from the importer itself so the screen and the sample never drift from it.
     *
     * @return array<string, list<string>>
     */
    public static function creditLossAliases(): array
    {
        $class = new ReflectionClass(CreditLossDataImport::class);
        $importer = $class->newInstanceWithoutConstructor();
        $method = $class->getMethod('getAliasesForDefinition');
        $method->setAccessible(true);

        return CreditLossDefinition::orderBy('id')->pluck('code')
            ->mapWithKeys(fn ($code) => [$code => array_values($method->invoke($importer, $code))])
            ->all();
    }
}
