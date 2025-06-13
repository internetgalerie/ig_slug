<?php

declare(strict_types=1);

namespace Ig\IgSlug\Utility;

use Doctrine\DBAL\Result;
use Ig\IgSlug\Exception;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\WorkspaceRestriction;
use TYPO3\CMS\Core\DataHandling\SlugEnricher;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SlugsUtility
{
    protected string $table = 'pages';

    protected string $slugFieldName = 'slug';

    protected ?string $slugLockedFieldName = 'slug_locked';

    protected array $fieldNamesToShow = ['title'];

    protected int $countUpdates = 0;

    protected int $maxDepth = 100;

    protected array $siteLanguagesIds = [];

    protected $siteLanguages;

    protected $slugUtility;


    /**
     * Instantiate the form protection before a simulated user is initialized.
     */
    public function __construct(array $siteLanguages)
    {
        $this->siteLanguages = $siteLanguages;
        foreach ($this->siteLanguages as $language) {
            $this->siteLanguagesIds[] = $language->getLanguageId();
        }
    }

    public function getLanguageIds(): array
    {
        return $this->siteLanguagesIds;
    }

    public function hasLanguageId(int $languageId): bool
    {
        return in_array($languageId, $this->siteLanguagesIds);
    }

    public function setTable(string $table): void
    {
        $this->table = $table;
    }

    public function setSlugFieldName(string $slugFieldName): void
    {
        $this->slugFieldName = $slugFieldName;
    }

    public function setSlugLockedFieldName(?string $slugLockedFieldName): void
    {
        $this->slugLockedFieldName = $slugLockedFieldName;
    }

    public function setFieldNamesToShow(array $fieldNamesToShow): void
    {
        $this->fieldNamesToShow = $fieldNamesToShow;
    }

    public function populateSlugsAll(?int $lang = null): int
    {
        $this->doSlugsAll(true, $lang);
        return $this->countUpdates;
    }

    public function populateSlugs(array $uids, ?int $lang = null): int
    {
        $this->doSlugs($uids, true, false, 1, $lang);
        return $this->countUpdates;
    }

    public function populateSlugsByUidRecursive(array $uids, int $depth, ?int $lang = null): int
    {
        $this->doSlugs($uids, true, true, $depth, $lang);
        return $this->countUpdates;
    }

    public function viewSlugs(array $uids, ?int $lang = null): array
    {
        return $this->doSlugs($uids, false, false, 1, $lang);
    }

    public function viewSlugsByUidRecursive(array $uids, int $depth, ?int $lang = null): array
    {
        return $this->doSlugs($uids, false, true, $depth, $lang);
    }

    /**
     * Fills the database table with slugs based on the slug fields and its configuration.
     */
    public function doSlugs(
        array $uids,
        bool $doUdpates = false,
        bool $recursive = false,
        int $maxDepth = 100,
        ?int $lang = null
    ): array {
        if (!$this->hasTableSlugFieldModifyAccess($this->table, $this->slugFieldName)) {
            return [];
        }

        if ($uids === []) {
            return [];
        }

        foreach ($uids as $uid) {
            if ($uid == 0) {
                return [];
            }
        }

        $this->maxDepth = $maxDepth;
        $this->countUpdates = 0;

        $this->slugUtility = GeneralUtility::makeInstance(
            SlugUtility::class,
            $this->table,
            $this->slugFieldName,
            $this->slugLockedFieldName,
            $this->fieldNamesToShow,
            $this->siteLanguages
        );

        if ($this->table == 'pages') {
            return $this->doSlugsByUid($uids, $doUdpates, $recursive, 0, $lang);
        }
        return $this->doSlugsByPid($uids, $doUdpates, $recursive, 0, $lang, false);
    }



    /**
     * Fills the database table with slugs based on the slug fields and its configuration.
     */
    public function doSlugsByUid(
        array $uids,
        bool $doUdpates = false,
        bool $recursive = false,
        int $depth = 10,
        ?int $lang = null
    ): array {
        $entries = [];
        $recursiveEntries = [];
        $pageRows = $this->getStatementByUid($uids, $lang);
        foreach ($pageRows as $record) {
            $hasUpdate = false;
            if (
                $this->table != 'pages' ||
                (
                    $GLOBALS['TCA'][$this->table]['ctrl']['languageField']
                    && ($lang === null || $record[$GLOBALS['TCA'][$this->table]['ctrl']['languageField']] == $lang)
                    && $this->hasLanguageId($record[$GLOBALS['TCA'][$this->table]['ctrl']['languageField']])
                )
            ) {
                $entry = $this->slugUtility->getEntryByRecord($record, $depth);
                $hasUpdate = $entry['updated'];
                if ($doUdpates && $entry['updated']) {
                    ++$this->countUpdates;
                    $this->slugUtility->updateEntry($entry);
                }

                $entries[] = $entry;
            }

            if ($recursive) {
                $subentries = $this->doSlugsByPid(
                    [$record['uid']],
                    $doUdpates,
                    $recursive,
                    $depth + 1,
                    $lang,
                    $hasUpdate
                );
                if ($subentries !== []) {
                    $subentries[count($subentries) - 1]['depthLast'] = true; // mark for easy output
                }

                $recursiveEntries = [...$recursiveEntries, ...$subentries];
            }
        }

        return [...$entries, ...$recursiveEntries];
    }





    /**
     * Fills the database table with slugs based on the slug fields and its configuration.
     */
    public function doSlugsByPid(
        array $uids,
        bool $doUdpates = false,
        bool $recursive = false,
        int $depth = 0,
        ?int $lang = null,
        bool $parentHasUpdates = false
    ): array {
        $entries = [];
        if ($depth > $this->maxDepth) {
            return [];
        }

        $statement = $this->getStatementByPid($uids, $lang);
        while ($record = $statement->fetchAssociative()) {
            $hasUpdate = false;
            if ($this->table != 'pages' ||
                (
                    $GLOBALS['TCA'][$this->table]['ctrl']['languageField']
                    && ($lang === null || $record[$GLOBALS['TCA'][$this->table]['ctrl']['languageField']] == $lang)
                    && $this->hasLanguageId($record[$GLOBALS['TCA'][$this->table]['ctrl']['languageField']])
                )
            ) {
                $entry = $this->slugUtility->getEntryByRecord($record, $depth, $parentHasUpdates);
                $hasUpdate = $entry['updated'];
                if ($doUdpates && $entry['updated']) {
                    ++$this->countUpdates;
                    $this->slugUtility->updateEntry($entry);
                }

                $entries[] = $entry;
            }

            if ($recursive) {
                $subentries = $this->doSlugsByPid(
                    [$record['uid']],
                    $doUdpates,
                    $recursive,
                    $depth + 1,
                    $lang,
                    $parentHasUpdates || $hasUpdate
                );
                if ($subentries !== []) {
                    $subentries[count($subentries) - 1]['depthLast'] = true; // mark for easy output
                }

                $entries = [...$entries, ...$subentries];
            }
        }

        return $entries;
    }



    /**
     * Fills the database table with slugs based on the slug fields and its configuration.
     */
    public function doSlugsAll(bool $doUdpates = false, ?int $lang = null): array
    {
        $entries = [];
        $this->slugUtility = GeneralUtility::makeInstance(
            SlugUtility::class,
            $this->table,
            $this->slugFieldName,
            $this->slugLockedFieldName,
            $this->fieldNamesToShow,
            $this->siteLanguages
        );

        $statement = $this->getStatementAll($lang);
        while ($record = $statement->fetchAssociative()) {
            if ($this->table != 'pages' ||
                (
                    $GLOBALS['TCA'][$this->table]['ctrl']['languageField']
                    && ($lang === null || $record[$GLOBALS['TCA'][$this->table]['ctrl']['languageField']] == $lang)
                    && $this->hasLanguageId($record[$GLOBALS['TCA'][$this->table]['ctrl']['languageField']])
                )
            ) {
                $entry = $this->slugUtility->getEntryByRecord($record, 0, false);
                if ($doUdpates && $entry['updated']) {
                    ++$this->countUpdates;
                    $this->slugUtility->updateEntry($entry);
                }

                $entries[] = $entry;
            }
        }

        return $entries;
    }





    public function getSlugFields(): array
    {
        $fields = [];
        $fieldConfig = $GLOBALS['TCA'][$this->table]['columns'][$this->slugFieldName]['config'];
        if (isset($fieldConfig['generatorOptions']['fields'])) {
            foreach ($fieldConfig['generatorOptions']['fields'] as $fieldNameParts) {
                // @todo after Bugfix 59167
                if (is_string($fieldNameParts)) {
                    $fieldNameParts = GeneralUtility::trimExplode(',', $fieldNameParts);
                }

                foreach ($fieldNameParts as $listenerFieldName) {
                    $fields[] = $listenerFieldName; // explode(',')
                }
            }
        }

        return $fields;
    }

    public function getSlugTables(array $tableNames = [], bool $raiseError = true): array
    {
        $slugTables = [];
        $lang = $this->getLanguageService();
        if ($tableNames === []) {
            $tableNames = array_keys($GLOBALS['TCA']);
            $raiseError = false;
        } else {
            foreach ($tableNames as $tableName) {
                if (!isset($GLOBALS['TCA'][$tableName])) {
                    throw new Exception\TableNotFoundException();
                }
            }
        }

        $slugEnricher = GeneralUtility::makeInstance(SlugEnricher::class);
        foreach ($tableNames as $tableName) {
            $slugFields = $slugEnricher->resolveSlugFieldNames($tableName);
            if (count($slugFields)) {
                $slugFieldConfig = $GLOBALS['TCA'][$this->table]['columns'][$this->slugFieldName] ?? [];
                if ($this->hasTableSlugFieldModifyAccess($tableName, $slugFields[0])) {
                    $slugFieldName = $slugFields[0];
                    $slugLockedFieldName = isset($GLOBALS['TCA'][$tableName]['columns'][$slugFieldName . '_locked']) ? $slugFieldName . '_locked' : null;
                    $slugTables[$tableName] = [
                        'table' => $tableName,
                        'slugFieldName' => $slugFieldName,
                        'slugLockedFieldName' => $slugLockedFieldName,
                        'title' => htmlspecialchars($lang->sL($GLOBALS['TCA'][$tableName]['ctrl']['title'])),
                    ];
                } elseif ($raiseError) {
                    throw new Exception\AccessDeniedException();
                }
            } elseif ($raiseError) {
                throw new Exception\SlugNotFoundException();
            }
        }

        return $slugTables;
    }




    public function getPageRecordsRecursive(int $pid, int $depth, array $rows = []): array
    {
        --$depth;
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');
        $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(WorkspaceRestriction::class, $this->getBackendUser()->workspace));

        $queryBuilder
            ->select('uid')
            ->from('pages')
            ->where(
                $queryBuilder->expr()
                             ->eq('pid', $pid),
                $queryBuilder->expr()
                             ->eq('sys_language_uid', 0),
                $this->getBackendUser()
                     ->getPagePermsClause(Permission::PAGE_SHOW)
            );

        if (!empty($GLOBALS['TCA']['pages']['ctrl']['sortby'])) {
            $queryBuilder->orderBy($GLOBALS['TCA']['pages']['ctrl']['sortby']);
        }

        if ($depth >= 0) {
            $result = $queryBuilder->executeQuery();
            while ($row = $result->fetchAssociative()) {
                $rows[] = $row['uid'];
                $rows = $this->getPageRecordsRecursive(
                    $row['uid'],
                    ($row['php_tree_stop'] ?? false) ? 0 : $depth,
                    $rows
                );
            }
        }

        return $rows;
    }


    protected function getStatementByPid($uids, ?int $lang = null): Result
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($this->table);
        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder
            ->getRestrictions()
            ->removeAll()
            //->add(GeneralUtility::makeInstance(WorkspaceRestriction::class, $this->getBackendUser()->workspace))
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $queryBuilder->select('*')
                     ->from($this->table);
        if ($this->table == 'pages') {
            $queryBuilder->where($queryBuilder->expr() ->in('pid', $uids));
        } else {
            $queryBuilder->where($queryBuilder->expr() ->in('pid', $uids));
            // non pages table - only show entries with the correct language
            if ($this->getLanguageIds() !== [] && $GLOBALS['TCA'][$this->table]['ctrl']['languageField']) {
                if (!$this->getBackendUser()->isAdmin() && $this->getBackendUser()->groupData['allowed_languages'] !== '') {
                    $queryBuilder->andWhere(
                        $queryBuilder->expr()
                                     ->in($GLOBALS['TCA'][$this->table]['ctrl']['languageField'], $this->getLanguageIds())
                    );
                }

                if ($lang !== null) {
                    $queryBuilder->andWhere(
                        $queryBuilder->expr()
                                     ->eq(
                                         $GLOBALS['TCA'][$this->table]['ctrl']['languageField'],
                                         $lang
                                     )
                    );
                }
            }
        }

        $queryBuilder->addOrderBy('pid', 'asc');
        if ($GLOBALS['TCA'][$this->table]['ctrl']['sortby'] ?? false) {
            $queryBuilder->addOrderBy($GLOBALS['TCA'][$this->table]['ctrl']['sortby'], 'asc');
        }

        return $queryBuilder->executeQuery();
    }

    // only for table pages
    protected function getStatementByUid(array $uids, ?int $lang = null): array
    {
        if ($uids === []) {
            return [];
        }

        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($this->table);
        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder
            ->getRestrictions()
            ->removeAll()
            //->add(GeneralUtility::makeInstance(WorkspaceRestriction::class, $this->getBackendUser()->workspace))
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $queryBuilder->select('*')
                     ->from($this->table)
                     ->where(
                         $queryBuilder->expr()
                                      ->or($queryBuilder->expr() ->in('uid', $uids), $queryBuilder->expr() ->in('l10n_parent', $uids)),
                         $this->getBackendUser()
                              ->getPagePermsClause(Permission::PAGE_SHOW)
                     );

        /*
          if($lang !== null) {
          $queryBuilder->andWhere($queryBuilder->expr()->eq('sys_language_uid', $lang));
          }
        */

        return $queryBuilder
            // Ensure that live workspace records are handled first
            ->orderBy('t3ver_wsid', 'asc')
            // Ensure that all pages are run through "per parent page" field, and in the correct sorting values
            ->addOrderBy('pid', 'asc')
            ->addOrderBy('sorting', 'asc')
            ->executeQuery()
            ->fetchAllAssociative();
    }


    protected function getStatementAll(?int $lang = null): Result
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($this->table);
        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $queryBuilder->select('*')
                     ->from($this->table);
        // only show entries with the correct language
        if ($this->getLanguageIds() !== [] && $GLOBALS['TCA'][$this->table]['ctrl']['languageField']) {
            if (!$this->getBackendUser()->isAdmin() && $this->getBackendUser()->groupData['allowed_languages'] !== '') {
                $queryBuilder->andWhere(
                    $queryBuilder->expr()
                                 ->in($GLOBALS['TCA'][$this->table]['ctrl']['languageField'], $this->getLanguageIds())
                );
            }

            if ($lang !== null) {
                $queryBuilder->andWhere(
                    $queryBuilder->expr()
                                 ->eq(
                                     $GLOBALS['TCA'][$this->table]['ctrl']['languageField'],
                                     $lang
                                 )
                );
            }
        }

        $queryBuilder->addOrderBy('pid', 'asc');
        if ($GLOBALS['TCA'][$this->table]['ctrl']['sortby']) {
            $queryBuilder->addOrderBy($GLOBALS['TCA'][$this->table]['ctrl']['sortby'], 'asc');
        }

        return $queryBuilder->executeQuery();
    }

    protected function hasTableSlugFieldModifyAccess(string $tableName, string $slugFieldName): bool
    {
        $hasTableModifyAccess = $this->getBackendUser()->check('tables_modify', $tableName);
        if (!isset($GLOBALS['TCA'][$tableName]['columns'][$slugFieldName])) {
            return false;
        }
        $slugFieldConfig = $GLOBALS['TCA'][$tableName]['columns'][$slugFieldName];
        $requiresPermission = $slugFieldConfig['exclude'] ?? false;
        $hasFieldAccess = !$requiresPermission || $this->getBackendUser()->check('non_exclude_fields', $tableName . ':' . $slugFieldName);
        return $hasTableModifyAccess && $hasFieldAccess;
    }

    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }


    /**
     * Returns the language service
     */
    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
