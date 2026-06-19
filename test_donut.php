<?php
require __DIR__.'/vendor/autoload.php';
 = require_once __DIR__.'/bootstrap/app.php';
->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

 = App\Models\StatsData\StatsDataDocument::find('532d019a-b653-4eb9-a0e9-184847935d4a');
 = [
    'specVersion' => 2,
    'sources' => [['alias' => 's', 'sourceId' => '2927e4a1-7b4a-4a8f-a21c-a4a27f5b8385']],
    'select' => [['kind' => 'from', 'label' => 'Colonne 1', 'from' => 's.country']],
    'where' => []
];

 = app(App\Domain\StatsData\Services\StatsDataQueryEngine::class);
 = ->execute(, );
echo 'Total rows: ' . count() . PHP_EOL;
 = [];
foreach ( as ) {
     = ['Colonne 1'] ?? 'unknown';
    [] = ([] ?? 0) + 1;
}
arsort();
echo 'Top 10 countries:' . PHP_EOL;
foreach (array_slice(, 0, 10, true) as  => ) {
    echo 
