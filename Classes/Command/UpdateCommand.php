<?php
declare(strict_types = 1);
namespace Ig\IgSlug\Command;
 
use Ig\IgSlug\Exception;
use Ig\IgSlug\Utility\SlugsUtility;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Command for create/update slugs
 */
class UpdateCommand extends Command
{
    /**
     * Defines the allowed options for this command
     */
    protected function configure()
    {
        $this
            ->setDescription('create/update slugs')
            //->setHelp('')
            //->setAliases(['ig:update'])
            ->addArgument(
                'tablename',
                InputArgument::REQUIRED,
                'the tablename to create/update the slugs'
            )
            ->addArgument(
                'pid',
                InputArgument::OPTIONAL,
                'the pid to use for rebuild',
                0
            )
            ->addArgument(
                'depth',
                InputArgument::OPTIONAL,
                'tree depth',
                0
            );
 
    }
 
    /**
     * create/update slugs
     *
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Make sure the _cli_ user is loaded
        Bootstrap::initializeBackendAuthentication();
        $io = new SymfonyStyle($input, $output);
        $tablename = $input->getArgument('tablename');
        $pid = (int)$input->getArgument('pid');
        $depth = (int)$input->getArgument('depth');
        $lang = null;//all

        $siteLanguages = [];
        if ($pid) {
             try {
                 $site = GeneralUtility::makeInstance(SiteFinder::class)->getSiteByPageId($pid);
                 $siteLanguages = $site->getAvailableLanguages($this->getBackendUser(), false, $pid);
             } catch (SiteNotFoundException $e) {
                 // no site for this pid found
             }
        }
        $slugsUtility = GeneralUtility::makeInstance(SlugsUtility::class, $siteLanguages);
        try {
            $slugTables = $slugsUtility->getSlugTables([$tablename], true);
        } catch(Exception\TableNotFoundException $e) {
            $io->error('Error table "' . $tablename . '" not found (no TCA found)');
            return 1;
        } catch(Exception\SlugNotFoundException $e) {
            $io->error('Error table "' . $tablename . '" has no slug field');
            return 1;
        } catch(Exception\AccessDeniedException $e) {
            $io->error('Access dienied to modify slug field on table "' . $tablename . '"');
            return 1;
        }
        if (isset($slugTables[$tablename])) {
            //$tablename && isset($GLOBALS['TCA'][$tablename])) {
            $slugTable = $slugTables[$tablename];
            $slugsUtility->setTable($tablename);
            $slugsUtility->setTable($slugTable['table']);
            $slugsUtility->setSlugFieldName($slugTable['slugFieldName']);
            $slugsUtility->setSlugLockedFieldName($slugTable['slugLockedFieldName']);
            $fields = $slugsUtility->getSlugFields();
            $slugsUtility->setFieldNamesToShow($fields);
             if ($tablename=='pages') {
                 $pagesCount = $slugsUtility->populateSlugsByUidRecursive([$pid], $depth, $lang);
            } else {
                 if ($pid>0) {
                     $pageUids = $slugsUtility->getPageRecordsRecursive($pid, $depth, [$pid]);
                     $pagesCount=$slugsUtility->populateSlugs($pageUids, $lang);
                 } else {
                     $pagesCount=$slugsUtility->populateSlugsAll( $lang);
                 }
            }
             $languageService = $this->getLanguageService();
             $message = sprintf($languageService->sL('LLL:EXT:ig_slug/Resources/Private/Language/locallang.xlf:' . ($pagesCount!=1 ? 'igSlug.populatedSlugs' : 'igSlug.populatedSlug')), $pagesCount);


            $io->success($message);
            return 0;
        }
        
        return 1;
     }

    /**
     * @return BackendUserAuthentication
     */
    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }


    private function getLanguageService(): LanguageService
    {
        return GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }
}
