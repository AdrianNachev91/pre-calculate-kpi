<?php

namespace KpiReport\Service;

use Application\Application\Constant\GlobalConstants;
use Doctrine\ORM\EntityManagerInterface;
use DoctrineEntities\Entity\Renault\DoctrineInterfaces\DataVocRepository;
use DoctrineEntities\Entity\Renault;
use TaskManager\Service\TaskManagerService;

class PreCalculateService
{
    /** @var EntityManagerInterface */
    protected $entityManager;
    /** @var array */
    protected $preCalculateConfig;
    /** @var TaskManagerService */
    protected $taskManagerService;

    /**
     * IndexService constructor.
     * @param EntityManagerInterface $entityManager
     * @param array $preCalculateConfig
     * @param TaskManagerService $taskManagerService
     */
    public function __construct(EntityManagerInterface $entityManager, array $preCalculateConfig, TaskManagerService $taskManagerService)
    {
        $this->entityManager = $entityManager;
        $this->preCalculateConfig = $preCalculateConfig;
        $this->taskManagerService = $taskManagerService;
    }

    /**
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     */
    public function preCalculate()
    {
        ini_set('memory_limit', '1024M');
        $startDate = (new \DateTime())->modify('last day of this month');

        $currentYear = (int)$startDate->format('Y');
        $currentMonth = (int)$startDate->format('m');
        if ($currentMonth === 1) {
            $prevMonth = 12;
            $prevYear = $currentYear - 1;
        } else {
            $prevMonth = $currentMonth - 1;
            $prevYear = $currentYear;
        }
        $prevDate = (new \DateTime($prevYear . '-' . ($prevMonth <= 9 ? '0' : '') . $prevMonth . '-01'))->modify('last day of this month');

        $entities = $this->getEntities();

        $this->preCalculateMonth($startDate, $entities);
        gc_collect_cycles();
        $this->preCalculateMonth($prevDate, $entities);
    }

    /**
     * @param \DateTime $startDate
     * @param $entities
     * @return bool
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     */
    public function preCalculateMonth(\DateTime $startDate, $entities)
    {
        $startTime = new \DateTime();
        echo 'start: ' . $startTime->format('H:i:s') . "\n";

        $countryRepository = $this->entityManager->getRepository(Renault\Country::class);
        $hubRepository = $this->entityManager->getRepository(Renault\Hub::class);
        $subhubRepository = $this->entityManager->getRepository(Renault\Subhub::class);
        $dealerRepository = $this->entityManager->getRepository(Renault\Dealer::class);

        $resultsArray = null;
        $qualityResultsArray = null;

        $resultsArray = [
            'countries' => [],
            'hubs' => [],
            'subhubs' => [],
            'dealers' => []
        ];

        $qualityResultsArray = [
            'countries' => [],
            'hubs' => [],
            'dealers' => []
        ];

        $entitiesCount = count($entities);

        /** @var Renault\Country|Renault\Hub|Renault\Subhub|Renault\Dealer $entity */
        foreach ($entities as $key => $entity) {
            echo 'Fetching data: ' . ($key + 1) . '/' . $entitiesCount . "\n";

            $params = [];
            $params['count'] = true;
            if ($entity instanceof Renault\Country) {
                $params['country'] = $entity;
            } elseif ($entity instanceof Renault\Hub) {
                $params['hub'] = $entity;
            } elseif ($entity instanceof Renault\Subhub) {
                $params['subhub'] = $entity;
            } elseif ($entity instanceof Renault\Dealer) {
                $params['dealer'] = $entity;
            }

            if ($entity instanceof Renault\Country) {
                $countryId = $entity->getCountryId();
            } else {
                $countryId = $entity->getCountry()->getCountryId();
            }

            foreach ($this->preCalculateConfig['quality-entities'] as $vocEntity => $periodTypes) {
                if ($entity instanceof Renault\Country) {
                    $arrayToFill = &$qualityResultsArray['countries'];
                    $entityId = $countryId;
                    $params['level'] = Renault\ExtendClasses\DataQualityImport::TOTALS_LEVEL;
                } elseif ($entity instanceof Renault\Hub) {
                    $arrayToFill = &$qualityResultsArray['hubs'];
                    $entityId = $entity->getHubId();
                    $params['hub'] = $entity->getHubId();
                    $params['level'] = Renault\ExtendClasses\DataQualityImport::HUB_LEVEL;
                } elseif ($entity instanceof Renault\Subhub) {
                    continue;
                } else {
                    $arrayToFill = &$qualityResultsArray['dealers'];
                    $entityId = $entity->getDealerId();
                    $params['dealer'] = $entity->getDealerId();
                    $params['level'] = Renault\ExtendClasses\DataQualityImport::CODE_DEALER_LEVEL;
                }

                if ($periodTypes['country'] !== $countryId) {
                    continue;
                }
                foreach ($periodTypes['period-types'] as $periodType) {
                    foreach (['period_to_date', 'month'] as $calculationType) {
                        $originalStartDate = $this->getStartDate($periodType, $startDate);
                        $originalEndDate = $startDate;

                        $waves = [];
                        if ($calculationType === 'period_to_date') {
                            $waves = [$originalStartDate->format('Y-m')];
                            $params['type'] = Renault\ExtendClasses\DataQualityImport::TYPE_TO_DATE;
                        } else {
                            $startMonth = (int) $originalStartDate->format('m');
                            $endMonth = (int) $originalEndDate->format('m');
                            while ($startMonth <= $endMonth) {
                                $waves[] = $originalStartDate->format('Y') . '-' . ($startMonth <= 9 ? '0' : '') . $startMonth;
                                $startMonth++;
                            }
                            $params['type'] = Renault\ExtendClasses\DataQualityImport::TYPE_MONTHLY;
                        }

                        foreach ($waves as $wave) {
                            $params['wave'] = $wave;

                            foreach ($this->preCalculateConfig['quality-kpis'] as $kpi => $countries) {
                                $params['project'] = $countries[$countryId];

                                if ($countryId === Renault\Country::NETHERLANDS_ID) {
                                    $repositoryEntity = Renault\DataNlQualityImport::class;
                                } else {
                                    $repositoryEntity = Renault\DataBeQualityImport::class;
                                }
                                /** @var Renault\Repository\DataNlQualityImportRepository|Renault\Repository\DataBeQualityImportRepository $qualityRepository */
                                $qualityRepository = $this->entityManager->getRepository($repositoryEntity);

                                $kpiResult = $qualityRepository->getAverageKpiByWave($wave, $params);

                                if ($calculationType === 'month') {
                                    $arrayToFill[$entityId][$kpi][$periodType][Renault\KpiDataqualityResults::DATA_TYPE_NR_INTERVIEWS][(int)explode('-', $wave)[1]] = $qualityRepository->getCompletedInterviewsByWave($wave, $params);
                                    $arrayToFill[$entityId][$kpi][$periodType][Renault\KpiDataqualityResults::DATA_TYPE_MONTH][(int)explode('-', $wave)[1]] = $kpiResult;
                                    $params['kpi'] = $kpi;
                                    $params['start'] = (new \DateTime($wave))->format('Y-m-d');
                                    $params['end'] = (new \DateTime($wave))->modify('last day of this month')->format('Y-m-d');
                                    $params['state'] = 'open';
                                    $arrayToFill[$entityId][$kpi][$periodType][Renault\KpiDataqualityResults::DATA_TYPE_OPEN_TASKS][(int)explode('-', $wave)[1]] = $this->taskManagerService->getCount($params);
                                    $params['state'] = 'closed';
                                    $arrayToFill[$entityId][$kpi][$periodType][Renault\KpiDataqualityResults::DATA_TYPE_CLOSED_TASKS][(int)explode('-', $wave)[1]] = $this->taskManagerService->getCount($params);
                                } else {
                                    $arrayToFill[$entityId][$kpi][$periodType][Renault\KpiDataqualityResults::DATA_TYPE_START_PERIOD_TO_DATE][(int)explode('-', $wave)[1]] = $kpiResult;
                                }
                            }
                        }
                    }
                }
            }

            foreach ($this->preCalculateConfig['voc-entities'] as $vocEntity => $periodTypes) {
                if ($periodTypes['country'] !== $countryId) {
                    continue;
                }
                foreach ($periodTypes['period-types'] as $periodType) {
                    foreach (['period_to_date', 'month'] as $calculationType) {
                        $originalStartDate = $this->getStartDate($periodType, $startDate);
                        $originalEndDate = $startDate;

                        $params['start'] = $originalStartDate->format('Y-m-d');
                        $params['end'] = $originalStartDate->modify('last day of this month')->format('Y-m-d');

                        // If end date is bigger than the original date, means that we wont't do calculations for even one full month
                        if (new \DateTime($params['end']) > $originalEndDate) {
                            $params['end'] = $originalEndDate->format('Y-m-d');
                        }

                        while (new \DateTime($params['end']) <= $originalEndDate) {
                            if ($calculationType === 'month') {
                                $params['start'] = (new \DateTime($params['end']))->modify('first day of this month')->format('Y-m-d');
                            }

                            /** @var DataVocRepository $vocRepository */
                            $vocRepository = $this->entityManager->getRepository($vocEntity);
                            $results = $vocRepository->getKpiStatistics($params);

                            if ($entity instanceof Renault\Country) {
                                $arrayToFill = &$resultsArray['countries'];
                                $entityId = $entity->getCountryId();
                            } elseif ($entity instanceof Renault\Hub) {
                                $arrayToFill = &$resultsArray['hubs'];
                                $entityId = $entity->getHubId();
                            } elseif ($entity instanceof Renault\Subhub) {
                                $arrayToFill = &$resultsArray['subhubs'];
                                $entityId = $entity->getSubhubId();
                            } else {
                                $arrayToFill = &$resultsArray['dealers'];
                                $entityId = $entity->getDealerId();
                            }

                            foreach ($this->preCalculateConfig['kpis'] as $kpi => $kpiEntities) {
                                if (key_exists($kpi, $results)) {
                                    if ($calculationType === 'month') {
                                        $params['kpi'] = $kpiEntities[$vocEntity];
                                        $arrayToFill[$entityId][$kpiEntities[$vocEntity]][$periodType][Renault\KpiVocResults::DATA_TYPE_NR_INTERVIEWS][(int)explode('-', $params['end'])[1]] = $results[$kpi . 'InterviewsCount'];
                                        $arrayToFill[$entityId][$kpiEntities[$vocEntity]][$periodType][Renault\KpiVocResults::DATA_TYPE_MONTH][(int)explode('-', $params['end'])[1]] = $results[$kpi];
                                        $params['state'] = 'open';
                                        $arrayToFill[$entityId][$kpiEntities[$vocEntity]][$periodType][Renault\KpiVocResults::DATA_TYPE_OPEN_TASKS][(int)explode('-', $params['end'])[1]] = $this->taskManagerService->getCount($params);
                                        $params['state'] = 'closed';
                                        $arrayToFill[$entityId][$kpiEntities[$vocEntity]][$periodType][Renault\KpiVocResults::DATA_TYPE_CLOSED_TASKS][(int)explode('-', $params['end'])[1]] = $this->taskManagerService->getCount($params);
                                    } else {
                                        $arrayToFill[$entityId][$kpiEntities[$vocEntity]][$periodType][Renault\KpiVocResults::DATA_TYPE_START_PERIOD_TO_DATE][(int)explode('-', $params['end'])[1]] = $results[$kpi];
                                    }
                                }
                            }

                            // If the end date is already equal to today's date there is no need to run again as it will display
                            // the same data.
                            if ($params['end'] === $originalEndDate->format('Y-m-d')) {
                                break;
                            }

                            $endYear = (new \DateTime($params['end']))->format('Y');
                            $endMonth = (int)(new \DateTime($params['end']))->format('m');

                            // If december switch to January
                            if ($endMonth === 12) {
                                $endMonth = 1;
                                $endYear++;
                            } else {
                                $endMonth++;
                            }

                            $params['end'] = (new \DateTime($endYear . '-' . $endMonth))->modify('last day of this month')->format('Y-m-d');

                            if (new \DateTime($params['end']) > $originalEndDate) {
                                $params['end'] = $originalEndDate->format('Y-m-d');
                            }
                        }
                    }
                }
            }
        }

        $entityNumber = 1;

        /** @var Renault\Repository\KpiDataqualityResultsRepository $kpiRepository */
        $kpiRepository = $this->entityManager->getRepository(Renault\KpiDataqualityResults::class);
        $kpiRepository->deleteDataByDate(new \DateTime($startDate->format('Y-m-d')));

        foreach ($qualityResultsArray['countries'] as $id => $kpi) {
            echo 'Saving quality entity: ' . $entityNumber . '/' . $entitiesCount . "\n";
            echo 'country: ' . $id . "\n";
            /** @var Renault\Country $entity */
            $entity = $countryRepository->find($id);
            $entitySetter = 'setCountry';
            $this->populateKpiQualityResults($kpi, $entitySetter, $entity, $startDate);
            $entityNumber++;
        }

        foreach ($qualityResultsArray['hubs'] as $id => $kpi) {
            echo 'Saving quality entity: ' . $entityNumber . '/' . $entitiesCount . "\n";
            echo 'hub: ' . $id . "\n";
            /** @var Renault\Hub $entity */
            $entity = $hubRepository->find($id);
            $entitySetter = 'setHub';
            $this->populateKpiQualityResults($kpi, $entitySetter, $entity, $startDate);
            $entityNumber++;
        }

        foreach ($qualityResultsArray['dealers'] as $id => $kpi) {
            echo 'Saving quality entity: ' . $entityNumber . '/' . $entitiesCount . "\n";
            echo 'dealer: ' . $id . "\n";
            /** @var Renault\Dealer $entity */
            $entity = $dealerRepository->find($id);
            $entitySetter = 'setDealer';
            $this->populateKpiQualityResults($kpi, $entitySetter, $entity, $startDate);
            $entityNumber++;
        }

        $entityNumber = 1;

        /** @var Renault\Repository\KpiVocResultsRepository $kpiRepository */
        $kpiRepository = $this->entityManager->getRepository(Renault\KpiVocResults::class);
        $kpiRepository->deleteDataByDate(new \DateTime($startDate->format('Y-m-d')));

        foreach ($resultsArray['countries'] as $id => $kpi) {
            echo 'Saving entity: ' . $entityNumber . '/' . $entitiesCount . "\n";
            echo 'country: ' . $id . "\n";
            $entity = $countryRepository->find($id);
            $entitySetter = 'setCountry';
            $this->populateKpiVocResults($kpi, $entitySetter, $entity, $startDate);
            $entityNumber++;
        }

        foreach ($resultsArray['hubs'] as $id => $kpi) {
            echo 'Saving entity: ' . $entityNumber . '/' . $entitiesCount . "\n";
            echo 'hub: ' . $id . "\n";
            $entity = $hubRepository->find($id);
            $entitySetter = 'setHub';
            $this->populateKpiVocResults($kpi, $entitySetter, $entity, $startDate);
            $entityNumber++;
        }

        foreach ($resultsArray['subhubs'] as $id => $kpi) {
            echo 'Saving entity: ' . $entityNumber . '/' . $entitiesCount . "\n";
            echo 'subhub: ' . $id . "\n";
            $entity = $subhubRepository->find($id);
            $entitySetter = 'setSubhub';
            $this->populateKpiVocResults($kpi, $entitySetter, $entity, $startDate);
            $entityNumber++;
        }

        foreach ($resultsArray['dealers'] as $id => $kpi) {
            echo 'Saving entity: ' . $entityNumber . '/' . $entitiesCount . "\n";
            echo 'dealer: ' . $id . "\n";
            $entity = $dealerRepository->find($id);
            $entitySetter = 'setDealer';
            $this->populateKpiVocResults($kpi, $entitySetter, $entity, $startDate);
            $entityNumber++;
        }

        $this->preCalculateTop5($startDate);

        $endTime = new \DateTime();
        echo 'end: ' . $endTime->format('H:i:s');

        return true;
    }

    /**
     * @param \DateTime $endDateValue
     * @return bool
     * @throws \Exception
     */
    private function preCalculateTop5(\DateTime $endDateValue)
    {
        echo 'Start top5: ' . (new \DateTime())->format('H:i:s') . "\n";

        /** @var Renault\Repository\KpiVocResultsRepository $kpiVocResultsRepository */
        $kpiVocResultsRepository = $this->entityManager->getRepository(Renault\KpiVocResults::class);

        $params['end'] = $endDateValue->format('Y-m-d');
        $params['dataType'] = Renault\KpiVocResults::DATA_TYPE_MONTH;

        $hubsTop5 = [];
        $dealersTop5 = [];

        foreach ([Renault\Country::NETHERLANDS_ID, Renault\Country::BELUX_ID] as $country) {
            $params['country'] = $country;
            foreach ($this->preCalculateConfig['kpi-ids'] as $kpi) {
                $params['kpi'] = $kpi;
                foreach ($this->preCalculateConfig['period-types'] as $periodType) {
                    if (
                        (
                            $country === Renault\Country::NETHERLANDS_ID &&
                            in_array($periodType, [Renault\KpiVocResults::PERIOD_TYPE_NL_BONUS_YTD, Renault\KpiVocResults::PERIOD_TYPE_NL_YTD])
                        )
                        ||
                        (
                            $country === Renault\Country::BELUX_ID &&
                            in_array($periodType, [Renault\KpiVocResults::PERIOD_TYPE_BE_6FM])
                        )
                    ) {
                        $params['periodType'] = $periodType;
                    } else {
                        continue;
                    }

                    foreach ($this->preCalculateConfig['months'] as $month) {
                        $params['month'] = $month;

                        $params['hub'] = true;
                        $top5Hubs = $kpiVocResultsRepository->getTop5($params);
                        $top5HubsNumbers = [];
                        foreach ($top5Hubs as $hub) {
                            $top5HubsNumbers[] = $hub[$month];
                        }
                        $hubsTop5[$country][$kpi][$periodType][$month] = number_format(array_sum($top5HubsNumbers) / count($top5HubsNumbers), 1);
                        $params['hub'] = false;
                        $top5Dealers = $kpiVocResultsRepository->getTop5($params);
                        $top5DealerNumbers = [];
                        foreach ($top5Dealers as $dealer) {
                            $top5DealerNumbers[] = $dealer[$month];
                        }
                        $dealersTop5[$country][$kpi][$periodType][$month] = number_format(array_sum($top5DealerNumbers) / count($top5DealerNumbers), 1);
                    }
                }
            }
        }

        $this->populateTop5($hubsTop5, Renault\KpiVocResults::DATA_TYPE_TOP_5_HUBS, $endDateValue);
        $this->populateTop5($dealersTop5, Renault\KpiVocResults::DATA_TYPE_TOP_5_DEALERS, $endDateValue);
        $this->entityManager->flush();

        echo 'End top5: ' . (new \DateTime())->format('H:i:s') . "\n";

        return null;
    }

    private function populateTop5(array $top5Array, $dataType, \DateTime $end)
    {
        $tmKpiRepository = $this->entityManager->getRepository(Renault\TmKpi::class);
        $countryRepository = $this->entityManager->getRepository(Renault\Country::class);

        foreach ($top5Array as $country => $kpi) {
            /** @var Renault\Country $countryValue */
            $countryValue = $countryRepository->find($country);
            foreach ($kpi as $kpiValue => $periodTypes) {
                /** @var Renault\TmKpi $kpiValue */
                $kpiValue = $tmKpiRepository->find($kpiValue);
                foreach ($periodTypes as $periodTypeValue => $months) {
                    $kpiVocResults = new Renault\KpiVocResults();
                    $kpiVocResults
                        ->setKpi($kpiValue)
                        ->setPeriodType($periodTypeValue)
                        ->setEnd($end)
                        ->setDataType($dataType)
                        ->setCountry($countryValue)
                    ;
                    foreach ($months as $monthValue => $score) {
                        $monthSetter = 'set' . ucfirst($monthValue);
                        $kpiVocResults->$monthSetter($score);
                    }
                    $this->entityManager->persist($kpiVocResults);
                }
            }
        }
    }

    /**
     * @param $kpi
     * @param $entitySetter
     * @param Renault\Country|Renault\Hub|Renault\Dealer $entity
     * @param $endDateValue
     */
    private function populateKpiQualityResults($kpi, $entitySetter, $entity, $endDateValue)
    {
        if ($entity instanceof Renault\Country) {
            $countryId = $entity->getCountryId();
        } else {
            $countryId = $entity->getCountry()->getCountryId();
        }
        $tmKpiRepository = $this->entityManager->getRepository(Renault\TmKpi::class);

        foreach ($kpi as $kpiId => $periodType) {
            foreach ($periodType as $periodTypeValue => $dataType) {
                foreach ($dataType as $dataTypeName => $month) {
                    /** @var Renault\TmKpi $tmKpi */
                    $tmKpi = $tmKpiRepository->find($kpiId);
                    $kpiQualityResults = new Renault\KpiDataqualityResults();
                    $kpiQualityResults
                        ->$entitySetter($entity)
                        ->setKpi($tmKpi)
                        ->setPeriodType($periodTypeValue)
                        ->setDataType($dataTypeName)
                        ->setEnd($endDateValue)
                    ;
                    foreach ($month as $monthValue => $kpiValue) {
                        if ($dataTypeName === Renault\KpiDataqualityResults::DATA_TYPE_START_PERIOD_TO_DATE) {
                            if ($countryId === Renault\Country::NETHERLANDS_ID) {
                                $kpiQualityResults->setPeriod1($kpiValue);
                            } else {
                                if ($monthValue <= 6) {
                                    $kpiQualityResults->setPeriod1($kpiValue);
                                } else {
                                    $kpiQualityResults->setPeriod2($kpiValue);
                                }
                            }
                        } else {
                            $monthSetter = $this->preCalculateConfig['monthSetters'][$monthValue];
                            $kpiQualityResults->$monthSetter($kpiValue);
                        }
                    }
                    $this->entityManager->persist($kpiQualityResults);
                }
            }
        }

        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    private function populateKpiVocResults($kpi, $entitySetter, $entity, $endDateValue)
    {
        $tmKpiRepository = $this->entityManager->getRepository(Renault\TmKpi::class);

        foreach ($kpi as $kpiId => $periodType) {
            foreach ($periodType as $periodTypeValue => $dataType) {
                foreach ($dataType as $dataTypeName => $month) {
                    /** @var Renault\TmKpi $tmKpi */
                    $tmKpi = $tmKpiRepository->find($kpiId);
                    $kpiVocResults = new Renault\KpiVocResults();
                    $kpiVocResults
                        ->$entitySetter($entity)
                        ->setKpi($tmKpi)
                        ->setPeriodType($periodTypeValue)
                        ->setDataType($dataTypeName)
                        ->setEnd($endDateValue)
                    ;
                    foreach ($month as $monthValue => $kpiValue) {
                        $monthSetter = $this->preCalculateConfig['monthSetters'][$monthValue];
                        $kpiVocResults
                            ->$monthSetter($kpiValue)
                        ;
                    }
                    $this->entityManager->persist($kpiVocResults);
                }
            }
        }

        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    /**
     * @param $periodType
     * @param \DateTime $endDate
     * @return \DateTime
     * @throws \Exception
     */
    public function getStartDate($periodType, \DateTime $endDate)
    {
        $year = (int)$endDate->modify('last day of this month')->format('Y');
        $month = (int)$endDate->modify('last day of this month')->format('m');

        if ($periodType === Renault\KpiVocResults::PERIOD_TYPE_NL_BONUS_YTD) {
            if ($month !== 12) {
                $year = $year - 1;
            }
            $month = GlobalConstants::NL_BONUS_MONTH;
        } elseif ($periodType === Renault\KpiVocResults::PERIOD_TYPE_NL_YTD) {
            $month = '01';
        } else {
            if ($month <= 6) {
                $month = '01';
            } else {
                $month = '07';
            }
        }
        return new \DateTime($year . '-' . $month . '-01');
    }

    private function getEntities()
    {
        $countryRepository = $this->entityManager->getRepository(Renault\Country::class);
        $hubRepository = $this->entityManager->getRepository(Renault\Hub::class);
        $subhubRepository = $this->entityManager->getRepository(Renault\Subhub::class);
        $dealerRepository = $this->entityManager->getRepository(Renault\Dealer::class);


        return array_merge(
            $countryRepository->findBy(['countryId' => [Renault\Country::NETHERLANDS_ID, Renault\Country::BELUX_ID]]),
            $hubRepository->findAll(),
            $subhubRepository->findAll(),
            $dealerRepository->findAll()
        );
    }
}
