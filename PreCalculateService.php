<?php

namespace KpiReport\Service;

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
    public function preCalculateCurrentMonth()
    {
        ini_set('memory_limit', '1024M');
        $startDate = (new \DateTime())->modify('last day of this month');

        $entities = $this->getEntities();

        return $this->preCalculateMonth($startDate, $entities);
    }

    /**
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     */
    public function preCalculatePrevMonth()
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
        $startDate = (new \DateTime($prevYear . '-' . ($prevMonth <= 9 ? '0' : '') . $prevMonth . '-01'))->modify('last day of this month');

        $entities = $this->getEntities();

        return $this->preCalculateMonth($startDate, $entities);
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

        $storecheckResultsArray = [
            'countries' => [],
            'hubs' => [],
            'subhubs' => [],
            'dealers' => []
        ];

        $detractorsResultsArray = [
            'countries' => [],
            'hubs' => [],
            'subhubs' => [],
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

            $qualityResultsArray = $this->fetchQualityData($entity, $countryId, $startDate, $qualityResultsArray, $params);
            $resultsArray = $this->fetchVocData($entity, $countryId, $startDate, $resultsArray, $params);
            $storecheckResultsArray = $this->fetchStorecheckData($entity, $countryId, $startDate, $storecheckResultsArray, $params);
            $detractorsResultsArray = $this->fetchDetractorsData($entity, $countryId, $startDate, $detractorsResultsArray, $params);
        }

        $this->populateResults($detractorsResultsArray, $storecheckResultsArray, $qualityResultsArray, $resultsArray, $entitiesCount, $startDate);

        $this->preCalculateTop5($startDate);

        $endTime = new \DateTime();
        echo 'end: ' . $endTime->format('H:i:s');

        return true;
    }

    /**
     * @param Renault\Country|Renault\Hub|Renault\Subhub|Renault\Dealer $entity
     * @param int $countryId
     * @param \DateTime $startDate
     * @param array $qualityResultsArray
     * @param $params
     * @return array
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     */
    private function fetchQualityData($entity, $countryId, $startDate, $qualityResultsArray, $params)
    {
        foreach ($this->preCalculateConfig['quality-entities'] as $qualityEntity => $periodTypes) {
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
            foreach (['period_to_date', 'month'] as $calculationType) {
                $originalStartDate = $this->getStartDate($startDate);
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
                            $arrayToFill[$entityId][$kpi][Renault\KpiDataqualityResults::DATA_TYPE_NR_INTERVIEWS][(int)explode('-', $wave)[1]] = $qualityRepository->getCompletedInterviewsByWave($wave, $params);
                            $arrayToFill[$entityId][$kpi][Renault\KpiDataqualityResults::DATA_TYPE_MONTH][(int)explode('-', $wave)[1]] = $kpiResult;
                            $params['kpi'] = $kpi;
                            $params['start'] = (new \DateTime($wave))->format('Y-m-d');
                            $params['end'] = (new \DateTime($wave))->modify('last day of this month')->format('Y-m-d');
                            $params['state'] = 'open';
                            $arrayToFill[$entityId][$kpi][Renault\KpiDataqualityResults::DATA_TYPE_OPEN_TASKS][(int)explode('-', $wave)[1]] = $this->taskManagerService->getCount($params);
                            $params['state'] = 'closed';
                            $arrayToFill[$entityId][$kpi][Renault\KpiDataqualityResults::DATA_TYPE_CLOSED_TASKS][(int)explode('-', $wave)[1]] = $this->taskManagerService->getCount($params);
                        } else {
                            $arrayToFill[$entityId][$kpi][Renault\KpiDataqualityResults::DATA_TYPE_START_PERIOD_TO_DATE][(int)explode('-', $wave)[1]] = $kpiResult;
                        }
                    }
                }
            }
        }
        return $qualityResultsArray;
    }

    /**
     * @param Renault\Country|Renault\Hub|Renault\Subhub|Renault\Dealer $entity
     * @param int $countryId
     * @param \DateTime $startDate
     * @param array $resultsArray
     * @param $params
     * @return array
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     */
    private function fetchVocData($entity, $countryId, $startDate, $resultsArray, $params)
    {
        foreach ($this->preCalculateConfig['voc-entities'] as $vocEntity => $periodTypes) {
            if ($periodTypes['country'] !== $countryId) {
                continue;
            }
            foreach (['period_to_date', 'month'] as $calculationType) {
                $originalStartDate = $this->getStartDate($startDate);
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
                                $arrayToFill[$entityId][$kpiEntities[$vocEntity]][Renault\KpiVocResults::DATA_TYPE_NR_INTERVIEWS][(int)explode('-', $params['end'])[1]] = $results[$kpi . 'InterviewsCount'];
                                $arrayToFill[$entityId][$kpiEntities[$vocEntity]][Renault\KpiVocResults::DATA_TYPE_MONTH][(int)explode('-', $params['end'])[1]] = $results[$kpi];
                                $params['state'] = 'open';
                                $arrayToFill[$entityId][$kpiEntities[$vocEntity]][Renault\KpiVocResults::DATA_TYPE_OPEN_TASKS][(int)explode('-', $params['end'])[1]] = $this->taskManagerService->getCount($params);
                                $params['state'] = 'closed';
                                $arrayToFill[$entityId][$kpiEntities[$vocEntity]][Renault\KpiVocResults::DATA_TYPE_CLOSED_TASKS][(int)explode('-', $params['end'])[1]] = $this->taskManagerService->getCount($params);
                            } else {
                                $arrayToFill[$entityId][$kpiEntities[$vocEntity]][Renault\KpiVocResults::DATA_TYPE_START_PERIOD_TO_DATE][(int)explode('-', $params['end'])[1]] = $results[$kpi];
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
        return $resultsArray;
    }

    /**
     * @param Renault\Country|Renault\Hub|Renault\Subhub|Renault\Dealer $entity
     * @param int $countryId
     * @param \DateTime $startDate
     * @param array $storecheckResultsArray
     * @param $params
     * @return array
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     */
    private function fetchStorecheckData($entity, $countryId, $startDate, $storecheckResultsArray, $params)
    {
        foreach ($this->preCalculateConfig['storecheck-entities'] as $storecheckEntity => $periodTypes) {
            if ($periodTypes['country'] !== $countryId) {
                continue;
            }
            foreach (['period_to_date', 'month'] as $calculationType) {
                $originalStartDate = $this->getStartDate($startDate);
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

                    /** @var Renault\DoctrineInterfaces\StorecheckRepository $storecheckRepository */
                    $storecheckRepository = $this->entityManager->getRepository($storecheckEntity);
                    $results = $storecheckRepository->getKpiStatistics($params);

                    if ($entity instanceof Renault\Country) {
                        $arrayToFill = &$storecheckResultsArray['countries'];
                        $entityId = $entity->getCountryId();
                    } elseif ($entity instanceof Renault\Hub) {
                        $arrayToFill = &$storecheckResultsArray['hubs'];
                        $entityId = $entity->getHubId();
                    } elseif ($entity instanceof Renault\Subhub) {
                        $arrayToFill = &$storecheckResultsArray['subhubs'];
                        $entityId = $entity->getSubhubId();
                    } else {
                        $arrayToFill = &$storecheckResultsArray['dealers'];
                        $entityId = $entity->getDealerId();
                    }

                    if ($calculationType === 'month') {
                        $params['kpi'] = $this->preCalculateConfig['storecheck-kpis'][$storecheckEntity];
                        $arrayToFill[$entityId][$this->preCalculateConfig['storecheck-kpis'][$storecheckEntity]][Renault\KpiVocResults::DATA_TYPE_NR_INTERVIEWS][(int)explode('-', $params['end'])[1]] = $results['countInterviews'];
                        $arrayToFill[$entityId][$this->preCalculateConfig['storecheck-kpis'][$storecheckEntity]][Renault\KpiVocResults::DATA_TYPE_MONTH][(int)explode('-', $params['end'])[1]] = $results['kpi'];
                        $params['state'] = 'open';
                        $arrayToFill[$entityId][$this->preCalculateConfig['storecheck-kpis'][$storecheckEntity]][Renault\KpiVocResults::DATA_TYPE_OPEN_TASKS][(int)explode('-', $params['end'])[1]] = $this->taskManagerService->getCount($params);
                        $params['state'] = 'closed';
                        $arrayToFill[$entityId][$this->preCalculateConfig['storecheck-kpis'][$storecheckEntity]][Renault\KpiVocResults::DATA_TYPE_CLOSED_TASKS][(int)explode('-', $params['end'])[1]] = $this->taskManagerService->getCount($params);
                    } else {
                        $arrayToFill[$entityId][$this->preCalculateConfig['storecheck-kpis'][$storecheckEntity]][Renault\KpiVocResults::DATA_TYPE_START_PERIOD_TO_DATE][(int)explode('-', $params['end'])[1]] = $results['kpi'];
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

        return $storecheckResultsArray;
    }

    /**
     * @param Renault\Country|Renault\Hub|Renault\Subhub|Renault\Dealer $entity
     * @param int $countryId
     * @param \DateTime $startDate
     * @param array $detractorsResultsArray
     * @param $params
     * @return array
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     */
    private function fetchDetractorsData($entity, $countryId, $startDate, $detractorsResultsArray, $params)
    {
        foreach ($this->preCalculateConfig['voc-entities'] as $vocEntity => $periodTypes) {
            if ($periodTypes['country'] !== $countryId) {
                continue;
            }
            foreach (['period_to_date', 'month'] as $calculationType) {
                $originalStartDate = $this->getStartDate($startDate);
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
                    $results = $vocRepository->getDetractorsCount($params);

                    $detractorsCount = count($results);
                    $codeInterviews = [];
                    foreach ($results as $result) {
                        $codeInterviews[] = $result['codeInterview'];
                    }
                    if (empty($codeInterviews)) {
                        $codeInterviews[] = 0;
                    }
                    $params['codeInterviews'] = $codeInterviews;

                    if ($entity instanceof Renault\Country) {
                        $arrayToFill = &$detractorsResultsArray['countries'];
                        $entityId = $entity->getCountryId();
                    } elseif ($entity instanceof Renault\Hub) {
                        $arrayToFill = &$detractorsResultsArray['hubs'];
                        $entityId = $entity->getHubId();
                    } elseif ($entity instanceof Renault\Subhub) {
                        $arrayToFill = &$detractorsResultsArray['subhubs'];
                        $entityId = $entity->getSubhubId();
                    } else {
                        $arrayToFill = &$detractorsResultsArray['dealers'];
                        $entityId = $entity->getDealerId();
                    }

                    foreach ($this->preCalculateConfig['detractor-kpis'] as $kpi) {
                        if ($this->preCalculateConfig['detractor-kpis'][$vocEntity] === $kpi) {
                            $params['verbatimImportProject'] = $this->preCalculateConfig['import-projects-from-entities'][$vocEntity];
                            $params['state'] = 'open';
                            $taskParams = $params;
                            unset($taskParams['start'], $taskParams['end']);
                            $openTasks = (int)$this->taskManagerService->getCount($taskParams);
                            $arrayToFill[$entityId][$kpi][Renault\KpiVocResults::DATA_TYPE_OPEN_TASKS][(int)explode('-', $params['end'])[1]] = $openTasks;
                            $taskParams['state'] = 'closed';
                            $closedTasks = (int)$this->taskManagerService->getCount($taskParams);
                            $arrayToFill[$entityId][$kpi][Renault\KpiVocResults::DATA_TYPE_CLOSED_TASKS][(int)explode('-', $params['end'])[1]] = $closedTasks;
                            $tasksCount = $openTasks + $closedTasks;

                            if ($detractorsCount === 0) {
                                $calculatedKpi = null;
                            } else {
                                $calculatedKpi = round((($tasksCount / $detractorsCount) * 100), 1);
                            }

                            if ($calculationType === 'month') {
                                $arrayToFill[$entityId][$kpi][Renault\KpiVocResults::DATA_TYPE_NR_INTERVIEWS][(int)explode('-', $params['end'])[1]] = $detractorsCount;

                                $arrayToFill[$entityId][$kpi][Renault\KpiVocResults::DATA_TYPE_MONTH][(int)explode('-', $params['end'])[1]] = $calculatedKpi;
                            } else {
                                $arrayToFill[$entityId][$kpi][Renault\KpiVocResults::DATA_TYPE_START_PERIOD_TO_DATE][(int)explode('-', $params['end'])[1]] = $calculatedKpi;
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
        return $detractorsResultsArray;
    }

    /**
     * @param array $detractorsResultsArray
     * @param array $storecheckResultsArray
     * @param array $qualityResultsArray
     * @param array $resultsArray
     * @param $entitiesCount
     * @param \DateTime $startDate
     * @throws \Exception
     */
    private function populateResults(array $detractorsResultsArray, array $storecheckResultsArray, array $qualityResultsArray, array $resultsArray, $entitiesCount, \DateTime $startDate)
    {
        $this->populateQualityResults($qualityResultsArray, $entitiesCount, $startDate);
        $this->populateVocResults($resultsArray, $entitiesCount, $startDate);
        $this->populateStorecheckResults($storecheckResultsArray, $entitiesCount, $startDate);
        $this->populateDetractorsResults($detractorsResultsArray, $entitiesCount, $startDate);
    }

    /**
     * @param $qualityResultsArray
     * @param $entitiesCount
     * @param \DateTime $startDate
     * @throws \Exception
     */
    private function populateQualityResults($qualityResultsArray, $entitiesCount, \DateTime $startDate)
    {
        $countryRepository = $this->entityManager->getRepository(Renault\Country::class);
        $hubRepository = $this->entityManager->getRepository(Renault\Hub::class);
        $dealerRepository = $this->entityManager->getRepository(Renault\Dealer::class);

        $entityNumber = 1;

        /** @var Renault\Repository\KpiVocResultsRepository $kpiRepository */
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
    }

    /**
     * @param $kpi
     * @param $entitySetter
     * @param Renault\Country|Renault\Hub|Renault\Dealer $entity
     * @param $endDateValue
     */
    private function populateKpiQualityResults($kpi, $entitySetter, $entity, $endDateValue)
    {
        $tmKpiRepository = $this->entityManager->getRepository(Renault\TmKpi::class);

        foreach ($kpi as $kpiId => $dataType) {
            foreach ($dataType as $dataTypeName => $month) {
                /** @var Renault\TmKpi $tmKpi */
                $tmKpi = $tmKpiRepository->find($kpiId);
                $kpiQualityResults = new Renault\KpiDataqualityResults();
                $kpiQualityResults
                    ->$entitySetter($entity)
                    ->setKpi($tmKpi)
                    ->setDataType($dataTypeName)
                    ->setEnd($endDateValue)
                ;
                foreach ($month as $monthValue => $kpiValue) {
                    if ($dataTypeName === Renault\KpiDataqualityResults::DATA_TYPE_START_PERIOD_TO_DATE) {
                        $kpiQualityResults->setPeriodToDate($kpiValue);
                    } else {
                        $monthSetter = $this->preCalculateConfig['monthSetters'][$monthValue];
                        $kpiQualityResults->$monthSetter($kpiValue);
                    }
                }
                $this->entityManager->persist($kpiQualityResults);
            }
        }

        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    /**
     * @param $resultsArray
     * @param $entitiesCount
     * @param \DateTime $startDate
     * @throws \Exception
     */
    private function populateVocResults($resultsArray, $entitiesCount, \DateTime $startDate)
    {
        $countryRepository = $this->entityManager->getRepository(Renault\Country::class);
        $hubRepository = $this->entityManager->getRepository(Renault\Hub::class);
        $subhubRepository = $this->entityManager->getRepository(Renault\Subhub::class);
        $dealerRepository = $this->entityManager->getRepository(Renault\Dealer::class);

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
    }

    /**
     * @param $kpi
     * @param $entitySetter
     * @param $entity
     * @param $endDateValue
     */
    private function populateKpiVocResults($kpi, $entitySetter, $entity, $endDateValue)
    {
        $tmKpiRepository = $this->entityManager->getRepository(Renault\TmKpi::class);

        foreach ($kpi as $kpiId => $dataType) {
            foreach ($dataType as $dataTypeName => $month) {
                /** @var Renault\TmKpi $tmKpi */
                $tmKpi = $tmKpiRepository->find($kpiId);
                $kpiVocResults = new Renault\KpiVocResults();
                $kpiVocResults
                    ->$entitySetter($entity)
                    ->setKpi($tmKpi)
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

        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    /**
     * @param $storecheckResultsArray
     * @param $entitiesCount
     * @param \DateTime $startDate
     * @throws \Exception
     */
    private function populateStorecheckResults($storecheckResultsArray, $entitiesCount, \DateTime $startDate)
    {
        $countryRepository = $this->entityManager->getRepository(Renault\Country::class);
        $hubRepository = $this->entityManager->getRepository(Renault\Hub::class);
        $subhubRepository = $this->entityManager->getRepository(Renault\Subhub::class);
        $dealerRepository = $this->entityManager->getRepository(Renault\Dealer::class);

        $entityNumber = 1;

        /** @var Renault\Repository\KpiStorecheckResultsRepository $kpiRepository */
        $kpiRepository = $this->entityManager->getRepository(Renault\KpiStorecheckResults::class);
        $kpiRepository->deleteDataByDate(new \DateTime($startDate->format('Y-m-d')));

        foreach ($storecheckResultsArray['countries'] as $id => $kpi) {
            echo 'Saving entity: ' . $entityNumber . '/' . $entitiesCount . "\n";
            echo 'country: ' . $id . "\n";
            $entity = $countryRepository->find($id);
            $entitySetter = 'setCountry';
            $this->populateKpiStorecheckResults($kpi, $entitySetter, $entity, $startDate);
            $entityNumber++;
        }

        foreach ($storecheckResultsArray['hubs'] as $id => $kpi) {
            echo 'Saving entity: ' . $entityNumber . '/' . $entitiesCount . "\n";
            echo 'hub: ' . $id . "\n";
            $entity = $hubRepository->find($id);
            $entitySetter = 'setHub';
            $this->populateKpiStorecheckResults($kpi, $entitySetter, $entity, $startDate);
            $entityNumber++;
        }

        foreach ($storecheckResultsArray['subhubs'] as $id => $kpi) {
            echo 'Saving entity: ' . $entityNumber . '/' . $entitiesCount . "\n";
            echo 'subhub: ' . $id . "\n";
            $entity = $subhubRepository->find($id);
            $entitySetter = 'setSubhub';
            $this->populateKpiStorecheckResults($kpi, $entitySetter, $entity, $startDate);
            $entityNumber++;
        }

        foreach ($storecheckResultsArray['dealers'] as $id => $kpi) {
            echo 'Saving entity: ' . $entityNumber . '/' . $entitiesCount . "\n";
            echo 'dealer: ' . $id . "\n";
            $entity = $dealerRepository->find($id);
            $entitySetter = 'setDealer';
            $this->populateKpiStorecheckResults($kpi, $entitySetter, $entity, $startDate);
            $entityNumber++;
        }
    }

    /**
     * @param $kpi
     * @param $entitySetter
     * @param $entity
     * @param $endDateValue
     */
    private function populateKpiStorecheckResults($kpi, $entitySetter, $entity, $endDateValue)
    {
        $tmKpiRepository = $this->entityManager->getRepository(Renault\TmKpi::class);

        foreach ($kpi as $kpiId => $dataType) {
            foreach ($dataType as $dataTypeName => $month) {
                /** @var Renault\TmKpi $tmKpi */
                $tmKpi = $tmKpiRepository->find($kpiId);
                $kpiVocResults = new Renault\KpiStorecheckResults();
                $kpiVocResults
                    ->$entitySetter($entity)
                    ->setKpi($tmKpi)
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

        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    /**
     * @param $detractorsResultsArray
     * @param $entitiesCount
     * @param \DateTime $startDate
     * @throws \Exception
     */
    private function populateDetractorsResults($detractorsResultsArray, $entitiesCount, \DateTime $startDate)
    {
        $countryRepository = $this->entityManager->getRepository(Renault\Country::class);
        $hubRepository = $this->entityManager->getRepository(Renault\Hub::class);
        $subhubRepository = $this->entityManager->getRepository(Renault\Subhub::class);
        $dealerRepository = $this->entityManager->getRepository(Renault\Dealer::class);

        $entityNumber = 1;

        /** @var Renault\Repository\KpiVocResultsRepository $kpiRepository */
        $kpiRepository = $this->entityManager->getRepository(Renault\KpiDetractorsResults::class);
        $kpiRepository->deleteDataByDate(new \DateTime($startDate->format('Y-m-d')));

        foreach ($detractorsResultsArray['countries'] as $id => $kpi) {
            echo 'Saving entity: ' . $entityNumber . '/' . $entitiesCount . "\n";
            echo 'country: ' . $id . "\n";
            $entity = $countryRepository->find($id);
            $entitySetter = 'setCountry';
            $this->populateKpiDetractorsResults($kpi, $entitySetter, $entity, $startDate);
            $entityNumber++;
        }

        foreach ($detractorsResultsArray['hubs'] as $id => $kpi) {
            echo 'Saving entity: ' . $entityNumber . '/' . $entitiesCount . "\n";
            echo 'hub: ' . $id . "\n";
            $entity = $hubRepository->find($id);
            $entitySetter = 'setHub';
            $this->populateKpiDetractorsResults($kpi, $entitySetter, $entity, $startDate);
            $entityNumber++;
        }

        foreach ($detractorsResultsArray['subhubs'] as $id => $kpi) {
            echo 'Saving entity: ' . $entityNumber . '/' . $entitiesCount . "\n";
            echo 'subhub: ' . $id . "\n";
            $entity = $subhubRepository->find($id);
            $entitySetter = 'setSubhub';
            $this->populateKpiDetractorsResults($kpi, $entitySetter, $entity, $startDate);
            $entityNumber++;
        }

        foreach ($detractorsResultsArray['dealers'] as $id => $kpi) {
            echo 'Saving entity: ' . $entityNumber . '/' . $entitiesCount . "\n";
            echo 'dealer: ' . $id . "\n";
            $entity = $dealerRepository->find($id);
            $entitySetter = 'setDealer';
            $this->populateKpiDetractorsResults($kpi, $entitySetter, $entity, $startDate);
            $entityNumber++;
        }
    }

    /**
     * @param $kpi
     * @param $entitySetter
     * @param $entity
     * @param $endDateValue
     */
    private function populateKpiDetractorsResults($kpi, $entitySetter, $entity, $endDateValue)
    {
        $tmKpiRepository = $this->entityManager->getRepository(Renault\TmKpi::class);

        foreach ($kpi as $kpiId => $dataType) {
            foreach ($dataType as $dataTypeName => $month) {
                /** @var Renault\TmKpi $tmKpi */
                $tmKpi = $tmKpiRepository->find($kpiId);
                $kpiDetractorsResults = new Renault\KpiDetractorsResults();
                $kpiDetractorsResults
                    ->$entitySetter($entity)
                    ->setKpi($tmKpi)
                    ->setDataType($dataTypeName)
                    ->setEnd($endDateValue)
                ;
                foreach ($month as $monthValue => $kpiValue) {
                    $monthSetter = $this->preCalculateConfig['monthSetters'][$monthValue];
                    $kpiDetractorsResults
                        ->$monthSetter($kpiValue)
                    ;
                }
                $this->entityManager->persist($kpiDetractorsResults);
            }
        }

        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    /**
     * @param \DateTime $endDateValue
     * @return bool
     * @throws \Exception
     */
    private function preCalculateTop5(\DateTime $endDateValue)
    {
        echo 'Start top5: ' . (new \DateTime())->format('H:i:s') . "\n";

        /** @var Renault\DoctrineAbstract\KpiReportDataAbstract $kpiResultsRepository */
        $kpiResultsRepository = $this->entityManager->getRepository(Renault\KpiVocResults::class);
        list($hubsTop5, $dealersTop5) = $this->fetchTop5('voc', $kpiResultsRepository, $endDateValue);
        $this->populateTop5('voc', $hubsTop5, Renault\ExtendClasses\KpiResultsConstants::DATA_TYPE_TOP_5_HUBS, $endDateValue);
        $this->populateTop5('voc', $dealersTop5, Renault\ExtendClasses\KpiResultsConstants::DATA_TYPE_TOP_5_DEALERS, $endDateValue);
        $this->entityManager->flush();

        /** @var Renault\DoctrineAbstract\KpiReportDataAbstract $kpiResultsRepository */
        $kpiResultsRepository = $this->entityManager->getRepository(Renault\KpiStorecheckResults::class);
        list($hubsTop5, $dealersTop5) = $this->fetchTop5('storecheck', $kpiResultsRepository, $endDateValue);
        $this->populateTop5('storecheck', $hubsTop5, Renault\ExtendClasses\KpiResultsConstants::DATA_TYPE_TOP_5_HUBS, $endDateValue);
        $this->populateTop5('storecheck', $dealersTop5, Renault\ExtendClasses\KpiResultsConstants::DATA_TYPE_TOP_5_DEALERS, $endDateValue);
        $this->entityManager->flush();

        /** @var Renault\DoctrineAbstract\KpiReportDataAbstract $kpiResultsRepository */
        $kpiResultsRepository = $this->entityManager->getRepository(Renault\KpiDetractorsResults::class);
        list($hubsTop5, $dealersTop5) = $this->fetchTop5('detractors', $kpiResultsRepository, $endDateValue);
        $this->populateTop5('detractors', $hubsTop5, Renault\ExtendClasses\KpiResultsConstants::DATA_TYPE_TOP_5_HUBS, $endDateValue);
        $this->populateTop5('detractors', $dealersTop5, Renault\ExtendClasses\KpiResultsConstants::DATA_TYPE_TOP_5_DEALERS, $endDateValue);
        $this->entityManager->flush();

        echo 'End top5: ' . (new \DateTime())->format('H:i:s') . "\n";

        return null;
    }

    /**
     * @param $stream
     * @param Renault\DoctrineAbstract\KpiReportDataAbstract $kpiResultsRepository
     * @param \DateTime $endDateValue
     * @return array
     */
    private function fetchTop5($stream, Renault\DoctrineAbstract\KpiReportDataAbstract $kpiResultsRepository, \DateTime $endDateValue)
    {
        $params['end'] = $endDateValue->format('Y-m-d');
        $params['dataType'] = Renault\KpiVocResults::DATA_TYPE_MONTH;

        $hubsTop5 = [];
        $dealersTop5 = [];

        if ($stream === 'voc') {
            $kpiIds = $this->preCalculateConfig['kpi-ids'];
        } elseif ($stream === 'storecheck') {
            $kpiIds = $this->preCalculateConfig['storecheck-kpi-ids'];
        } else {
            $kpiIds = $this->preCalculateConfig['detractors-kpi-ids'];
        }

        foreach ([Renault\Country::NETHERLANDS_ID, Renault\Country::BELUX_ID] as $country) {
            $params['country'] = $country;

            if ($stream === 'storecheck') {
                if ($country === Renault\Country::NETHERLANDS_ID) {
                    $kpiIds = [Renault\TmKpi::STORECHECK_NL];
                } else {
                    $kpiIds = [Renault\TmKpi::CARE_DEALER_CHECK_BE];
                }
            }
            foreach ($kpiIds as $kpi) {
                $params['kpi'] = $kpi;
                foreach ($this->preCalculateConfig['months'] as $month) {
                    $params['month'] = $month;

                    $params['hub'] = true;
                    $top5Hubs = $kpiResultsRepository->getTop5($params);
                    $top5HubsNumbers = [];
                    foreach ($top5Hubs as $hub) {
                        if ($hub[$month] !== null) {
                            $top5HubsNumbers[] = $hub[$month];
                        }
                    }
                    if (empty($top5HubsNumbers)) {
                        $hubsTop5[$country][$kpi][$month] = null;
                    } else {
                        $hubsTop5[$country][$kpi][$month] = number_format(array_sum($top5HubsNumbers) / count($top5HubsNumbers), 1);
                    }
                    $params['hub'] = false;
                    $top5Dealers = $kpiResultsRepository->getTop5($params);
                    $top5DealerNumbers = [];
                    foreach ($top5Dealers as $dealer) {
                        if ($dealer[$month] !== null) {
                            $top5DealerNumbers[] = $dealer[$month];
                        }
                    }
                    if (empty($top5DealerNumbers)) {
                        $dealersTop5[$country][$kpi][$month] = null;
                    } else {
                        $dealersTop5[$country][$kpi][$month] = number_format(array_sum($top5DealerNumbers) / count($top5DealerNumbers), 1);
                    }
                }
            }
        }

        return [$hubsTop5, $hubsTop5];
    }

    /**
     * @param $stream
     * @param array $top5Array
     * @param $dataType
     * @param \DateTime $end
     */
    private function populateTop5($stream, array $top5Array, $dataType, \DateTime $end)
    {
        $tmKpiRepository = $this->entityManager->getRepository(Renault\TmKpi::class);
        $countryRepository = $this->entityManager->getRepository(Renault\Country::class);

        foreach ($top5Array as $country => $kpi) {
            /** @var Renault\Country $countryValue */
            $countryValue = $countryRepository->find($country);
            foreach ($kpi as $kpiValue => $months) {
                /** @var Renault\TmKpi $kpiValue */
                $kpiValue = $tmKpiRepository->find($kpiValue);
                if ($stream === 'voc') {
                    $kpiResults = new Renault\KpiVocResults();
                } elseif ($stream === 'storecheck') {
                    $kpiResults = new Renault\KpiStorecheckResults();
                } else {
                    $kpiResults = new Renault\KpiDetractorsResults();
                }
                $kpiResults
                    ->setKpi($kpiValue)
                    ->setEnd($end)
                    ->setDataType($dataType)
                    ->setCountry($countryValue)
                ;
                foreach ($months as $monthValue => $score) {
                    $monthSetter = 'set' . ucfirst($monthValue);
                    $kpiResults->$monthSetter($score);
                }
                $this->entityManager->persist($kpiResults);
            }
        }
    }

    /**
     * @param \DateTime $endDate
     * @return \DateTime
     * @throws \Exception
     */
    public function getStartDate(\DateTime $endDate)
    {
        $year = (int)$endDate->modify('last day of this month')->format('Y');
        $month = (int)$endDate->modify('last day of this month')->format('m');

        if ($month <= 6) {
            $month = '01';
        } else {
            $month = '07';
        }

        return new \DateTime($year . '-' . $month . '-01');
    }

    /**
     * @return array
     */
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
