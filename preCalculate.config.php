<?php

use DoctrineEntities\Entity\Renault;

return [
    'quality-entities' => [
        Renault\DataNlQualityImport::class => [
            'period-types' => [
                Renault\KpiDataqualityResults::PERIOD_TYPE_NL_YTD
            ],
            'country' => Renault\Country::NETHERLANDS_ID
        ],
        Renault\DataBeQualityImport::class => [
            'period-types' => [
                Renault\KpiDataqualityResults::PERIOD_TYPE_BE_6FM
            ],
            'country' => Renault\Country::BELUX_ID
        ]
    ],
    'voc-entities' => [
        Renault\DataNlVocSales::class => [
            'period-types' => [
                Renault\KpiVocResults::PERIOD_TYPE_NL_BONUS_YTD,
                Renault\KpiVocResults::PERIOD_TYPE_NL_YTD
            ],
            'country' => Renault\Country::NETHERLANDS_ID
        ],
        Renault\DataNlVocDaciaSales::class => [
            'period-types' => [
                Renault\KpiVocResults::PERIOD_TYPE_NL_BONUS_YTD,
                Renault\KpiVocResults::PERIOD_TYPE_NL_YTD
            ],
            'country' => Renault\Country::NETHERLANDS_ID
        ],
        Renault\DataNlVocService::class => [
            'period-types' => [
                Renault\KpiVocResults::PERIOD_TYPE_NL_BONUS_YTD,
                Renault\KpiVocResults::PERIOD_TYPE_NL_YTD
            ],
            'country' => Renault\Country::NETHERLANDS_ID
        ],
        Renault\DataBeVocSales::class => [
            'period-types' => [
                Renault\KpiVocResults::PERIOD_TYPE_BE_6FM
            ],
            'country' => Renault\Country::BELUX_ID
        ],
        Renault\DataBeVocDaciaSales::class => [
            'period-types' => [
                Renault\KpiVocResults::PERIOD_TYPE_BE_6FM
            ],
            'country' => Renault\Country::BELUX_ID
        ],
        Renault\DataBeVocService::class => [
            'period-types' => [
                Renault\KpiVocResults::PERIOD_TYPE_BE_6FM
            ],
            'country' => Renault\Country::BELUX_ID
        ]
    ],
    'period-types' => [
        Renault\KpiVocResults::PERIOD_TYPE_NL_BONUS_YTD,
        Renault\KpiVocResults::PERIOD_TYPE_NL_YTD,
        Renault\KpiVocResults::PERIOD_TYPE_BE_6FM
    ],
    'kpi-ids' => [
        Renault\TmKpi::RENAULT_SALES_NPS,
        Renault\TmKpi::DACIA_SALES_NPS,
        Renault\TmKpi::AFTERSALES_NPS,
        Renault\TmKpi::RENAULT_SALES_RETURN,
        Renault\TmKpi::DACIA_SALES_RETURN,
        Renault\TmKpi::AFTERSALES_RETURN,
        Renault\TmKpi::RENAULT_SALES_MOT_1,
        Renault\TmKpi::DACIA_SALES_MOT_1,
        Renault\TmKpi::AFTERSALES_MOT_1,
        Renault\TmKpi::RENAULT_SALES_MOT_7,
        Renault\TmKpi::DACIA_SALES_MOT_7,
        Renault\TmKpi::AFTERSALES_MOT_7,
        Renault\TmKpi::RENAULT_SALES_MOT_5,
        Renault\TmKpi::DACIA_SALES_MOT_5,
        Renault\TmKpi::AFTERSALES_MOT_5,
        Renault\TmKpi::RENAULT_SALES_MOT_21,
        Renault\TmKpi::DACIA_SALES_MOT_21,
        Renault\TmKpi::RENAULT_SALES_MOT_22,
        Renault\TmKpi::DACIA_SALES_MOT_22,
        Renault\TmKpi::RENAULT_SALES_MOT_3,
        Renault\TmKpi::DACIA_SALES_MOT_3,
        Renault\TmKpi::RENAULT_SALES_MOT_31,
        Renault\TmKpi::DACIA_SALES_MOT_31,
        Renault\TmKpi::RENAULT_SALES_MOT_41,
        Renault\TmKpi::DACIA_SALES_MOT_41,
        Renault\TmKpi::RENAULT_SALES_MOT_42,
        Renault\TmKpi::DACIA_SALES_MOT_42,
        Renault\TmKpi::RENAULT_SALES_MOT_43,
        Renault\TmKpi::DACIA_SALES_MOT_43,
        Renault\TmKpi::AFTERSALES_MOT_61,
        Renault\TmKpi::AFTERSALES_MOT_62,
        Renault\TmKpi::AFTERSALES_MOT_63
    ],
    'quality-kpis' => [
        Renault\TmKpi::RENAULT_SALES_DATA_QUALITY => [
            Renault\Country::NETHERLANDS_ID => Renault\ImportProject::DATAQUALITY_RENAULT_SALES_NL_ID,
            Renault\Country::BELUX_ID => Renault\ImportProject::DATAQUALITY_RENAULT_SALES_BE_ID
        ],
        Renault\TmKpi::DACIA_SALES_DATA_QUALITY => [
            Renault\Country::NETHERLANDS_ID => Renault\ImportProject::DATAQUALITY_DACIA_SALES_NL_ID,
            Renault\Country::BELUX_ID => Renault\ImportProject::DATAQUALITY_DACIA_SALES_BE_ID
        ],
        Renault\TmKpi::AFTERSALES_DATA_QUALITY => [
            Renault\Country::NETHERLANDS_ID => Renault\ImportProject::DATAQUALITY_AFTERSALES_NL_ID,
            Renault\Country::BELUX_ID => Renault\ImportProject::DATAQUALITY_AFTERSALES_BE_ID
        ]
    ],
    'kpis' => [
        'nps' => [
            Renault\DataNlVocSales::class => Renault\TmKpi::RENAULT_SALES_NPS,
            Renault\DataNlVocDaciaSales::class => Renault\TmKpi::DACIA_SALES_NPS,
            Renault\DataNlVocService::class => Renault\TmKpi::AFTERSALES_NPS,
            Renault\DataBeVocSales::class => Renault\TmKpi::RENAULT_SALES_NPS,
            Renault\DataBeVocDaciaSales::class => Renault\TmKpi::DACIA_SALES_NPS,
            Renault\DataBeVocService::class => Renault\TmKpi::AFTERSALES_NPS
        ],
        'return' => [
            Renault\DataNlVocSales::class => Renault\TmKpi::RENAULT_SALES_RETURN,
            Renault\DataNlVocDaciaSales::class => Renault\TmKpi::DACIA_SALES_RETURN,
            Renault\DataNlVocService::class => Renault\TmKpi::AFTERSALES_RETURN,
            Renault\DataBeVocSales::class => Renault\TmKpi::RENAULT_SALES_RETURN,
            Renault\DataBeVocDaciaSales::class => Renault\TmKpi::DACIA_SALES_RETURN,
            Renault\DataBeVocService::class => Renault\TmKpi::AFTERSALES_RETURN
        ],
        'mot1' => [
            Renault\DataNlVocSales::class => Renault\TmKpi::RENAULT_SALES_MOT_1,
            Renault\DataNlVocDaciaSales::class => Renault\TmKpi::DACIA_SALES_MOT_1,
            Renault\DataNlVocService::class => Renault\TmKpi::AFTERSALES_MOT_1,
            Renault\DataBeVocSales::class => Renault\TmKpi::RENAULT_SALES_MOT_1,
            Renault\DataBeVocDaciaSales::class => Renault\TmKpi::DACIA_SALES_MOT_1,
            Renault\DataBeVocService::class => Renault\TmKpi::AFTERSALES_MOT_1
        ],
        'mot7' => [
            Renault\DataNlVocSales::class => Renault\TmKpi::RENAULT_SALES_MOT_7,
            Renault\DataNlVocDaciaSales::class => Renault\TmKpi::DACIA_SALES_MOT_7,
            Renault\DataNlVocService::class => Renault\TmKpi::AFTERSALES_MOT_7,
            Renault\DataBeVocSales::class => Renault\TmKpi::RENAULT_SALES_MOT_7,
            Renault\DataBeVocDaciaSales::class => Renault\TmKpi::DACIA_SALES_MOT_7,
            Renault\DataBeVocService::class => Renault\TmKpi::AFTERSALES_MOT_7
        ],
        'mot5' => [
            Renault\DataNlVocSales::class => Renault\TmKpi::RENAULT_SALES_MOT_5,
            Renault\DataNlVocDaciaSales::class => Renault\TmKpi::DACIA_SALES_MOT_5,
            Renault\DataNlVocService::class => Renault\TmKpi::AFTERSALES_MOT_5,
            Renault\DataBeVocSales::class => Renault\TmKpi::RENAULT_SALES_MOT_5,
            Renault\DataBeVocDaciaSales::class => Renault\TmKpi::DACIA_SALES_MOT_5,
            Renault\DataBeVocService::class => Renault\TmKpi::AFTERSALES_MOT_5
        ],
        'mot21' => [
            Renault\DataNlVocSales::class => Renault\TmKpi::RENAULT_SALES_MOT_21,
            Renault\DataNlVocDaciaSales::class => Renault\TmKpi::DACIA_SALES_MOT_21,
            Renault\DataBeVocSales::class => Renault\TmKpi::RENAULT_SALES_MOT_21,
            Renault\DataBeVocDaciaSales::class => Renault\TmKpi::DACIA_SALES_MOT_21
        ],
        'mot22' => [
            Renault\DataNlVocSales::class => Renault\TmKpi::RENAULT_SALES_MOT_22,
            Renault\DataNlVocDaciaSales::class => Renault\TmKpi::DACIA_SALES_MOT_22,
            Renault\DataBeVocSales::class => Renault\TmKpi::RENAULT_SALES_MOT_22,
            Renault\DataBeVocDaciaSales::class => Renault\TmKpi::DACIA_SALES_MOT_22
        ],
        'mot3' => [
            Renault\DataNlVocSales::class => Renault\TmKpi::RENAULT_SALES_MOT_3,
            Renault\DataNlVocDaciaSales::class => Renault\TmKpi::DACIA_SALES_MOT_3,
            Renault\DataBeVocSales::class => Renault\TmKpi::RENAULT_SALES_MOT_3,
            Renault\DataBeVocDaciaSales::class => Renault\TmKpi::DACIA_SALES_MOT_3
        ],
        'mot31' => [
            Renault\DataNlVocSales::class => Renault\TmKpi::RENAULT_SALES_MOT_31,
            Renault\DataNlVocDaciaSales::class => Renault\TmKpi::DACIA_SALES_MOT_31,
            Renault\DataBeVocSales::class => Renault\TmKpi::RENAULT_SALES_MOT_31,
            Renault\DataBeVocDaciaSales::class => Renault\TmKpi::DACIA_SALES_MOT_31
        ],
        'mot41' => [
            Renault\DataNlVocSales::class => Renault\TmKpi::RENAULT_SALES_MOT_41,
            Renault\DataNlVocDaciaSales::class => Renault\TmKpi::DACIA_SALES_MOT_41,
            Renault\DataBeVocSales::class => Renault\TmKpi::RENAULT_SALES_MOT_41,
            Renault\DataBeVocDaciaSales::class => Renault\TmKpi::DACIA_SALES_MOT_41
        ],
        'mot42' => [
            Renault\DataNlVocSales::class => Renault\TmKpi::RENAULT_SALES_MOT_42,
            Renault\DataNlVocDaciaSales::class => Renault\TmKpi::DACIA_SALES_MOT_42,
            Renault\DataBeVocSales::class => Renault\TmKpi::RENAULT_SALES_MOT_42,
            Renault\DataBeVocDaciaSales::class => Renault\TmKpi::DACIA_SALES_MOT_42
        ],
        'mot43' => [
            Renault\DataNlVocSales::class => Renault\TmKpi::RENAULT_SALES_MOT_43,
            Renault\DataNlVocDaciaSales::class => Renault\TmKpi::DACIA_SALES_MOT_43,
            Renault\DataBeVocSales::class => Renault\TmKpi::RENAULT_SALES_MOT_43,
            Renault\DataBeVocDaciaSales::class => Renault\TmKpi::DACIA_SALES_MOT_43
        ],
        'mot61' => [
            Renault\DataNlVocService::class => Renault\TmKpi::AFTERSALES_MOT_61,
            Renault\DataBeVocService::class => Renault\TmKpi::AFTERSALES_MOT_61
        ],
        'mot62' => [
            Renault\DataNlVocService::class => Renault\TmKpi::AFTERSALES_MOT_62,
            Renault\DataBeVocService::class => Renault\TmKpi::AFTERSALES_MOT_62
        ],
        'mot63' => [
            Renault\DataNlVocService::class => Renault\TmKpi::AFTERSALES_MOT_63,
            Renault\DataBeVocService::class => Renault\TmKpi::AFTERSALES_MOT_63
        ]
    ],
    'monthGetters' => [
        'getJanuary',
        'getFebruary',
        'getMarch',
        'getApril',
        'getMay',
        'getJune',
        'getJuly',
        'getAugust',
        'getSeptember',
        'getOctober',
        'getNovember',
        'getDecember'
    ],
    'monthDisplay' => [
        1 => 'getJanuary',
        2 => 'getFebruary',
        3 => 'getMarch',
        4 => 'getApril',
        5 => 'getMay',
        6 => 'getJune',
        7 => 'getJuly',
        8 => 'getAugust',
        9 => 'getSeptember',
        10 => 'getOctober',
        11 => 'getNovember',
        12 => 'getDecember'
    ],
    'monthSetters' => [
        1 => 'setJanuary',
        2 => 'setFebruary',
        3 => 'setMarch',
        4 => 'setApril',
        5 => 'setMay',
        6 => 'setJune',
        7 => 'setJuly',
        8 => 'setAugust',
        9 => 'setSeptember',
        10 => 'setOctober',
        11 => 'setNovember',
        12 => 'setDecember'
    ],
    'months' => [
        'january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november',
        'december'
    ],
];
