<?php

declare(strict_types=1);

namespace Ig\IgSlug\Command;

use Ig\IgSlug\Exception;
use Ig\IgSlug\Utility\SlugsUtility;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
            ->addArgument('tablename', InputArgument::REQUIRED, 'the tablename to create/update the slugs')
            ->addArgument('pid', InputArgument::OPTIONAL, 'the pid to use for rebuild', 0)
            ->addOption('recursive', 'R', InputOption::VALUE_OPTIONAL, 'recursive level', 0)
            ->addOption('language', 'L', InputOption::VALUE_REQUIRED, 'limit to languages');
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
        $recursive = (int)($input->getOption('recursive') ?? 999);

        // language: null is all
        $language = $input->getOption('language');
        if ($language !== null) {
            $language = (int)$language;
        }

        $siteLanguages = [];
        if ($pid) {
            try {
                $site = GeneralUtility::makeInstance(SiteFinder::class)->getSiteByPageId($pid);
                $siteLanguages = $site->getAvailableLanguages($this->getBackendUser(), false, $pid);
            } catch (SiteNotFoundException) {
                // no site for this pid found
            }
        }

        $slugsUtility = GeneralUtility::makeInstance(SlugsUtility::class, $siteLanguages);
        try {
            $slugTables = $slugsUtility->getSlugTables([$tablename], true);
        } catch (Exception\TableNotFoundException) {
            $io->error('Error table "' . $tablename . '" not found (no TCA found)');
            return 1;
        } catch (Exception\SlugNotFoundException) {
            $io->error('Error table "' . $tablename . '" has no slug field');
            return 1;
        } catch (Exception\AccessDeniedException) {
            $io->error('Access dienied to modify slug field on table "' . $tablename . '"');
            return 1;
        }

        if (isset($slugTables[$tablename])) {
            $slugTable = $slugTables[$tablename];
            $slugsUtility->setTable($tablename);
            $slugsUtility->setTable($slugTable['table']);
            $slugsUtility->setSlugFieldName($slugTable['slugFieldName']);
            $slugsUtility->setSlugLockedFieldName($slugTable['slugLockedFieldName']);
            $fields = $slugsUtility->getSlugFields();
            $slugsUtility->setFieldNamesToShow($fields);
            if ($tablename == 'pages') {
                $pagesCount = $slugsUtility->populateSlugsByUidRecursive([$pid], $recursive, $language);
            } elseif ($pid > 0) {
                $pageUids = $slugsUtility->getPageRecordsRecursive($pid, $recursive, [$pid]);
                $pagesCount = $slugsUtility->populateSlugs($pageUids, $language);
            } else {
                $pagesCount = $slugsUtility->populateSlugsAll($language);
            }

            $languageService = $this->getLanguageService();
            $message = sprintf(
                $languageService->sL(
                    'LLL:EXT:ig_slug/Resources/Private/Language/locallang.xlf:'
                    . ($pagesCount != 1 ? 'igSlug.populatedSlugs' : 'igSlug.populatedSlug')
                ),
                $pagesCount
            );


            $io->success($message);
            return 0;
        }

        return 1;
    }

    
    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }


    private function getLanguageService(): LanguageService
    {
        return GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }
}
