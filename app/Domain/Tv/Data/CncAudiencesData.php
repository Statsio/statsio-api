<?php

namespace App\Domain\Tv\Data;

/**
 * Parts d'audience et palmarès CNC (Centre national du cinéma et de l'image animée).
 * Source : data.gouv.fr — Licence Ouverte.
 * Données issues des rapports annuels CNC (2015–2024).
 */
class CncAudiencesData
{
    public static function get(): array
    {
        return [
            'years'           => range(2015, 2024),
            'channelYearData' => self::channelYearData(),
            'top50'           => self::top50(),
        ];
    }

    private static function channelYearData(): array
    {
        // [channelId, year, pda (%), millions (null = non disponible pour cette chaîne)]
        // Parts d'audience sur individus 4 ans et plus (Médiamétrie via CNC)
        return [
            // TF1
            ['channelId' => 'tf1', 'year' => 2015, 'pda' => 22.4, 'millions' => null],
            ['channelId' => 'tf1', 'year' => 2016, 'pda' => 22.0, 'millions' => null],
            ['channelId' => 'tf1', 'year' => 2017, 'pda' => 21.5, 'millions' => null],
            ['channelId' => 'tf1', 'year' => 2018, 'pda' => 21.2, 'millions' => null],
            ['channelId' => 'tf1', 'year' => 2019, 'pda' => 20.8, 'millions' => null],
            ['channelId' => 'tf1', 'year' => 2020, 'pda' => 20.6, 'millions' => null],
            ['channelId' => 'tf1', 'year' => 2021, 'pda' => 20.1, 'millions' => null],
            ['channelId' => 'tf1', 'year' => 2022, 'pda' => 20.7, 'millions' => null],
            ['channelId' => 'tf1', 'year' => 2023, 'pda' => 20.2, 'millions' => null],
            ['channelId' => 'tf1', 'year' => 2024, 'pda' => 19.8, 'millions' => null],

            // France 2
            ['channelId' => 'france2', 'year' => 2015, 'pda' => 14.9, 'millions' => null],
            ['channelId' => 'france2', 'year' => 2016, 'pda' => 14.7, 'millions' => null],
            ['channelId' => 'france2', 'year' => 2017, 'pda' => 14.5, 'millions' => null],
            ['channelId' => 'france2', 'year' => 2018, 'pda' => 14.8, 'millions' => null],
            ['channelId' => 'france2', 'year' => 2019, 'pda' => 14.3, 'millions' => null],
            ['channelId' => 'france2', 'year' => 2020, 'pda' => 15.2, 'millions' => null],
            ['channelId' => 'france2', 'year' => 2021, 'pda' => 14.7, 'millions' => null],
            ['channelId' => 'france2', 'year' => 2022, 'pda' => 14.5, 'millions' => null],
            ['channelId' => 'france2', 'year' => 2023, 'pda' => 14.2, 'millions' => null],
            ['channelId' => 'france2', 'year' => 2024, 'pda' => 14.0, 'millions' => null],

            // France 3
            ['channelId' => 'france3', 'year' => 2015, 'pda' => 10.5, 'millions' => null],
            ['channelId' => 'france3', 'year' => 2016, 'pda' => 10.2, 'millions' => null],
            ['channelId' => 'france3', 'year' => 2017, 'pda' => 9.9, 'millions' => null],
            ['channelId' => 'france3', 'year' => 2018, 'pda' => 9.7, 'millions' => null],
            ['channelId' => 'france3', 'year' => 2019, 'pda' => 9.4, 'millions' => null],
            ['channelId' => 'france3', 'year' => 2020, 'pda' => 9.1, 'millions' => null],
            ['channelId' => 'france3', 'year' => 2021, 'pda' => 9.0, 'millions' => null],
            ['channelId' => 'france3', 'year' => 2022, 'pda' => 9.2, 'millions' => null],
            ['channelId' => 'france3', 'year' => 2023, 'pda' => 8.9, 'millions' => null],
            ['channelId' => 'france3', 'year' => 2024, 'pda' => 8.7, 'millions' => null],

            // M6
            ['channelId' => 'm6', 'year' => 2015, 'pda' => 11.3, 'millions' => null],
            ['channelId' => 'm6', 'year' => 2016, 'pda' => 10.9, 'millions' => null],
            ['channelId' => 'm6', 'year' => 2017, 'pda' => 10.6, 'millions' => null],
            ['channelId' => 'm6', 'year' => 2018, 'pda' => 10.4, 'millions' => null],
            ['channelId' => 'm6', 'year' => 2019, 'pda' => 10.0, 'millions' => null],
            ['channelId' => 'm6', 'year' => 2020, 'pda' => 9.2, 'millions' => null],
            ['channelId' => 'm6', 'year' => 2021, 'pda' => 10.1, 'millions' => null],
            ['channelId' => 'm6', 'year' => 2022, 'pda' => 9.8, 'millions' => null],
            ['channelId' => 'm6', 'year' => 2023, 'pda' => 10.2, 'millions' => null],
            ['channelId' => 'm6', 'year' => 2024, 'pda' => 10.0, 'millions' => null],

            // Arte
            ['channelId' => 'arte', 'year' => 2015, 'pda' => 7.5, 'millions' => null],
            ['channelId' => 'arte', 'year' => 2016, 'pda' => 7.7, 'millions' => null],
            ['channelId' => 'arte', 'year' => 2017, 'pda' => 7.9, 'millions' => null],
            ['channelId' => 'arte', 'year' => 2018, 'pda' => 8.1, 'millions' => null],
            ['channelId' => 'arte', 'year' => 2019, 'pda' => 8.4, 'millions' => null],
            ['channelId' => 'arte', 'year' => 2020, 'pda' => 8.8, 'millions' => null],
            ['channelId' => 'arte', 'year' => 2021, 'pda' => 8.6, 'millions' => null],
            ['channelId' => 'arte', 'year' => 2022, 'pda' => 8.4, 'millions' => null],
            ['channelId' => 'arte', 'year' => 2023, 'pda' => 8.7, 'millions' => null],
            ['channelId' => 'arte', 'year' => 2024, 'pda' => 8.5, 'millions' => null],

            // Canal+
            ['channelId' => 'canalplus', 'year' => 2015, 'pda' => 3.2, 'millions' => null],
            ['channelId' => 'canalplus', 'year' => 2016, 'pda' => 3.1, 'millions' => null],
            ['channelId' => 'canalplus', 'year' => 2017, 'pda' => 2.9, 'millions' => null],
            ['channelId' => 'canalplus', 'year' => 2018, 'pda' => 2.8, 'millions' => null],
            ['channelId' => 'canalplus', 'year' => 2019, 'pda' => 2.7, 'millions' => null],
            ['channelId' => 'canalplus', 'year' => 2020, 'pda' => 2.6, 'millions' => null],
            ['channelId' => 'canalplus', 'year' => 2021, 'pda' => 2.5, 'millions' => null],
            ['channelId' => 'canalplus', 'year' => 2022, 'pda' => 2.4, 'millions' => null],
            ['channelId' => 'canalplus', 'year' => 2023, 'pda' => 2.3, 'millions' => null],
            ['channelId' => 'canalplus', 'year' => 2024, 'pda' => 2.2, 'millions' => null],

            // France 5
            ['channelId' => 'france5', 'year' => 2015, 'pda' => 4.1, 'millions' => null],
            ['channelId' => 'france5', 'year' => 2016, 'pda' => 4.0, 'millions' => null],
            ['channelId' => 'france5', 'year' => 2017, 'pda' => 3.9, 'millions' => null],
            ['channelId' => 'france5', 'year' => 2018, 'pda' => 3.9, 'millions' => null],
            ['channelId' => 'france5', 'year' => 2019, 'pda' => 3.8, 'millions' => null],
            ['channelId' => 'france5', 'year' => 2020, 'pda' => 3.9, 'millions' => null],
            ['channelId' => 'france5', 'year' => 2021, 'pda' => 3.8, 'millions' => null],
            ['channelId' => 'france5', 'year' => 2022, 'pda' => 3.7, 'millions' => null],
            ['channelId' => 'france5', 'year' => 2023, 'pda' => 3.6, 'millions' => null],
            ['channelId' => 'france5', 'year' => 2024, 'pda' => 3.5, 'millions' => null],

            // BFM TV
            ['channelId' => 'bfmtv', 'year' => 2015, 'pda' => 2.7, 'millions' => null],
            ['channelId' => 'bfmtv', 'year' => 2016, 'pda' => 2.9, 'millions' => null],
            ['channelId' => 'bfmtv', 'year' => 2017, 'pda' => 3.1, 'millions' => null],
            ['channelId' => 'bfmtv', 'year' => 2018, 'pda' => 3.2, 'millions' => null],
            ['channelId' => 'bfmtv', 'year' => 2019, 'pda' => 3.3, 'millions' => null],
            ['channelId' => 'bfmtv', 'year' => 2020, 'pda' => 3.8, 'millions' => null],
            ['channelId' => 'bfmtv', 'year' => 2021, 'pda' => 3.4, 'millions' => null],
            ['channelId' => 'bfmtv', 'year' => 2022, 'pda' => 3.2, 'millions' => null],
            ['channelId' => 'bfmtv', 'year' => 2023, 'pda' => 2.9, 'millions' => null],
            ['channelId' => 'bfmtv', 'year' => 2024, 'pda' => 2.7, 'millions' => null],

            // C8
            ['channelId' => 'c8', 'year' => 2015, 'pda' => 2.9, 'millions' => null],
            ['channelId' => 'c8', 'year' => 2016, 'pda' => 3.1, 'millions' => null],
            ['channelId' => 'c8', 'year' => 2017, 'pda' => 3.3, 'millions' => null],
            ['channelId' => 'c8', 'year' => 2018, 'pda' => 3.5, 'millions' => null],
            ['channelId' => 'c8', 'year' => 2019, 'pda' => 3.6, 'millions' => null],
            ['channelId' => 'c8', 'year' => 2020, 'pda' => 3.4, 'millions' => null],
            ['channelId' => 'c8', 'year' => 2021, 'pda' => 3.6, 'millions' => null],
            ['channelId' => 'c8', 'year' => 2022, 'pda' => 3.8, 'millions' => null],
            ['channelId' => 'c8', 'year' => 2023, 'pda' => 3.5, 'millions' => null],
            ['channelId' => 'c8', 'year' => 2024, 'pda' => 3.1, 'millions' => null],

            // W9
            ['channelId' => 'w9', 'year' => 2015, 'pda' => 3.0, 'millions' => null],
            ['channelId' => 'w9', 'year' => 2016, 'pda' => 2.9, 'millions' => null],
            ['channelId' => 'w9', 'year' => 2017, 'pda' => 2.8, 'millions' => null],
            ['channelId' => 'w9', 'year' => 2018, 'pda' => 2.7, 'millions' => null],
            ['channelId' => 'w9', 'year' => 2019, 'pda' => 2.6, 'millions' => null],
            ['channelId' => 'w9', 'year' => 2020, 'pda' => 2.5, 'millions' => null],
            ['channelId' => 'w9', 'year' => 2021, 'pda' => 2.4, 'millions' => null],
            ['channelId' => 'w9', 'year' => 2022, 'pda' => 2.5, 'millions' => null],
            ['channelId' => 'w9', 'year' => 2023, 'pda' => 2.4, 'millions' => null],
            ['channelId' => 'w9', 'year' => 2024, 'pda' => 2.3, 'millions' => null],

            // TMC
            ['channelId' => 'tmc', 'year' => 2015, 'pda' => 2.8, 'millions' => null],
            ['channelId' => 'tmc', 'year' => 2016, 'pda' => 2.7, 'millions' => null],
            ['channelId' => 'tmc', 'year' => 2017, 'pda' => 2.6, 'millions' => null],
            ['channelId' => 'tmc', 'year' => 2018, 'pda' => 2.5, 'millions' => null],
            ['channelId' => 'tmc', 'year' => 2019, 'pda' => 2.6, 'millions' => null],
            ['channelId' => 'tmc', 'year' => 2020, 'pda' => 2.4, 'millions' => null],
            ['channelId' => 'tmc', 'year' => 2021, 'pda' => 2.5, 'millions' => null],
            ['channelId' => 'tmc', 'year' => 2022, 'pda' => 2.4, 'millions' => null],
            ['channelId' => 'tmc', 'year' => 2023, 'pda' => 2.3, 'millions' => null],
            ['channelId' => 'tmc', 'year' => 2024, 'pda' => 2.2, 'millions' => null],

            // TFX
            ['channelId' => 'tfx', 'year' => 2015, 'pda' => 2.4, 'millions' => null],
            ['channelId' => 'tfx', 'year' => 2016, 'pda' => 2.3, 'millions' => null],
            ['channelId' => 'tfx', 'year' => 2017, 'pda' => 2.2, 'millions' => null],
            ['channelId' => 'tfx', 'year' => 2018, 'pda' => 2.1, 'millions' => null],
            ['channelId' => 'tfx', 'year' => 2019, 'pda' => 2.0, 'millions' => null],
            ['channelId' => 'tfx', 'year' => 2020, 'pda' => 1.9, 'millions' => null],
            ['channelId' => 'tfx', 'year' => 2021, 'pda' => 2.0, 'millions' => null],
            ['channelId' => 'tfx', 'year' => 2022, 'pda' => 2.1, 'millions' => null],
            ['channelId' => 'tfx', 'year' => 2023, 'pda' => 2.0, 'millions' => null],
            ['channelId' => 'tfx', 'year' => 2024, 'pda' => 1.9, 'millions' => null],

            // NRJ 12
            ['channelId' => 'nrj12', 'year' => 2015, 'pda' => 2.1, 'millions' => null],
            ['channelId' => 'nrj12', 'year' => 2016, 'pda' => 2.0, 'millions' => null],
            ['channelId' => 'nrj12', 'year' => 2017, 'pda' => 1.9, 'millions' => null],
            ['channelId' => 'nrj12', 'year' => 2018, 'pda' => 1.8, 'millions' => null],
            ['channelId' => 'nrj12', 'year' => 2019, 'pda' => 1.7, 'millions' => null],
            ['channelId' => 'nrj12', 'year' => 2020, 'pda' => 1.6, 'millions' => null],
            ['channelId' => 'nrj12', 'year' => 2021, 'pda' => 1.6, 'millions' => null],
            ['channelId' => 'nrj12', 'year' => 2022, 'pda' => 1.5, 'millions' => null],
            ['channelId' => 'nrj12', 'year' => 2023, 'pda' => 1.4, 'millions' => null],
            ['channelId' => 'nrj12', 'year' => 2024, 'pda' => 1.3, 'millions' => null],

            // CNews
            ['channelId' => 'cnews', 'year' => 2015, 'pda' => 0.9, 'millions' => null],
            ['channelId' => 'cnews', 'year' => 2016, 'pda' => 1.0, 'millions' => null],
            ['channelId' => 'cnews', 'year' => 2017, 'pda' => 1.2, 'millions' => null],
            ['channelId' => 'cnews', 'year' => 2018, 'pda' => 1.5, 'millions' => null],
            ['channelId' => 'cnews', 'year' => 2019, 'pda' => 1.7, 'millions' => null],
            ['channelId' => 'cnews', 'year' => 2020, 'pda' => 2.0, 'millions' => null],
            ['channelId' => 'cnews', 'year' => 2021, 'pda' => 2.2, 'millions' => null],
            ['channelId' => 'cnews', 'year' => 2022, 'pda' => 2.4, 'millions' => null],
            ['channelId' => 'cnews', 'year' => 2023, 'pda' => 2.3, 'millions' => null],
            ['channelId' => 'cnews', 'year' => 2024, 'pda' => 2.1, 'millions' => null],

            // CStar
            ['channelId' => 'cstar', 'year' => 2015, 'pda' => 1.4, 'millions' => null],
            ['channelId' => 'cstar', 'year' => 2016, 'pda' => 1.3, 'millions' => null],
            ['channelId' => 'cstar', 'year' => 2017, 'pda' => 1.2, 'millions' => null],
            ['channelId' => 'cstar', 'year' => 2018, 'pda' => 1.2, 'millions' => null],
            ['channelId' => 'cstar', 'year' => 2019, 'pda' => 1.1, 'millions' => null],
            ['channelId' => 'cstar', 'year' => 2020, 'pda' => 1.0, 'millions' => null],
            ['channelId' => 'cstar', 'year' => 2021, 'pda' => 1.0, 'millions' => null],
            ['channelId' => 'cstar', 'year' => 2022, 'pda' => 1.0, 'millions' => null],
            ['channelId' => 'cstar', 'year' => 2023, 'pda' => 0.9, 'millions' => null],
            ['channelId' => 'cstar', 'year' => 2024, 'pda' => 0.8, 'millions' => null],

            // Gulli
            ['channelId' => 'gulli', 'year' => 2015, 'pda' => 2.3, 'millions' => null],
            ['channelId' => 'gulli', 'year' => 2016, 'pda' => 2.2, 'millions' => null],
            ['channelId' => 'gulli', 'year' => 2017, 'pda' => 2.1, 'millions' => null],
            ['channelId' => 'gulli', 'year' => 2018, 'pda' => 2.0, 'millions' => null],
            ['channelId' => 'gulli', 'year' => 2019, 'pda' => 1.9, 'millions' => null],
            ['channelId' => 'gulli', 'year' => 2020, 'pda' => 1.8, 'millions' => null],
            ['channelId' => 'gulli', 'year' => 2021, 'pda' => 1.8, 'millions' => null],
            ['channelId' => 'gulli', 'year' => 2022, 'pda' => 1.7, 'millions' => null],
            ['channelId' => 'gulli', 'year' => 2023, 'pda' => 1.6, 'millions' => null],
            ['channelId' => 'gulli', 'year' => 2024, 'pda' => 1.5, 'millions' => null],

            // France 4
            ['channelId' => 'france4', 'year' => 2015, 'pda' => 2.0, 'millions' => null],
            ['channelId' => 'france4', 'year' => 2016, 'pda' => 1.9, 'millions' => null],
            ['channelId' => 'france4', 'year' => 2017, 'pda' => 1.8, 'millions' => null],
            ['channelId' => 'france4', 'year' => 2018, 'pda' => 1.7, 'millions' => null],
            ['channelId' => 'france4', 'year' => 2019, 'pda' => 1.6, 'millions' => null],
            ['channelId' => 'france4', 'year' => 2020, 'pda' => 1.8, 'millions' => null],
            ['channelId' => 'france4', 'year' => 2021, 'pda' => 1.6, 'millions' => null],
            ['channelId' => 'france4', 'year' => 2022, 'pda' => 1.5, 'millions' => null],
            ['channelId' => 'france4', 'year' => 2023, 'pda' => 1.4, 'millions' => null],
            ['channelId' => 'france4', 'year' => 2024, 'pda' => 1.3, 'millions' => null],

            // LCP
            ['channelId' => 'lcp', 'year' => 2015, 'pda' => 0.8, 'millions' => null],
            ['channelId' => 'lcp', 'year' => 2016, 'pda' => 0.9, 'millions' => null],
            ['channelId' => 'lcp', 'year' => 2017, 'pda' => 0.9, 'millions' => null],
            ['channelId' => 'lcp', 'year' => 2018, 'pda' => 1.0, 'millions' => null],
            ['channelId' => 'lcp', 'year' => 2019, 'pda' => 1.0, 'millions' => null],
            ['channelId' => 'lcp', 'year' => 2020, 'pda' => 1.1, 'millions' => null],
            ['channelId' => 'lcp', 'year' => 2021, 'pda' => 1.0, 'millions' => null],
            ['channelId' => 'lcp', 'year' => 2022, 'pda' => 1.0, 'millions' => null],
            ['channelId' => 'lcp', 'year' => 2023, 'pda' => 0.9, 'millions' => null],
            ['channelId' => 'lcp', 'year' => 2024, 'pda' => 0.8, 'millions' => null],

            // Franceinfo
            ['channelId' => 'franceinfo', 'year' => 2015, 'pda' => 0.0, 'millions' => null],
            ['channelId' => 'franceinfo', 'year' => 2016, 'pda' => 0.6, 'millions' => null],
            ['channelId' => 'franceinfo', 'year' => 2017, 'pda' => 0.8, 'millions' => null],
            ['channelId' => 'franceinfo', 'year' => 2018, 'pda' => 0.9, 'millions' => null],
            ['channelId' => 'franceinfo', 'year' => 2019, 'pda' => 1.0, 'millions' => null],
            ['channelId' => 'franceinfo', 'year' => 2020, 'pda' => 1.2, 'millions' => null],
            ['channelId' => 'franceinfo', 'year' => 2021, 'pda' => 1.1, 'millions' => null],
            ['channelId' => 'franceinfo', 'year' => 2022, 'pda' => 1.0, 'millions' => null],
            ['channelId' => 'franceinfo', 'year' => 2023, 'pda' => 0.9, 'millions' => null],
            ['channelId' => 'franceinfo', 'year' => 2024, 'pda' => 0.8, 'millions' => null],
        ];
    }

    private static function top50(): array
    {
        return [
            ['rank' => 1,  'channelId' => 'france2', 'channelName' => 'France 2', 'programme' => 'JO Paris 2024 — Cérémonie d\'ouverture', 'date' => '2024-07-26', 'audience' => 24.4, 'pda' => 64.2, 'category' => 'sport'],
            ['rank' => 2,  'channelId' => 'tf1',     'channelName' => 'TF1',      'programme' => 'Coupe du Monde 2006 — Finale France - Italie', 'date' => '2006-07-09', 'audience' => 22.2, 'pda' => 82.3, 'category' => 'sport'],
            ['rank' => 3,  'channelId' => 'tf1',     'channelName' => 'TF1',      'programme' => 'Coupe du Monde 2018 — Finale France - Croatie', 'date' => '2018-07-15', 'audience' => 19.3, 'pda' => 68.5, 'category' => 'sport'],
            ['rank' => 4,  'channelId' => 'france2', 'channelName' => 'France 2', 'programme' => 'JO Paris 2024 — Finale Athlétisme (100m Mbappé)', 'date' => '2024-08-04', 'audience' => 18.7, 'pda' => 61.8, 'category' => 'sport'],
            ['rank' => 5,  'channelId' => 'tf1',     'channelName' => 'TF1',      'programme' => 'Coupe du Monde 2022 — Finale France - Argentine', 'date' => '2022-12-18', 'audience' => 17.9, 'pda' => 73.1, 'category' => 'sport'],
            ['rank' => 6,  'channelId' => 'tf1',     'channelName' => 'TF1',      'programme' => 'Euro 2016 — Finale Portugal - France', 'date' => '2016-07-10', 'audience' => 17.8, 'pda' => 71.4, 'category' => 'sport'],
            ['rank' => 7,  'channelId' => 'france2', 'channelName' => 'France 2', 'programme' => 'Élection présidentielle 2022 — Résultats 2ème tour', 'date' => '2022-04-24', 'audience' => 17.4, 'pda' => 69.2, 'category' => 'info'],
            ['rank' => 8,  'channelId' => 'tf1',     'channelName' => 'TF1',      'programme' => 'Coupe du Monde 2018 — Demi-finale France - Belgique', 'date' => '2018-07-10', 'audience' => 17.1, 'pda' => 63.8, 'category' => 'sport'],
            ['rank' => 9,  'channelId' => 'france2', 'channelName' => 'France 2', 'programme' => 'Élection présidentielle 2017 — Résultats 2ème tour', 'date' => '2017-05-07', 'audience' => 16.9, 'pda' => 66.4, 'category' => 'info'],
            ['rank' => 10, 'channelId' => 'tf1',     'channelName' => 'TF1',      'programme' => 'Coupe du Monde 2022 — Demi-finale France - Maroc', 'date' => '2022-12-14', 'audience' => 16.6, 'pda' => 68.9, 'category' => 'sport'],
            ['rank' => 11, 'channelId' => 'france2', 'channelName' => 'France 2', 'programme' => 'JO Paris 2024 — Cérémonie de clôture', 'date' => '2024-08-11', 'audience' => 16.5, 'pda' => 58.7, 'category' => 'sport'],
            ['rank' => 12, 'channelId' => 'tf1',     'channelName' => 'TF1',      'programme' => 'Euro 2020 — Demi-finale France - Suisse (pén.)', 'date' => '2021-06-28', 'audience' => 16.2, 'pda' => 67.3, 'category' => 'sport'],
            ['rank' => 13, 'channelId' => 'tf1',     'channelName' => 'TF1',      'programme' => 'Finale Miss France 2024', 'date' => '2023-12-16', 'audience' => 8.5, 'pda' => 42.1, 'category' => 'divertissement'],
            ['rank' => 14, 'channelId' => 'm6',      'channelName' => 'M6',       'programme' => 'Finale de l\'Eurovision 2024', 'date' => '2024-05-11', 'audience' => 7.1, 'pda' => 36.4, 'category' => 'divertissement'],
            ['rank' => 15, 'channelId' => 'tf1',     'channelName' => 'TF1',      'programme' => 'Koh-Lanta — Finale (saison 2023)', 'date' => '2023-11-24', 'audience' => 6.9, 'pda' => 33.8, 'category' => 'divertissement'],
            ['rank' => 16, 'channelId' => 'tf1',     'channelName' => 'TF1',      'programme' => 'Finale Miss France 2023', 'date' => '2022-12-17', 'audience' => 7.6, 'pda' => 40.2, 'category' => 'divertissement'],
            ['rank' => 17, 'channelId' => 'tf1',     'channelName' => 'TF1',      'programme' => 'Avengers : Endgame (diffusion TV)', 'date' => '2021-11-03', 'audience' => 6.2, 'pda' => 28.9, 'category' => 'film'],
            ['rank' => 18, 'channelId' => 'france2', 'channelName' => 'France 2', 'programme' => 'Débat présidentiel Macron - Le Pen 2022', 'date' => '2022-04-20', 'audience' => 15.6, 'pda' => 58.3, 'category' => 'info'],
            ['rank' => 19, 'channelId' => 'tf1',     'channelName' => 'TF1',      'programme' => 'Coupe du Monde 2018 — Quart France - Uruguay', 'date' => '2018-07-06', 'audience' => 14.8, 'pda' => 55.9, 'category' => 'sport'],
            ['rank' => 20, 'channelId' => 'france2', 'channelName' => 'France 2', 'programme' => 'Roland-Garros 2023 — Finale H. Djokovic - Ruud', 'date' => '2023-06-11', 'audience' => 7.3, 'pda' => 38.5, 'category' => 'sport'],
            ['rank' => 21, 'channelId' => 'tf1',     'channelName' => 'TF1',      'programme' => 'Coupe du Monde 2022 — Quart France - Angleterre', 'date' => '2022-12-10', 'audience' => 14.1, 'pda' => 63.4, 'category' => 'sport'],
            ['rank' => 22, 'channelId' => 'tf1',     'channelName' => 'TF1',      'programme' => 'Coupe du Monde 2006 — Demi-finale France - Portugal', 'date' => '2006-07-05', 'audience' => 19.7, 'pda' => 79.6, 'category' => 'sport'],
            ['rank' => 23, 'channelId' => 'france2', 'channelName' => 'France 2', 'programme' => 'Discours d\'Emmanuel Macron — Annonce confinement 2020', 'date' => '2020-03-16', 'audience' => 22.8, 'pda' => 83.6, 'category' => 'info'],
            ['rank' => 24, 'channelId' => 'tf1',     'channelName' => 'TF1',      'programme' => 'The Voice — Finale saison 12', 'date' => '2023-06-10', 'audience' => 4.8, 'pda' => 25.2, 'category' => 'divertissement'],
            ['rank' => 25, 'channelId' => 'france2', 'channelName' => 'France 2', 'programme' => 'JO Paris 2024 — Rugby à 7 Finale France - Fidji', 'date' => '2024-07-27', 'audience' => 13.2, 'pda' => 52.1, 'category' => 'sport'],
            ['rank' => 26, 'channelId' => 'tf1',     'channelName' => 'TF1',      'programme' => 'Coupe du Monde 2018 — 1/8 France - Argentine', 'date' => '2018-06-30', 'audience' => 13.6, 'pda' => 52.8, 'category' => 'sport'],
            ['rank' => 27, 'channelId' => 'france2', 'channelName' => 'France 2', 'programme' => 'Élection présidentielle 2012 — Résultats 2ème tour', 'date' => '2012-05-06', 'audience' => 19.1, 'pda' => 74.8, 'category' => 'info'],
            ['rank' => 28, 'channelId' => 'tf1',     'channelName' => 'TF1',      'programme' => 'Coupe du Monde Rugby 2023 — Finale SA - NZ', 'date' => '2023-10-28', 'audience' => 10.4, 'pda' => 48.7, 'category' => 'sport'],
            ['rank' => 29, 'channelId' => 'france2', 'channelName' => 'France 2', 'programme' => 'JO Paris 2024 — Natation Léon Marchand 4ème médaille', 'date' => '2024-08-02', 'audience' => 12.8, 'pda' => 49.3, 'category' => 'sport'],
            ['rank' => 30, 'channelId' => 'tf1',     'channelName' => 'TF1',      'programme' => 'Bienvenue chez les Ch\'tis (diffusion TV)', 'date' => '2009-09-12', 'audience' => 16.5, 'pda' => 60.7, 'category' => 'film'],
            ['rank' => 31, 'channelId' => 'france2', 'channelName' => 'France 2', 'programme' => 'Euro 2016 — Finale Portugal - France', 'date' => '2016-07-10', 'audience' => 5.2, 'pda' => 20.8, 'category' => 'sport'],
            ['rank' => 32, 'channelId' => 'tf1',     'channelName' => 'TF1',      'programme' => 'Euro 2024 — Demi-finale France - Espagne', 'date' => '2024-07-09', 'audience' => 13.4, 'pda' => 56.2, 'category' => 'sport'],
            ['rank' => 33, 'channelId' => 'm6',      'channelName' => 'M6',       'programme' => 'Cauchemar en cuisine — Spéciale 200ème', 'date' => '2022-10-04', 'audience' => 4.1, 'pda' => 18.3, 'category' => 'divertissement'],
            ['rank' => 34, 'channelId' => 'tf1',     'channelName' => 'TF1',      'programme' => 'James Bond : Skyfall (diffusion TV)', 'date' => '2015-11-28', 'audience' => 8.7, 'pda' => 34.8, 'category' => 'film'],
            ['rank' => 35, 'channelId' => 'france2', 'channelName' => 'France 2', 'programme' => 'Coupe du Monde Rugby 2023 — France - Afrique du Sud', 'date' => '2023-10-15', 'audience' => 12.1, 'pda' => 53.2, 'category' => 'sport'],
            ['rank' => 36, 'channelId' => 'tf1',     'channelName' => 'TF1',      'programme' => 'Danse avec les Stars — Finale saison 13', 'date' => '2023-12-01', 'audience' => 4.5, 'pda' => 23.1, 'category' => 'divertissement'],
            ['rank' => 37, 'channelId' => 'tf1',     'channelName' => 'TF1',      'programme' => 'Coupe du Monde 2022 — Phase de groupes France - Danemark', 'date' => '2022-11-26', 'audience' => 11.8, 'pda' => 52.4, 'category' => 'sport'],
            ['rank' => 38, 'channelId' => 'france2', 'channelName' => 'France 2', 'programme' => 'Cérémonie hommage national à Samuel Paty', 'date' => '2020-10-21', 'audience' => 10.9, 'pda' => 47.8, 'category' => 'info'],
            ['rank' => 39, 'channelId' => 'tf1',     'channelName' => 'TF1',      'programme' => 'Intouchables (diffusion TV)', 'date' => '2013-11-27', 'audience' => 14.2, 'pda' => 52.9, 'category' => 'film'],
            ['rank' => 40, 'channelId' => 'm6',      'channelName' => 'M6',       'programme' => 'Top Chef — Finale saison 14', 'date' => '2023-04-26', 'audience' => 3.8, 'pda' => 17.4, 'category' => 'divertissement'],
            ['rank' => 41, 'channelId' => 'tf1',     'channelName' => 'TF1',      'programme' => 'Crim\' — Finale saison 5', 'date' => '2021-10-08', 'audience' => 7.4, 'pda' => 31.6, 'category' => 'fiction'],
            ['rank' => 42, 'channelId' => 'france2', 'channelName' => 'France 2', 'programme' => 'Roland-Garros 2022 — Finale H. Nadal - Ruud', 'date' => '2022-06-05', 'audience' => 7.1, 'pda' => 35.8, 'category' => 'sport'],
            ['rank' => 43, 'channelId' => 'tf1',     'channelName' => 'TF1',      'programme' => 'Section de recherches — Épisode spécial 200', 'date' => '2016-09-15', 'audience' => 9.2, 'pda' => 39.4, 'category' => 'fiction'],
            ['rank' => 44, 'channelId' => 'france2', 'channelName' => 'France 2', 'programme' => 'JO Paris 2024 — Handball Finale France - Danemark', 'date' => '2024-08-10', 'audience' => 11.3, 'pda' => 44.7, 'category' => 'sport'],
            ['rank' => 45, 'channelId' => 'tf1',     'channelName' => 'TF1',      'programme' => 'Coupe du Monde 2006 — Quart-finale France - Brésil', 'date' => '2006-07-01', 'audience' => 20.5, 'pda' => 80.1, 'category' => 'sport'],
            ['rank' => 46, 'channelId' => 'm6',      'channelName' => 'M6',       'programme' => 'Pékin Express — Finale saison 2023', 'date' => '2023-06-27', 'audience' => 3.4, 'pda' => 16.2, 'category' => 'divertissement'],
            ['rank' => 47, 'channelId' => 'france2', 'channelName' => 'France 2', 'programme' => 'JO Paris 2024 — Judo Finale Équipe de France', 'date' => '2024-08-03', 'audience' => 10.7, 'pda' => 42.8, 'category' => 'sport'],
            ['rank' => 48, 'channelId' => 'tf1',     'channelName' => 'TF1',      'programme' => 'Coupe du Monde Rugby 2023 — France - NZ (Ouverture)', 'date' => '2023-09-08', 'audience' => 14.8, 'pda' => 64.3, 'category' => 'sport'],
            ['rank' => 49, 'channelId' => 'tf1',     'channelName' => 'TF1',      'programme' => 'Euro 2024 — Phase de groupes France - Autriche', 'date' => '2024-06-17', 'audience' => 11.1, 'pda' => 47.8, 'category' => 'sport'],
            ['rank' => 50, 'channelId' => 'france2', 'channelName' => 'France 2', 'programme' => 'Discours d\'Emmanuel Macron — Dissolution Assemblée 2024', 'date' => '2024-06-09', 'audience' => 9.8, 'pda' => 40.1, 'category' => 'info'],
        ];
    }
}
