<?php

namespace Elendev\Phar\Bundle\Command;

use Symfony\Component\Filesystem\Filesystem;

use Symfony\Component\Console\Input\InputOption;

use Symfony\Component\Finder\Finder;

use Symfony\Component\Console\Command\Command;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PharCreateCommand extends ContainerAwareCommand {
	
	/**
	 * Configure to answere to command shop:basket clean
	 */
	protected function configure(){
		
		$this->setName("elendev:phar:create")->setDescription("Create a phar archive from current application")
			->addArgument("archiveName", InputArgument::OPTIONAL, "Archive name, default app.phar (or other if specified)", null)
			->addOption("archive-version", null, InputOption::VALUE_OPTIONAL, "Phar version to set in metadata")
			->addOption("app-php", null, InputOption::VALUE_OPTIONAL, "Set the app.php filename (example : app.php, app_dev.php)")
			->addOption("target", null, InputOption::VALUE_OPTIONAL, "Application phar path to save to")
			->addOption("dump-file-list", null, InputOption::VALUE_NONE, "dump file list only");
			//->addOption("verbose", "v", InputArgument::OPTIONAL, "Display details", false);
	}
	
	
	/**
	 * Show something
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 */
	protected function execute(InputInterface $input, OutputInterface $output){
		$verboseOption = true;
		
		$container = $this->getContainer();
		
		$kernel = $container->get("kernel");
		
		$rootPath = $container->getParameter("elendev_phar.local.root_directory");
		
		$targetDir = $container->getParameter("elendev_phar.target.directory");
		
		$pharName = $input->getArgument("archiveName");
		if(!$pharName) {
			$pharName = $container->getParameter("elendev_phar.target.name");
		}
		
		$pharPath = $input->getOption("target");
		if(!$pharPath){
			$pharPath = $targetDir . "/" . $pharName;
		}
		
		$appPath = $container->getParameter("elendev_phar.local.app_directory");
		$webPath = $container->getParameter("elendev_phar.local.web_directory");
		$vendorPath = $container->getParameter("elendev_phar.local.vendor_directory");
		$srcPath = $container->getParameter("elendev_phar.local.src_directory");
		
		$archiveWebDir = $container->getParameter("elendev_phar.archive.web_dir");
		$archiveAppPhp = $input->getOption("app-php");
		if(!$archiveAppPhp){
			$archiveAppPhp = $container->getParameter("elendev_phar.archive.app_php");
		}
		
		
		if(OutputInterface::VERBOSITY_NORMAL <= $output->getVerbosity()) {
			$output->writeln("Parameters :");
			
			$parameters = array(
					"elendev_phar.local.root_directory",
					"elendev_phar.local.app_directory",
					"elendev_phar.local.web_directory",
					"elendev_phar.local.vendor_directory",
					"elendev_phar.local.src_directory",
					"elendev_phar.target.directory",
					"elendev_phar.target.name");
			
			foreach($parameters as $parameter){
				$output->writeln("  - " . $parameter . " : " . $container->getParameter($parameter));
			}
			
		}
		
		//STUB :
		$stubFileContent = $this->getStubContent($pharName, $archiveWebDir, $archiveAppPhp);
		
		$finder = new Finder();
		$appPathFinder = new Finder();
		$appPathFinder //avoid cache and logs on app by default
			->files()
			->in($appPath)
			->exclude("cache")
			->exclude("logs");
		$finder
			->files()
			->in($webPath)
			->in($vendorPath)
			->in($srcPath)
			->ignoreDotFiles(true)
			->append($appPathFinder);
		
		if($input->getOption("dump-file-list") || OutputInterface::VERBOSITY_VERY_VERBOSE <= $output->getVerbosity()) {
			foreach($finder as $file){
				$output->writeln($file->getRealpath());
			}
			
			if($input->getOption("dump-file-list")) {
				return;
			}
		}
		
		if(file_exists($pharPath)) {
			if(OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()){
				$output->writeln("Remove existing phar archive " . $pharPath);
			}
			\Phar::unlinkArchive($pharPath);
		}
		
		try {
			$archive = new \Phar($pharPath);
			$archive->startBuffering();
			$archive->setStub($stubFileContent);
			
			if(OutputInterface::VERBOSITY_NORMAL <= $output->getVerbosity()){
				$output->writeln("Creating phar archive " . $pharPath);
			}
			
			$archive->buildFromIterator($finder, $rootPath);
			
			$metadata = array(
				"date" => new \DateTime(),
			);
			
			if($input->hasOption("archive-version")){
				$metadata["archive-version"] = $input->getOption("archive-version");
			}
			
			$archive->setMetadata($metadata);
			
			$archive->stopBuffering();
			
			if(OutputInterface::VERBOSITY_NORMAL <= $output->getVerbosity()){
				$output->writeln("The phar archive " . $pharPath . " has been correctly created");
			}
			
		} catch (\PharException $e){
			if(OutputInterface::VERBOSITY_NORMAL <= $output->getVerbosity()){
				$output->writeln("An error occured while creating phar archive $pharPath : " . $e->getMessage());
			}
			throw $e;
		}
		
	}
	
	
	
	private function getStubContent($pharName, $publicDirectory = "web", $appScript = "app.hp", $confConstantsPrefix = "ELENDEV_PHAR"){
		
		return '<?php
Phar::mapPhar("' . $pharName . '");
Phar::interceptFileFuncs();

$confFile = dirname(__FILE__) . "/" . basename(__FILE__, ".phar") . ".ini";
$configurations = array();
if(file_exists($confFile)){
	$configurations = parse_ini_file($confFile);
	
	$replaceValues = array(
		"%current_dir%" => dirname(__FILE__),
		"%phar_filename%" => basename(__FILE__),
		"%phar_name%" => basename(__FILE__, ".phar")
	);
	
	//set vars as constants after modifying some specific placeholders
	foreach($configurations as $key => $value){
		define("' . $confConstantsPrefix . '_" . strtoupper($key), str_replace(array_keys($replaceValues), $replaceValues, $value));
	}
}

if(php_sapi_name() == "cli") {
	include "phar://" . __FILE__ . "/app/console";
} else {
	$defaultPhpScript = "' . $appScript. '";
	$publicDirectory = "' . $publicDirectory . '";
	$webApp = $publicDirectory . "/" . $defaultPhpScript;
	$pharName = "' . $pharName . '";
	
	Phar::webPhar($pharName, $webApp, $webApp, array(), function ($path) use($webApp, $pharName, $defaultPhpScript, $publicDirectory) {
			$webPath = "phar://" . $pharName . "/" . $publicDirectory;
			//$pharPath = "phar://" . $pharName;
			
			//find php file and separate with query
			$pathInfos = array();
			$tokens = explode("/", $path);
			if(substr($path, 0, 1) === "/"){
				array_shift($tokens); //avoid first empty element
			}
			$validTokenId = -1;
			$filePath = "";
			$requestLeft;
			
			do{
				$validTokenId ++;
			} while(count($tokens) > $validTokenId && file_exists($webPath . "/" . implode("/", array_slice($tokens, 0, $validTokenId + 1))));
			
			$filePath = implode("/", array_slice($tokens, 0, $validTokenId));
			$requestLeft = implode("/", array_slice($tokens, $validTokenId));
			
			
			//echo "File to test : ";
			$validPath = true;
			if(strlen($requestLeft) > 0){
				$validPath = substr($filePath, -4) === ".php";
			}
			
			if($validPath && file_exists($webPath . "/" . $filePath) && is_file($webPath . "/" . $filePath)){
				$_SERVER["SCRIPT_NAME"] = $_SERVER["SCRIPT_NAME"] . "/" . $filePath;
				$_SERVER["SCRIPT_FILENAME"] = $_SERVER["SCRIPT_FILENAME"] . "/" . $filePath;
				
				return $publicDirectory . $path;
			} else {
				//rewrite request URI to avoid pharName for $defaultPhpScript - simulate apache htaccess url rewritter
				$_SERVER["REQUEST_URI"] = str_replace("/" . $pharName, "/" . $pharName . "/" . $defaultPhpScript, $_SERVER["REQUEST_URI"]);
				$_SERVER["SCRIPT_NAME"] = $_SERVER["SCRIPT_NAME"] . "/" . $defaultPhpScript;
				$_SERVER["SCRIPT_FILENAME"] = $_SERVER["SCRIPT_FILENAME"] . "/" . $defaultPhpScript;
				
				return $webApp;
			}
		}
	);
}

__HALT_COMPILER(); ?>';
	}
}