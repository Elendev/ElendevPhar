<?php

namespace Elendev\Phar\Bundle\Command;

use Symfony\Component\Finder\Finder;

use Symfony\Component\Console\Command\Command;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PharExtractCommand extends ContainerAwareCommand {
	
	/**
	 * Configure to answere to command shop:basket clean
	 */
	protected function configure(){
		$this->setName("elendev:phar:extract")->setDescription("Extract given PHAR archive in specified directory")
			->addArgument("archivePath", InputArgument::REQUIRED, "Archive path")
			->addOption("directory", "d", InputArgument::OPTIONAL, "Directory to extract to", null);
			//->addOption("verbose", "v", InputArgument::OPTIONAL, "Display details", false);
	}
	
	
	/**
	 * Show something
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 */
	protected function execute(InputInterface $input, OutputInterface $output){
		$archive = $input->getArgument("archivePath");
		$directory = $input->getOption("directory");
		
		if($directory === null) {
			$directory = dirname($archive) . "/" . basename($archive, ".phar");
		}
		
		$output->writeln("Extract archive $archive in $directory");
		
		try {
			$archive = new \Phar($archive);
			mkdir($directory, 0777, true);
			$archive->extractTo($directory, null, true);
			
			$output->writeln("The phar archive " . $archive . " has been correctly extracted to $directory");
			
		} catch (\PharException $e){
			$output->writeln("An error occured while extracting phar archive $archive to $directory : " . $e->getMessage());
		}
		
	}
	
	
	
	private function getStubContent(){
		return '<?php
Phar::interceptFileFuncs();

if(php_sapi_name() == "cli") {
	include "phar://" . __FILE__ . "/app/console";
} else {
	$defaultPhpScript = "app.php";
	$publicDirectory = "web";
	$webApp = $publicDirectory . "/" . $defaultPhpScript;
	$pharName = "symfony.phar";
	
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