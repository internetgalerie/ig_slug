<?php
declare(strict_types = 1);
namespace Ig\IgSlug\Command;
 
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

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
            );
 
    }
 
    /**
     * create/update slugs
     *
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $tablename = $input->getArgument('tablename');
        $pid = intval($input->getArgument('pid'));
        $depth=0;//all
        $lang=null;//all
        // Ensure the _cli_ user is authenticated because the extension might import data
        Bootstrap::initializeBackendAuthentication();
 
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        //$this->initializeSiteLanguages();
        $slugsUtility= $objectManager->get(\Ig\IgSlug\Utility\SlugsUtility::class, []);//$this->siteLanguages);
        $slugTables=$slugsUtility->getSlugTables();
        
        if( isset($slugTables[$tablename])) {
            //$tablename && isset($GLOBALS['TCA'][$tablename])) {
            $slugTable=$slugTables[$tablename];
            $slugsUtility->setTable($tablename);
            $slugsUtility->setTable($slugTable['table']);
            $slugsUtility->setSlugFieldName($slugTable['slugFieldName']);
            $slugsUtility->setSlugLockedFieldName($slugTable['slugLockedFieldName']);
            $fields=$slugsUtility->getSlugFields();
            $slugsUtility->setFieldNamesToShow($fields);
             if ($tablename=='pages') {
                 //die('pid='.$pid);
                 $pagesCount=$slugsUtility->populateSlugsByUidRecursive([$pid], $depth, $lang);
            } else {
                 if( $pid>0 ) {
                     $pageUids=$slugsUtility->getPageRecordsRecursive($pid, $depth, [$pid]);
                     $pagesCount=$slugsUtility->populateSlugs($pageUids, $lang);
                 } else {
                     $pagesCount=$slugsUtility->populateSlugsAll( $lang);
                 }
            }

             $message=\TYPO3\CMS\Extbase\Utility\LocalizationUtility::translate($pagesCount!=1 ? 'igSlug.populatedSlugs' : 'igSlug.populatedSlug', 'ig_slug', [$pagesCount]);

            $io->success($message);
            return 0;
        } else {
            $io->error('Error table "'.$tablename . '" not found');
            return 1;
        }
 
        //$io->writeln('<comment>Data user=' . $username . ', is password valid='.$validPassword.', FTP User = '. $ftpUser.', new password hash=' .$hashedPassword . '</comment>');
    }

}