<?php
declare(strict_types = 1);
namespace Ig\IgSlug\Utility;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\DataHandling\Model\RecordStateFactory;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Database\Query\Restriction\BackendWorkspaceRestriction;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Cache\CacheManager;

class SlugsUtility
{
    protected $table = 'pages';
  
    protected $slugFieldName = 'slug';
    protected $slugLockedFieldName = 'slug_locked';
    protected $fieldNamesToShow = ['title'];
    protected $countUpdates =0;
    protected $maxDepth =100;
    protected $siteLanguagesIds=[];
    /**
     * Instantiate the form protection before a simulated user is initialized.
     *
     * @param array $siteLanguages
     */
    public function __construct(array $siteLanguages)
    {
        $this->siteLanguages = $siteLanguages;
        foreach ($this->siteLanguages as $language) {
            $this->siteLanguagesIds[]=$language->getLanguageId();
        }
    }
    public function getLanguageIds()
    {
        return $this->siteLanguagesIds;
    }
    public function hasLanguageId($languageId)
    {
        return in_array($languageId, $this->siteLanguagesIds);
    }
    public function setTable($table)
    {
        $this->table=$table;
    }
  
    public function setSlugFieldName($slugFieldName)
    {
        $this->slugFieldName=$slugFieldName;
    }
    public function setSlugLockedFieldName($slugLockedFieldName)
    {
        $this->slugLockedFieldName=$slugLockedFieldName;
    }
    public function setFieldNamesToShow($fieldNamesToShow)
    {
        $this->fieldNamesToShow=$fieldNamesToShow;
    }
  
    public function populateSlugsAll(int $lang=null)
    {
        $this->doSlugsAll(true, $lang);
        return $this->countUpdates;
    }

    public function populateSlugs(array $uids, int $lang=null)
    {
        $this->doSlugs($uids, true, false, 1, $lang);
        return $this->countUpdates;
    }
    public function populateSlugsByUidRecursive(array $uids, int $depth, int $lang=null)
    {
        $this->doSlugs($uids, true, true, $depth, $lang);
        return $this->countUpdates;
    }
    public function viewSlugs(array $uids, int $lang=null)
    {
        return $this->doSlugs($uids, false, false, 1, $lang);
    }
    public function viewSlugsByUidRecursive(array $uids, int $depth, int $lang=null)
    {
        return $this->doSlugs($uids, false, true, $depth, $lang);
    }

    /**
     * Fills the database table with slugs based on the slug fields and its configuration.
     */
    public function doSlugs(array $uids, bool $doUdpates=false, bool $recursive=false, int $maxDepth=100, int $lang=null) :array
    {
        if (!$GLOBALS['BE_USER']->check('non_exclude_fields', $this->table . ':' . $this->slugFieldName)) {
            return [];
        }
        if (count($uids)==0) {
            return [];
        }
        foreach ($uids as $uid) {
            if ($uid==0) {
                return [];
            }
        }
        $this->maxDepth=$maxDepth;
        $this->countUpdates=0;
      
        $entries=[];

        $this->slugUtility = GeneralUtility::makeInstance(SlugUtility::class, $this->table, $this->slugFieldName, $this->slugLockedFieldName, $this->fieldNamesToShow);

        if ($this->table=='pages') {
            return $this->doSlugsByUid($uids, $doUdpates, $recursive, 0, $lang);
        } else {
            return $this->doSlugsByPid($uids, $doUdpates, $recursive, 0, $lang, false);
        }
    }



    /**
     * Fills the database table with slugs based on the slug fields and its configuration.
     */
    public function doSlugsByUid(array $uids, bool $doUdpates=false, bool $recursive=false, int $depth=10, int $lang=null) :array
    {
        $entries=[];
        $recursiveEntries=[];
        $statement = $this->getStatementByUid($uids, $lang);
        while ($record = $statement->fetch()) {
            $hasUpdate=false;
            if ($this->table!='pages' || ($GLOBALS['TCA'][$this->table]['ctrl']['languageField'] && ($lang===null || $record[$GLOBALS['TCA'][$this->table]['ctrl']['languageField']]==$lang) && $this->hasLanguageId($record[$GLOBALS['TCA'][$this->table]['ctrl']['languageField']]))) {
                //die($GLOBALS['TCA'][$this->table]['ctrl']['languageField'] .'='. $record[$GLOBALS['TCA'][$this->table]['ctrl']['languageField']] .' mit '. print_r($this->siteLanguagesIds,true). '='. $this->hasLanguageId($record[$GLOBALS['TCA'][$this->table]['ctrl']['languageField']]));
                $entry = $this->slugUtility->getEntryByRecord($record, $depth);
                $hasUpdate=$entry['updated'];
                if ($doUdpates && $entry['updated']) {
                    $this->countUpdates++;
                    $this->slugUtility->updateEntry($entry);
                }
                $entries[] = $entry;
            }
            if ($recursive) {
                $subentries=$this->doSlugsByPid([$record['uid']], $doUdpates, $recursive, $depth+1, $lang, $hasUpdate);
                if (count($subentries)) {
                    $subentries[count($subentries)-1]['depthLast']=true; // mark for easy output
                }
                $recursiveEntries=array_merge($recursiveEntries, $subentries);
            }
        }
        return array_merge($entries, $recursiveEntries);
    }




  
    /**
     * Fills the database table with slugs based on the slug fields and its configuration.
     */
    public function doSlugsByPid(array $uids, bool $doUdpates=false, bool $recursive=false, int $depth=0, int $lang=null, bool $parentHasUpdates=false) :array
    {
        $entries=[];
        //$depth--;
        if ($depth>$this->maxDepth) {
            return [];
        }
        $statement = $this->getStatementByPid($uids, $lang);
        while ($record = $statement->fetch()) {
            $hasUpdate=false;
            if ($this->table!='pages' || ($GLOBALS['TCA'][$this->table]['ctrl']['languageField'] && ($lang===null || $record[$GLOBALS['TCA'][$this->table]['ctrl']['languageField']]==$lang) && $this->hasLanguageId($record[$GLOBALS['TCA'][$this->table]['ctrl']['languageField']]))) {
                $entry = $this->slugUtility->getEntryByRecord($record, $depth, $parentHasUpdates);
                $hasUpdate=$entry['updated'];
                if ($doUdpates && $entry['updated']) {
                    $this->countUpdates++;
                    $this->slugUtility->updateEntry($entry);
                }
                $entries[] = $entry;
            }
            if ($recursive) {
                $subentries=$this->doSlugsByPid([$record['uid']], $doUdpates, $recursive, $depth+1, $lang, $parentHasUpdates || $hasUpdate);
                if (count($subentries)) {
                    $subentries[count($subentries)-1]['depthLast']=true; // mark for easy output
                }
                $entries=array_merge($entries, $subentries);
            }
        }
        return $entries;
    }


  
    /**
     * Fills the database table with slugs based on the slug fields and its configuration.
     */
    public function doSlugsAll(bool $doUdpates=false, int $lang=null) :array
    {
        $entries=[];
        $this->slugUtility = GeneralUtility::makeInstance(SlugUtility::class, $this->table, $this->slugFieldName, $this->slugLockedFieldName, $this->fieldNamesToShow);

        $statement = $this->getStatementAll($lang);
        while ($record = $statement->fetch()) {
            $hasUpdate=false;
            if ($this->table!='pages' || ($GLOBALS['TCA'][$this->table]['ctrl']['languageField'] && ($lang===null || $record[$GLOBALS['TCA'][$this->table]['ctrl']['languageField']]==$lang) && $this->hasLanguageId($record[$GLOBALS['TCA'][$this->table]['ctrl']['languageField']]))) {
                $entry = $this->slugUtility->getEntryByRecord($record, 0, false);
                $hasUpdate=$entry['updated'];
                if ($doUdpates && $entry['updated']) {
                    $this->countUpdates++;
                    $this->slugUtility->updateEntry($entry);
                }
                $entries[] = $entry;
            }
        }
        return $entries;
    }




  
    public function getSlugFields()
    {
        $fields=[];
        $fieldConfig = $GLOBALS['TCA'][$this->table]['columns'][$this->slugFieldName]['config'];
        if (isset($fieldConfig['generatorOptions']['fields'])) {
            foreach ($fieldConfig['generatorOptions']['fields'] as $fieldNameParts) {
                // @todo after Bugfix 59167
                if (is_string($fieldNameParts)) {
                    $fieldNameParts = GeneralUtility::trimExplode(',', $fieldNameParts);
                }
                foreach ($fieldNameParts as $listenerFieldName) {
                    $fields[]=$listenerFieldName; // explode(',')
                }
            }
        }
        return $fields;
    }


    protected function getStatementByPid($uids, int $lang=null)
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($this->table);
        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
    
        $queryBuilder->select('*')
            ->from($this->table);
        if ($this->table=='pages') {
            $queryBuilder->where(
                $queryBuilder->expr()->in('pid', $uids)
            );
        } else {
            $queryBuilder->where(
                $queryBuilder->expr()->in('pid', $uids)
            );
            // non pages table - only show entries with the correct language
            if ( !empty($this->getLanguageIds()) && $GLOBALS['TCA'][$this->table]['ctrl']['languageField']) {
                if (!$this->getBackendUser()->isAdmin() && $this->getBackendUser()->groupData['allowed_languages'] !== '') {
                    $queryBuilder->andWhere($queryBuilder->expr()->in($GLOBALS['TCA'][$this->table]['ctrl']['languageField'], $this->getLanguageIds()));
                }
                if ($lang!==null) {
                    $queryBuilder->andWhere($queryBuilder->expr()->eq($GLOBALS['TCA'][$this->table]['ctrl']['languageField'], $queryBuilder->createNamedParameter($lang, \PDO::PARAM_INT)));
                }
            }
        }
        $queryBuilder->addOrderBy('pid', 'asc');
        if ($GLOBALS['TCA'][$this->table]['ctrl']['sortby']) {
            $queryBuilder->addOrderBy($GLOBALS['TCA'][$this->table]['ctrl']['sortby'], 'asc');
        }

        return $queryBuilder->execute();
    }

    // only for table pages
    protected function getStatementByUid(array $uids, int $lang=null)
    {
        if (count($uids)==0) {
            return [];
        }
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($this->table);
        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        // ->add(GeneralUtility::makeInstance(BackendWorkspaceRestriction::class)
        $queryBuilder->select('*')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->in('uid', $uids),
                    $queryBuilder->expr()->in('l10n_parent', $uids)
                ),
                $this->getBackendUser()->getPagePermsClause(Permission::PAGE_SHOW)
            );

        /*
          if( $lang!==null) {
          $queryBuilder->andWhere( $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($lang, \PDO::PARAM_INT)));
          }
        */

        return $queryBuilder
            // Ensure that live workspace records are handled first
            ->orderBy('t3ver_wsid', 'asc')
            // Ensure that all pages are run through "per parent page" field, and in the correct sorting values
            ->addOrderBy('pid', 'asc')
            ->addOrderBy('sorting', 'asc')
            ->execute();
    }
  

    protected function getStatementAll(int $lang=null)
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($this->table);
        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
    
        $queryBuilder->select('*')
            ->from($this->table);
        // only show entries with the correct language
        if ( !empty($this->getLanguageIds()) && $GLOBALS['TCA'][$this->table]['ctrl']['languageField']) {
            if (!$this->getBackendUser()->isAdmin() && $this->getBackendUser()->groupData['allowed_languages'] !== '') {
                $queryBuilder->andWhere($queryBuilder->expr()->in($GLOBALS['TCA'][$this->table]['ctrl']['languageField'], $this->getLanguageIds()));
            }
            if ($lang!==null) {
                $queryBuilder->andWhere($queryBuilder->expr()->eq($GLOBALS['TCA'][$this->table]['ctrl']['languageField'], $queryBuilder->createNamedParameter($lang, \PDO::PARAM_INT)));
            }
        }
        $queryBuilder->addOrderBy('pid', 'asc');
        if ($GLOBALS['TCA'][$this->table]['ctrl']['sortby']) {
            $queryBuilder->addOrderBy($GLOBALS['TCA'][$this->table]['ctrl']['sortby'], 'asc');
        }
        
        return $queryBuilder->execute();
    }

  

    public function getSlugTables()
    {
        $slugTables=[];
        $lang = $this->getLanguageService();

        $tableNames = array_flip(array_keys($GLOBALS['TCA']));
        $slugEnricher=GeneralUtility::makeInstance(\TYPO3\CMS\Core\DataHandling\SlugEnricher::class);
        foreach ($tableNames as $tableName => &$config) {
            if ($tableName!='pages' || 1) {
                $slugFields=$slugEnricher->resolveSlugFieldNames($tableName);
                if (count($slugFields)
                    && $GLOBALS['BE_USER']->check('non_exclude_fields', $tableName . ':' . $slugFields[0])) {
                    $slugFieldName=$slugFields[0];
                    $slugLockedFieldName= isset($GLOBALS['TCA'][$tableName]['columns'][$slugFieldName . '_locked']) ? $slugFieldName . '_locked' : null;
                    $slugTables[$tableName]=[
                        'table' => $tableName,
                        'slugFieldName' => $slugFieldName,
                        'slugLockedFieldName' => $slugLockedFieldName,
                        'title' => htmlspecialchars($lang->sL($GLOBALS['TCA'][$tableName]['ctrl']['title'])),
                    ];
                }
            }
        }
        return $slugTables;
    }



  
    public function getPageRecordsRecursive(int $pid, int $depth, array $rows = []): array
    {
        $depth--;
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(BackendWorkspaceRestriction::class));
        $queryBuilder
            ->select('uid')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)),
                $this->getBackendUser()->getPagePermsClause(Permission::PAGE_SHOW)
            );
      
        if (!empty($GLOBALS['TCA']['pages']['ctrl']['sortby'])) {
            $queryBuilder->orderBy($GLOBALS['TCA']['pages']['ctrl']['sortby']);
        }
      
        if ($depth >= 0) {
            $result = $queryBuilder->execute();
            $rowCount = $queryBuilder->count('uid')->execute()->fetchColumn(0);
            $count = 0;
            while ($row = $result->fetch()) {
                $rows[] = $row['uid'];
                $rows = $this->getPageRecordsRecursive(
                    $row['uid'],
                    $row['php_tree_stop'] ? 0 : $depth,
                    $rows
                );
            }
        }
        return $rows;
    }
    /**
     * @return BackendUserAuthentication
     */
    protected function getBackendUser()
    {
        return $GLOBALS['BE_USER'];
    }


    /**
     * Returns the language service
     * @return LanguageService
     */
    protected function getLanguageService()
    {
        return $GLOBALS['LANG'];
    }
}
